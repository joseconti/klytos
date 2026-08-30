<?php

/**
 * Klytos CMS — the DATABASE storage backend, against a real database.
 *
 * WHY THIS TIER EXISTS. Until 2026-08-30 this project had **no `DatabaseStorage`
 * tests at all** and no database in `keel-doctor`'s requirements, so one of the
 * two shipped storage backends had never executed a single test. That is exactly
 * how D-115's second defect survived: `list()` selected only the `data` column
 * while passing `$row['id']` to `shouldEncrypt()`, so per-record encryption
 * decisions were made with an empty id and the affected records were silently
 * dropped from every listing. No amount of reading the file tier would ever have
 * found it, because the file tier does not have that code path.
 *
 * The absent tier was the finding; this is the tier.
 *
 * HOW TO RUN IT. The database is disposable and local:
 *
 *   docker run -d --name klytos-test-mysql \
 *     -e MARIADB_ROOT_PASSWORD=klytos-test -e MARIADB_DATABASE=klytos_test \
 *     -p 13306:3306 mariadb:11.4
 *
 * and then either export `KLYTOS_TEST_DB_DSN`-style variables or accept the
 * defaults below. **With no database reachable every test here SKIPS with a
 * message that says how to start one** — a developer without Docker still gets a
 * green suite, and the skip is visible rather than silent.
 *
 * MariaDB rather than MySQL 8.4 on purpose, and it is not arbitrary: 8.4 removed
 * `mysql_native_password`, and this machine's `mysqlnd` cannot speak
 * `caching_sha2_password` — measured, `SQLSTATE[HY000] [2054]`. MariaDB is a
 * first-class target for this product either way (`docs/PROGRESS.md` project
 * card names "MySQL/MariaDB").
 *
 * @package    Klytos
 * @license    GPL-3.0-or-later
 * @copyright  Copyright (c) 2026 José Conti — https://klytos.io
 */

declare(strict_types=1);

namespace Klytos\Tests\Unit;

use Klytos\Core\DatabaseStorage;
use Klytos\Core\Encryption;
use PHPUnit\Framework\TestCase;

/**
 * The database backend must answer the same contract the file backend does.
 */
final class DatabaseStorageTest extends TestCase
{
    private string $tempDir = '';
    private ?DatabaseStorage $db = null;

    /** @return array<string,mixed> */
    private static function dbConfig(): array
    {
        return [
            'host'     => getenv( 'KLYTOS_TEST_DB_HOST' ) ?: '127.0.0.1',
            'port'     => (int) ( getenv( 'KLYTOS_TEST_DB_PORT' ) ?: 13306 ),
            // `name` and `pass`, not `database`/`password`: those are the keys
            // `DatabaseStorage::getPdo()` actually reads (database-storage.php
            // :712-714), and getting them wrong fails as a config error rather
            // than a connection one.
            'name'     => getenv( 'KLYTOS_TEST_DB_NAME' ) ?: 'klytos_test',
            'user'     => getenv( 'KLYTOS_TEST_DB_USER' ) ?: 'root',
            'pass'     => getenv( 'KLYTOS_TEST_DB_PASS' ) ?: 'klytos-test',
            'prefix'   => 'kly_test_',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $config = self::dbConfig();
        $dsn    = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['name']
        );

        try {
            $probe = new \PDO( $dsn, $config['user'], $config['pass'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 2,
            ] );
        } catch ( \Throwable $e ) {
            $this->markTestSkipped(
                'No test database reachable at ' . $config['host'] . ':' . $config['port'] . '. Start one with: '
                . 'docker run -d --name klytos-test-mysql -e MARIADB_ROOT_PASSWORD=klytos-test '
                . '-e MARIADB_DATABASE=klytos_test -p 13306:3306 mariadb:11.4'
            );
        }

        $this->tempDir = sys_get_temp_dir() . '/klytos-dbtest-' . bin2hex( random_bytes( 8 ) );
        mkdir( $this->tempDir . '/config', 0700, true );
        mkdir( $this->tempDir . '/data', 0700, true );

        $keyPath = $this->tempDir . '/config/.encryption_key';
        Encryption::generateKey( $keyPath );

        // Every table this class touches, dropped before it is used, so a run
        // never inherits another run's rows.
        foreach ( ['config', 'widgets', 'analytics'] as $collection ) {
            $probe->exec( 'DROP TABLE IF EXISTS `' . $config['prefix'] . str_replace( '-', '_', $collection ) . '`' );
        }

        $this->db = new DatabaseStorage( new Encryption( $keyPath ), $this->tempDir . '/data', $config );
        $this->db->createTables( ['config', 'widgets', 'analytics'] );
    }

    protected function tearDown(): void
    {
        if ( $this->tempDir !== '' && is_dir( $this->tempDir ) ) {
            exec( 'rm -rf ' . escapeshellarg( $this->tempDir ) );
        }

        parent::tearDown();
    }

    // ─── The contract ────────────────────────────────────────────

    /** A record written comes back, decrypted, under its own id. */
    public function testWriteThenReadRoundTrips(): void
    {
        $this->db->write( 'widgets', 'alpha', ['name' => 'Alpha', 'kind' => 'a'] );

        $this->assertSame( ['name' => 'Alpha', 'kind' => 'a'], $this->db->read( 'widgets', 'alpha' ) );
    }

    /** `listWithIds()` keys by the storage id on this backend too. */
    public function testListWithIdsKeysByTheStorageId(): void
    {
        $this->db->write( 'widgets', 'alpha', ['name' => 'Alpha', 'kind' => 'a'] );
        $this->db->write( 'widgets', 'beta', ['name' => 'Beta', 'kind' => 'b'] );

        $rows = $this->db->listWithIds( 'widgets' );

        // Compared as a SET, not a sequence: this backend orders by
        // `updated_at DESC` (database-storage.php:366), which is its own
        // documented behaviour and not something this test gets to redefine.
        $keys = array_keys( $rows );
        sort( $keys );
        $this->assertSame( ['alpha', 'beta'], $keys );
        $this->assertArrayNotHasKey( 'id', $rows['alpha'], 'the identity is the KEY, not an injected field' );
    }

    /**
     * THE POINT: a record listed on this backend can be deleted.
     *
     * On the file backend the missing id THREW; here it became
     * `DELETE … WHERE id = ''`, matched nothing and returned false in silence.
     * That difference is why D-115's class went unnoticed for the life of the
     * product.
     */
    public function testARecordThatWasListedCanBeDeleted(): void
    {
        $this->db->write( 'widgets', 'alpha', ['name' => 'Alpha'] );
        $this->db->write( 'widgets', 'beta', ['name' => 'Beta'] );

        foreach ( $this->db->listWithIds( 'widgets' ) as $id => $row ) {
            if ( $row['name'] === 'Alpha' ) {
                $this->assertTrue( $this->db->delete( 'widgets', (string) $id ) );
            }
        }

        $this->assertSame( ['beta'], array_keys( $this->db->listWithIds( 'widgets' ) ) );
    }

    /** `list()` is `listWithIds()` without the keys — the same derivation as the file backend. */
    public function testListIsExactlyListWithIdsWithoutTheKeys(): void
    {
        $this->db->write( 'widgets', 'alpha', ['name' => 'Alpha', 'kind' => 'a'] );
        $this->db->write( 'widgets', 'beta', ['name' => 'Beta', 'kind' => 'b'] );
        $this->db->write( 'widgets', 'gamma', ['name' => 'Gamma', 'kind' => 'a'] );

        $this->assertSame(
            array_values( $this->db->listWithIds( 'widgets' ) ),
            $this->db->list( 'widgets' ),
            'the two views must agree on this backend as well'
        );
    }

    /** Pagination keeps the keys rather than renumbering them away. */
    public function testPaginationPreservesTheKeys(): void
    {
        foreach ( ['alpha', 'beta', 'gamma'] as $id ) {
            $this->db->write( 'widgets', $id, ['name' => $id] );
        }

        $page = $this->db->listWithIds( 'widgets', [], 1, 1 );

        $this->assertCount( 1, $page );
        $this->assertNotSame( [0], array_keys( $page ), 'the key is an id, not an offset' );
    }

    /**
     * THE REGRESSION THIS TIER WAS BUILT FOR.
     *
     * `config` is encrypted PER RECORD — ids `tokens`, `app_passwords`,
     * `oauth_clients`, `site`, `theme`, `menus`, `templates`, `post_types`
     * (`encryption-level-trait.php:53`, `:80`). `list()` used to fetch only the
     * `data` column and then ask `shouldEncrypt( $collection, $row['id'] ?? '' )`
     * — always `''` — so those records were treated as plaintext, `json_decode()`
     * on ciphertext returned null, and the loop's `continue` **dropped them from
     * the listing entirely**. A person reading the Options screen on MySQL would
     * simply not see them, with no error anywhere.
     */
    public function testPerRecordEncryptedConfigRowsSurviveAListing(): void
    {
        // `tokens`, not `site`: `site` is per-record-encrypted only from the
        // `professional` level up, while `tokens` is on the list from `basic`
        // (encryption-level-trait.php:53), so this test does not depend on the
        // level a fresh instance happens to default to. The precondition below
        // asserts it rather than trusting the reading.
        $this->db->write( 'config', 'tokens', ['title' => 'Encrypted per record'] );
        $this->db->write( 'config', 'plain_example', ['title' => 'Not on the per-id list'] );

        $this->assertTrue(
            $this->db->shouldEncrypt( 'config', 'tokens' ),
            'precondition: `tokens` really is a per-record-encrypted id'
        );
        $this->assertFalse(
            $this->db->shouldEncrypt( 'config', '' ),
            'precondition: an EMPTY id answers "not encrypted" — which is what the bug relied on'
        );

        $rows = $this->db->listWithIds( 'config' );

        $this->assertArrayHasKey(
            'tokens',
            $rows,
            'the per-record-encrypted row must not vanish from the listing'
        );
        $this->assertSame( 'Encrypted per record', $rows['tokens']['title'] );
        $this->assertArrayHasKey( 'plain_example', $rows );
    }

    /** Filters work, and the encrypted rows are filtered on their decrypted values. */
    public function testFiltersApplyToDecryptedValues(): void
    {
        $this->db->write( 'widgets', 'alpha', ['name' => 'Alpha', 'kind' => 'a'] );
        $this->db->write( 'widgets', 'beta', ['name' => 'Beta', 'kind' => 'b'] );

        $this->assertSame( ['alpha'], array_keys( $this->db->listWithIds( 'widgets', ['kind' => 'a'] ) ) );
    }

    /** A missing table is an empty listing, not an exception. */
    public function testAMissingCollectionIsEmpty(): void
    {
        $this->assertSame( [], $this->db->listWithIds( 'never-created' ) );
    }
}
