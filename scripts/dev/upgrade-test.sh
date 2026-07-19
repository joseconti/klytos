#!/usr/bin/env bash
#
# Klytos CMS — upgrade test from the REAL previous release (Sprint 1, slice 3).
#
# Keel Phase 5 makes this mandatory whenever `Installed base: yes`: the upgrade
# path is tested from the real previous version, not only from a clean install.
# Slice 3 removes the v1.x owner fallback from klytos_current_user() (NEW-01,
# D-021), and the only honest way to show that does not lock out a production
# install is to build one with the previous release's own installer and then
# upgrade it.
#
# WHY A TEMP DIRECTORY, NOT THE CHECKOUT: installer/install.php is destructive
# to a repository. It renames the tracked install.php, renames installer/ to
# <hex>-admin, copies files into the PARENT directory and deletes
# ../installer.php (audit NEW-04, docs/playground.md). Run outside the repo it
# is simply the product doing its job; run inside it, it eats the checkout.
# This script therefore never touches the working tree.
#
# @package    Klytos
# @license    GPL-3.0-or-later
# @copyright  Copyright (c) 2026 José Conti — https://klytos.io

set -euo pipefail

PREVIOUS_VERSION="${1:-v0.30.1}"
PORT="${PORT:-8099}"
REPO_ROOT="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../.." && pwd )"

if [ ! -d "${REPO_ROOT}/.git" ]; then
    echo "FAIL: ${REPO_ROOT} is not a git checkout." >&2
    exit 1
fi

# ^{commit}, not ^{tag}: this repository's release tags are lightweight, so they
# dereference straight to a commit and ^{tag} would reject every one of them.
if ! git -C "${REPO_ROOT}" rev-parse --verify --quiet "${PREVIOUS_VERSION}^{commit}" > /dev/null; then
    echo "FAIL: tag ${PREVIOUS_VERSION} does not exist. Available:" >&2
    git -C "${REPO_ROOT}" tag --sort=-v:refname | head -5 >&2
    exit 1
fi

SITE_ROOT="$( mktemp -d "${TMPDIR:-/tmp}/klytos-upgrade-XXXXXXXX" )"
SERVER_PID=""

cleanup() {
    if [ -n "${SERVER_PID}" ] && kill -0 "${SERVER_PID}" 2> /dev/null; then
        kill "${SERVER_PID}" 2> /dev/null || true
        wait "${SERVER_PID}" 2> /dev/null || true
    fi
    # Guard the rm: only ever remove a path we just created under the temp dir.
    case "${SITE_ROOT}" in
        "${TMPDIR:-/tmp}"/klytos-upgrade-*) rm -rf "${SITE_ROOT}" ;;
        /tmp/klytos-upgrade-*)              rm -rf "${SITE_ROOT}" ;;
        *) echo "REFUSING to remove unexpected path ${SITE_ROOT}" >&2 ;;
    esac
}
trap cleanup EXIT

echo "== Klytos upgrade test: ${PREVIOUS_VERSION} -> working tree"
echo "   site root: ${SITE_ROOT}"

# ── 1. Lay down the REAL previous release ────────────────────────────────────
echo
echo "-- 1. Exporting ${PREVIOUS_VERSION} from git"
git -C "${REPO_ROOT}" archive "${PREVIOUS_VERSION}" | tar -x -C "${SITE_ROOT}"
# VERSION lives inside installer/, not at the repo root: app.php resolves it as
# dirname(__DIR__).'/VERSION' with __DIR__ = installer/core.
INSTALLED_VERSION="$( cat "${SITE_ROOT}/installer/VERSION" 2>/dev/null || echo unknown )"
echo "   VERSION on disk: ${INSTALLED_VERSION}"

# ── 2. Install it with ITS OWN installer ─────────────────────────────────────
echo
echo "-- 2. Running the ${PREVIOUS_VERSION} installer (php -S on 127.0.0.1:${PORT})"
php -S "127.0.0.1:${PORT}" -t "${SITE_ROOT}" > "${SITE_ROOT}/server.log" 2>&1 &
SERVER_PID=$!

for _ in $( seq 1 40 ); do
    if curl -fsS -o /dev/null "http://127.0.0.1:${PORT}/installer/install.php" 2> /dev/null; then
        break
    fi
    sleep 0.25
done

HTTP_CODE="$( curl -s -o "${SITE_ROOT}/install-response.html" -w '%{http_code}' \
    -X POST "http://127.0.0.1:${PORT}/installer/install.php" \
    --data-urlencode 'step=install' \
    --data-urlencode 'site_name=Upgrade Test Site' \
    --data-urlencode 'admin_user=upgradeowner' \
    --data-urlencode 'admin_pass=upgrade-test-2026-Aa!' \
    --data-urlencode 'admin_pass_confirm=upgrade-test-2026-Aa!' \
    --data-urlencode 'admin_email=upgrade@example.test' \
    --data-urlencode 'admin_language=en' \
    --data-urlencode 'design_preference=dark' \
    --data-urlencode 'storage_driver=file' )"

echo "   installer responded ${HTTP_CODE}"

# The installer renames installer/ to <hex>-admin as its final step.
ADMIN_DIR="$( find "${SITE_ROOT}" -maxdepth 1 -type d -name '*-admin' | head -1 )"

if [ -z "${ADMIN_DIR}" ]; then
    echo "FAIL: the installer did not produce an admin directory." >&2
    echo "      Response head:" >&2
    head -40 "${SITE_ROOT}/install-response.html" >&2
    exit 1
fi

echo "   installed to: $( basename "${ADMIN_DIR}" )"

kill "${SERVER_PID}" 2> /dev/null || true
wait "${SERVER_PID}" 2> /dev/null || true
SERVER_PID=""

# ── 3. Prove the OLD version really is installed and has an owner ────────────
echo
echo "-- 3. Verifying the ${PREVIOUS_VERSION} install before upgrading"
php "${REPO_ROOT}/scripts/dev/upgrade-assert.php" "${ADMIN_DIR}" pre-upgrade

# ── 4. Upgrade: overlay the working tree's code, as the self-updater does ────
echo
echo "-- 4. Upgrading in place (core/, admin/, VERSION) from the working tree"
rm -rf "${ADMIN_DIR}/core"
cp -R "${REPO_ROOT}/installer/core" "${ADMIN_DIR}/core"
cp "${REPO_ROOT}/installer/VERSION" "${ADMIN_DIR}/VERSION"
NEW_VERSION="$( cat "${REPO_ROOT}/installer/VERSION" )"
echo "   upgraded to VERSION ${NEW_VERSION}"

# ── 5. Boot the UPGRADED install and assert the slice-3 properties ───────────
echo
echo "-- 5. Booting the upgraded install and asserting slice 3"
php "${REPO_ROOT}/scripts/dev/upgrade-assert.php" "${ADMIN_DIR}" post-upgrade

# ── 6. The failing-migration path: boot must survive it, fail-closed ─────────
#
# This is the ONLY place the Step 10b try/catch is actually executed. Every
# other test asset boots an install that already has an owner, so
# migrateFromV1Config() is never reached THROUGH App::boot() — the defensive
# code would sit there unverified, which this project's own testing rule calls
# an unverified claim rather than a fix. Two separate PHP processes are required
# because App is a singleton that boots once and has no reset.
echo
echo "-- 6. Breaking the install so the boot migration must fail"
php "${REPO_ROOT}/scripts/dev/upgrade-assert.php" "${ADMIN_DIR}" break-migration

echo
echo "-- 7. Booting the broken install (this used to fatal on every request)"
php "${REPO_ROOT}/scripts/dev/upgrade-assert.php" "${ADMIN_DIR}" boot-must-survive

echo
echo "== UPGRADE TEST PASSED (${PREVIOUS_VERSION} -> ${NEW_VERSION})"
