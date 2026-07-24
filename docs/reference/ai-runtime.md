# AI runtime requirements — how Klytos refuses a host it cannot run AI on

> Created Sprint 3 slice 2 (2026-07-25), closing audit **NEW-06**. Decision: **D-053**.
> Related: **D-052** (the re-vendor that made the floor current), **D-028** (the vendor-ai manifest).

## The short version

The AI chat feature needs a **newer PHP than Klytos itself does**. Klytos supports **PHP 8.1+**; the
vendored AI dependency tree needs **PHP 8.3+**. On a host between those two versions everything in
Klytos works except AI, and Klytos now says so instead of crashing.

## Why the two floors differ, and why that is not a bug

`installer/vendor-ai/` is a vendored Composer tree loaded lazily by `App::getChatEngine()` and by
nothing else. Its root requirement, `soukicz/llm`, declares `"php": ">=8.3"`. Klytos does not choose
that floor and cannot lower it without dropping the AI SDK.

This project asserts **four** different PHP floors, and reconciling them is deliberately *not* this
document's job:

| Floor | Where | What it governs |
|---|---|---|
| 8.0 | `installer/index.php` | the public front controller's hard minimum |
| **8.1** | `README.md`, `installer/install.php`, `installer/core/updater.php` | **the product's declared support floor** |
| 8.2 | PHPUnit 11 (D-027) | the test suite — so the declared floor cannot be verified by the suite |
| **8.3** | `soukicz/llm` → `vendor-ai/composer/platform_check.php` | **the AI feature only** |

Raising the product floor to 8.3 is a support-matrix decision with installed-base consequences and
belongs to **D-027's trigger**. What this slice does is make the 8.3 requirement *fail safe*, which is
Keel's standing rule for every external dependency: an absent or version-incompatible dependency
degrades its feature with an explicit message and never fatals the host.

## What used to happen (audit NEW-06)

`App::getChatEngine()` guarded the vendored autoloader with `file_exists()` — a **presence** check,
not a **version** check. Below PHP 8.3 the file exists, the `require_once` proceeds, and Composer's
generated `vendor-ai/composer/platform_check.php` (required unconditionally by `autoload_real.php`):

1. sends `HTTP/1.1 500 Internal Server Error`,
2. echoes `Composer detected issues in your platform: …` **into the response body**, and
3. throws a bare `\RuntimeException`.

All three happen inside vendored code. The operator sees a 500 carrying third-party text, with no
indication that AI chat is the feature at fault or that the rest of the CMS is fine.

## What happens now

`App::getChatEngine()` decides **before** requiring the vendored autoloader — ordering that is the
load-bearing part, because once the require has run it is already too late to intervene:

```php
$reason = self::aiRuntimeUnsupportedReason( PHP_VERSION_ID );

if ( $reason !== null ) {
    klytos_do_action( 'ai.runtime_unsupported', $reason, self::AI_MIN_PHP_VERSION_ID, PHP_VERSION_ID );
    throw new Klytos\Core\Ai\UnsupportedRuntimeException( /* translated message */, 80300, PHP_VERSION_ID );
}
```

All three call sites — `installer/admin/api/ai-chat.php`, `installer/admin/api/translations-ai.php`
and `installer/core/mcp/tools/translation-tools.php` — already wrap `getChatEngine()` in
`try { … } catch ( \Throwable )`, so each turns the refusal into its own existing error response with
no change. A caller that wants to say something more precise catches the typed exception instead.

## The public surface

### `App::AI_MIN_PHP_VERSION_ID` (constant, `int`)

The `PHP_VERSION_ID` the vendored AI stack requires — currently `80300`. Written **once**. Its two
upstream sources are `installer/vendor-ai/soukicz/llm/composer.json` (`"php": ">=8.3"`) and the
generated `installer/vendor-ai/composer/platform_check.php` (`PHP_VERSION_ID >= 80300`);
`AiRuntimeGuardTest::testTheConstantMatchesTheGeneratedPlatformCheck()` fails if the constant and the
generated file ever disagree, so a future re-vendor that moves the floor cannot pass silently.

### `App::aiRuntimeUnsupportedReason( int $phpVersionId ): ?string`

Pure and static. Returns a stable machine reason (`'php_version_too_low'`) or `null` when the runtime
is supported. It takes the version as a **parameter** rather than reading `PHP_VERSION_ID` because PHP
cannot be downgraded inside a test suite — a guard that read the constant directly would have a branch
no test could ever reach, which is indistinguishable from a branch that cannot fire (L-010). This is
the same split D-044 made for `Auth::buildSecurityHeaders()`.

Returning a machine reason rather than a sentence follows `SafeHttp`'s `REASON_*` convention (D-041):
the caller owns the wording and the translation, so the policy stays testable with no I18n service.

```php
Klytos\Core\App::aiRuntimeUnsupportedReason( 80200 );  // 'php_version_too_low'
Klytos\Core\App::aiRuntimeUnsupportedReason( 80300 );  // null — supported
```

### `Klytos\Core\Ai\UnsupportedRuntimeException`

Extends `\RuntimeException`. Carries the translated, operator-facing message plus both version ids:

```php
try {
    $engine = $app->getChatEngine();
} catch ( Klytos\Core\Ai\UnsupportedRuntimeException $e ) {
    // "AI features need PHP 8.3 or newer. This site runs PHP 8.2.15, so the AI
    //  engine is disabled. The rest of Klytos is unaffected — ask your hosting
    //  provider to upgrade PHP."
    error_log( $e->getMessage() );
    error_log( sprintf( 'needs %d, running %d', $e->getRequiredVersionId(), $e->getRunningVersionId() ) );
}
```

It is typed rather than bare so a caller can distinguish *"this host cannot run AI chat"* from
*"the AI call failed"* — a distinction `\RuntimeException` cannot express.

### `ai.runtime_unsupported` (action)

```php
klytos_add_action( 'ai.runtime_unsupported', function ( $reason, $required, $running ) {
    error_log( "Klytos AI disabled: {$reason} (needs {$required}, running {$running})" );
}, 10, 3 );
```

**An action, never a filter.** A listener must not be able to talk this refusal into proceeding,
because proceeding means the fatal described above. Same reasoning as `auth.access_denied` (D-032) and
`http.safe.blocked` (D-041).

**Nothing in core subscribes to it.** Stated plainly because L-019 was recorded for exactly the
opposite claim: an extension point is a *seam*, not a *sink*, and a seam with no subscriber writes
nothing anywhere. If you want these refusals in a log, register the listener above — the core product
does not do it for you.

## The message

`__( 'ai.unsupported_runtime', [ 'required' => …, 'running' => … ] )`, present in **all 20** locale
catalogues under the `ai` domain. It names the required version, the running version, the fact that
only the AI feature is affected, and the fix (ask the host to upgrade PHP). It discloses nothing about
the installation.

## What is deliberately NOT here

- **No fallback, no degraded AI mode.** There is nothing to fall back to: the SDK is the feature.
- **No filter to override the floor.** See above — overriding it produces the fatal.
- **No change to the product's declared PHP support.** D-027's trigger owns that.
- **`installer/index.php`'s 8.0 check is untouched**, as are the installer's and updater's 8.1 checks.
  This slice adds a floor for one feature; it does not reconcile the four that exist.
