# Machine recipe — Klytos CMS

> Machines covered: the `klytos` tenant on the shared Parallels VM `keel-development`, and the
> disposable `klytos-install` tenant (created for one standalone-installer test, retired after it).
> Host: **parallels-shared** (D-121).
> Last updated: 2026-11-12 — written while creating the tenant (`scripts/keel-site create`), from the
> running VM, not from memory.
> Last rebuilt from this recipe: **2026-11-12 — PASS** (the tenant was created, retired and created
> again three times while `scripts/keel-site` was being written; the last run is the evidence below).
>
> The shared VM itself is described in `docs/machines/keel-development/recipe.md` (base revision
> `keel-base-rev=2026-11-12-1`). This file is only Klytos's own layer on top of it.

## 1. Host prerequisites

- Everything in `docs/machines/keel-development/recipe.md` §1 (Parallels Desktop Pro/Business with a
  working `prlctl`, the `~/.ssh/keel_development` key, macOS on Apple silicon).
- Python 3 on the host (`scripts/keel-site` is Python; macOS ships `/usr/bin/python3`).
- `git` on the host: the sync list is computed by `git ls-files` there.
- For the host-side tiers (unchanged): PHP 8.3, Composer, Node + `npm ci` — see `docs/playground.md`.

## 2. Base

- No VM of its own. One tenant on `keel-development` (Ubuntu 24.04.5 arm64, 4 vCPU, 8 GB, 120 GB cap).
- The VM's lease is the shared one (`keel-lease: owner=shared-plugin-host until=never`). The tenant has
  no lease of its own: it lives while this project is active and `scripts/keel-site retire` removes it.
- `klytos-install` is created for ONE installer test and retired in the same session (cleanup contract).

## 3. Packages

None added to the VM. The tenant uses the base layer's Apache 2.4.58 (`rewrite headers` enabled),
PHP **8.3** FPM + CLI (all extensions Klytos needs: openssl json mbstring session curl zip sodium),
MariaDB 10.11, Composer 2.7, rsync. The project's PHPUnit 11.5 is installed INSIDE the tenant copy by
`scripts/keel-site test` (`composer install`, from `composer.lock`).

## 4. Configuration (all written by `scripts/keel-site create`; nothing global changed)

| Path in the VM | Content |
|---|---|
| `/etc/php/8.3/fpm/pool.d/<site>.conf` | pool `[<site>]`, user/group `ks-<site>`, socket `/run/php/ks-<site>.sock`, `pm = ondemand`, `max_children 6`, `open_basedir = /srv/keel/sites/<site>:/tmp:/usr/share/php`, errors to `/srv/keel/sites/<site>/php-error.log`, `display_errors off` |
| `/etc/apache2/sites-available/<site>.conf` (enabled) | `Listen <port>`, vhost with `DocumentRoot /srv/keel/sites/<site>/public`, `AllowOverride All`, PHP through the pool socket, logs `<site>-error.log` / `<site>-access.log` |
| `/srv/keel/sites/klytos/public/.htaccess` | dev-only web-root rules (never shipped): `FollowSymLinks`, dotfiles 403, everything outside `/installer/` rewritten to `/installer/public/` — the same mapping `scripts/dev/router.php` gives `php -S` |
| `/srv/keel/sites/klytos/public/installer` | symlink → `../app/installer` (the copy below) |
| `/srv/keel/sites/<site>/site.env` | `php= port= kind= klytos_share=` (bash-safe; the VM's `keel-mounts` service ignores it because there is no `share=` key) |
| `/srv/keel/sites/<site>/db.env` (0600, tenant user) | `DB_NAME DB_USER DB_PASSWORD DB_HOST` — generated per tenant at creation, never written to the repository |

`installer/.htaccess` — the production access controls — is served by real Apache here, so the
playground exercises it instead of the router's re-implementation.

## 5. Isolation and sharing

- Unix user and group `ks-klytos` (system user, home `/srv/keel/sites/klytos`, mode 750, `www-data`
  added to the group plus an ACL `u:www-data:rx` on the root).
- Database and user `ks_klytos` on the system MariaDB (unused by the flat-file seed; ready for the
  `DatabaseStorage` tier).
- Parallels share **`klytos`**, read-only, this repository only:
  `prlctl set keel-development --shf-host-add klytos --path <repo> --mode ro` (added live by
  `create`; never restart `prltoolsd`, it would unmount every other tenant's share).
- **A synced COPY, not a mount (D-121, measured).** Klytos writes into its own tree at runtime, and an
  overlayfs over the share denies the tenant user read access (prl_fsd exports `keel:0500`). So
  `/srv/keel/sites/klytos/app` is an rsync copy of exactly what `git ls-files -co --exclude-standard`
  lists on the host (minus `.claude/`, `.agents/`, `node_modules/`, `tests/E2E/artifacts/`), owned by
  the tenant user with normal modes. Ignored paths — the runtime state in `installer/config`, `data`,
  `backups`, the generated site, `vendor/` — are never copied from the host and never deleted on the
  VM. A file the previous sync copied and the new list lacks is deleted (`.sync-old` vs `.sync-new`).
  Every `keel-site` command that touches the site syncs first.

## 6. Network

| Site | VM port | Host address (SSH forward) |
|---|---|---|
| `klytos` | 8121 | `http://127.0.0.1:18121` |
| `klytos-install` (disposable) | 8122 | `http://127.0.0.1:18122` |

- The forward is `/usr/bin/ssh -N -L 127.0.0.1:<local>:127.0.0.1:<port> keel@<vm ip>` (macOS Local
  Network privacy blocks non-Apple binaries from the VM address). PID in `.keel-site/` (gitignored).
- The HTTP test tier binds `php -S` on a fixed band from 8101; on this VM other tenants own
  8101–8115, so `keel-site test` sets `KLYTOS_TEST_PORT_OFFSET=1000` (band from 9101, free).
- No hosts entries, no partner sites, no inbound tunnel (none in the technical plan's inventory).

## 7. Data

- Seed: `scripts/dev/seed-playground.php` run inside the tenant as `ks-klytos` (`keel-site seed`,
  `keel-site reset` = `--reset`). Synthetic data only; the seeded passwords are the throwaway ones in
  `docs/playground.md`; the MCP application password is generated per seed and lives only in the
  tenant's `installer/config/.playground-access`.
- Nothing on the tenant is irreplaceable.

## 8. Rebuild procedure

```sh
# 0. The shared VM exists and is running (else docs/machines/keel-development/recipe.md §9).
./scripts/keel-site create            # share, user, pool, vhost, database, copy, web root, seed
./scripts/keel-site start             # sync + SSH forward; prints http://127.0.0.1:18121
# Standalone-installer test (sibling repo ../klytos-installer), then retire it in the same session:
./scripts/keel-site --site klytos-install create && ./scripts/keel-site --site klytos-install install-test
./scripts/keel-site --site klytos-install retire
```

## 9. Verification (run 2026-11-12, all PASS)

- `./scripts/keel-site doctor` → VM running, revision matches, `klytos` admin answers 302.
- Gate zero through the forward: admin `302` → login; MCP `POST` without auth `401`; `403` on
  `installer/config/.encryption_key`, `config.json.enc`, `installer/data/*`, `installer/core/*`,
  `installer/backups/*`, `installer/plugins/**/*.php`, `.git/config`, `admin-identity.*`, `*.pem`,
  `installer/.htaccess`; the security headers present on `login.php`.
- A real form login as `owner` → Dashboard `200`; authenticated MCP `tools/list` → **206 tools**.
- `./scripts/keel-site test --testsuite integration` → **OK (280 tests, 1601 assertions)** — identical
  to the host run with the same port offset.
- `./scripts/keel-site test --testsuite unit` → 205 tests, 4 failures that are Linux/tenant
  differences, recorded in L-052: three `KeelVerifyTest` (the copy has no `.git`), one
  `SafeHttpTest` data set (`0x7f000001` is refused as `host_does_not_resolve` instead of
  `private_or_reserved_address` by glibc's resolver). Host: 205 OK.
- Installer: `klytos-installer/installer.php` downloaded and extracted the published release
  **v0.30.1**, the wizard installed it (file storage), `installer.php` was removed, the admin directory
  renamed, `/` `200`, the new admin `302` → login → first-run Setup Wizard `200`, protections `403`.
  Tenant retired after (25 MB freed).
