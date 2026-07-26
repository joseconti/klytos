# FileLock — one exclusive critical section for a read-modify-write

`Klytos\Core\FileLock` (`installer/core/file-lock.php`) runs a
read → decide → write cycle on a JSON file while holding **one** exclusive
lock for the whole cycle.

It exists because the product's two abuse-bounding counters — the login lockout
(`Auth`) and `Klytos\Core\MCP\RateLimiter` — both read their file, decided, and
wrote it back under *separate* locks. Between the read and the write another
process could read the same pre-increment value, so increments were lost, and
**every lost increment is one request that was never counted against its
limit**. Recorded as audit NEW-40 and NEW-20; closed together by D-059.

## How bad it actually was

Measured at the Sprint 6 kickoff, before this class existed, with 20 processes
each issuing one `RateLimiter::check()` at the same instant:

```
round 1: 20 workers -> 2 recorded, 18 LOST
round 2: 20 workers -> 3 recorded, 17 LOST
round 3: 20 workers -> 3 recorded, 17 LOST
round 4: 20 workers -> 4 recorded, 16 LOST
round 5: 20 workers -> 3 recorded, 17 LOST
```

After the conversion, the same probe records **20 of 20 in every round**. The
audit entry's own wording ("somewhat more than the nominal five attempts")
understated it: under parallel load the limiter counted roughly 15% of what it
received. That number came from running it, not from reading it.

## Usage

```php
use Klytos\Core\FileLock;

$allowed = false;

$ran = FileLock::transaction(
    $dataDir . '/hits.json',
    function ( array $data ) use ( &$allowed ): array {
        $allowed = count( $data['hits'] ?? [] ) < 10;

        if ( $allowed ) {
            $data['hits'][] = time();
        }

        return $data;
    }
);

// A lock we could not take is NOT permission to proceed.
if ( ! $ran || ! $allowed ) {
    // refuse
}
```

The callback returns the array to persist, or `null` to persist nothing (a
read-only decision). To return a decision as well as data, capture a variable
with `use ( &$x )`. That is **reference capture**, which is unaffected by the
by-reference refusal D-054 added to the hook registries — that check reflects
*parameters*, and this is not a hook.

## Fail directions — they differ, and the difference is deliberate

| Situation | Behaviour | Why |
|---|---|---|
| Lock not acquired within the deadline | `transaction()` returns **false**, the callback never runs | Not counting an attempt is exactly the amplification this class closes. "We could not count it" is never a reason to allow it |
| File missing | Callback receives `[]`, file is created | A first request is not an error |
| File unreadable or undecodable | Callback receives `[]`, the condition is logged, the transaction proceeds | Refusing everyone because a counter file is corrupt would turn one damaged file into a total login outage — a worse failure than the race being fixed |

The two are not symmetrical on purpose. D-059 records the reasoning rather than
leaving a later reader to assume one of them is an oversight.

## Why it is not `ActionScheduler::acquireLock()`

Audit NEW-40 named that method as the fix. It is **private** to its class and
takes `LOCK_EX | LOCK_NB` — *skip if busy*, returning `null`. Skipping is right
for a scheduler (another process is already doing the run) and wrong for a
counter: under the parallel burst this class exists to bound, every contender
would fail to acquire and skip its own increment, which is a **deterministic**
lost update rather than a racy one.

The two locks therefore stay separate. Two locks with genuinely different
contracts are not the duplication this project treats as a defect; two locks
with the *same* contract would be, and that is what this class prevents.

## Why the critical section is narrow

The lock spans the counter's own read-modify-write and nothing else. It
deliberately does **not** span `Auth::login()`: `UserManager::authenticate()`
performs a bcrypt verify on every branch (the NEW-39 equalization), so holding
a lock across it would serialise every login attempt on the install behind that
verify — a denial-of-service lever built by a hardening fix. The remaining
window is closed by the IP ceiling in `admin/login.php`, not by a wider lock.

## Pre-boot safety

`installer/public/comment-submit.php` constructs a `RateLimiter` **before**
`App::boot()` by design (D-043), so nothing in this class may depend on the
autoloader, the hook system or the Klytos logger. Every optional dependency is
guarded with `function_exists()`, and the only unconditional sink is
`error_log()` — the same reasoning L-006 recorded for the boot-time logger, and
the reason it is not `klytos_log_warning()`: audit **NEW-44** is precisely a
guard whose diagnostics were silently dropped.

`RateLimiter` loads this class lazily through a private `ensureFileLock()`
rather than requiring it at file scope, so the file still declares a symbol
without causing a side effect.

## Extension points

| Hook | Type | Notes |
|---|---|---|
| `file_lock.timeout_ms` | filter | Widen or narrow the wait. Skipped entirely when the hook system does not exist yet (pre-boot). |
| `file_lock.timeout` | action | Fires when a lock was not acquired, with the path and the timeout. **Nothing in core subscribes to it** — it is an audit seam, not a sink (L-019). It cannot reverse the refusal. |

## Storage shape

An empty map is written as `{}` rather than the file being deleted, so no
`unlink` can race a lock held on the same path. Removing the file by hand still
clears its contents, which is what
[`docs/reference/authentication.md`](authentication.md) documents for the login
lockout.
