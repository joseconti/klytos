# Owner recovery — getting back into an install whose owner record is missing

> Created Sprint 4 slice 2 (2026-07-25), closing audit **NEW-08**. Decision: **D-055**.
> Related: **D-031** (the contained boot crash this completes), **D-021** (the fail-closed identity
> that made the state visible), **NEW-11 / D-056** (Sprint 5 made the user RECORD the login
> authority — this command is unaffected, because it rebuilds the record from config's seed values;
> what changed is that the restored record is now what the gate reads, so recovery is if anything
> more direct than when this was written).

## The short version

```bash
php installer/cli.php owner:repair --email=you@example.com
```

Run from the installation directory, on a host with shell access. It restores the owner account and
**does not change your password** — you log back in with the one you already had.

## What actually breaks, because the fix follows from it

`App::boot()` Step 10b runs `UserManager::migrateFromV1Config()`, which builds the owner record from
**`config['admin_user']` and `config['admin_pass_hash']`**. It throws when the config has no usable
`admin_email`. Before Sprint 1 that throw took every request down; **D-031** contained it, so boot now
logs and continues — leaving an install whose *credentials are intact* but whose owner **record** does
not exist. Every permission check then denies, and nothing could put the record back.

The symptom in the log:

```
Klytos: v1.x owner migration failed — this install has no owner record, so every
permission check will deny until one exists. Underlying error: …
```

So the missing piece is the **email**, not the identity. This command supplies it and then runs the
product's own migration.

## Why it does not take a username or a password

Because they would not work, and this is the load-bearing detail.

When this command was designed, `Auth::login()` — the admin panel's actual gate — validated the
username against **`config['admin_user']`** and the password against **`config['admin_pass_hash']`**,
never against the user record (**NEW-11**). An owner minted with its own username and its own
freshly-hashed password was therefore a record **nobody could log in as** — and, because
`findOwner()` would then return non-null, the command would refuse to run ever again, leaving the
install permanently unrecoverable through the product. That is exactly what the first implementation
did, and what its review caught (**L-024**).

**Sprint 5 (D-056) removed that trap and the design still stands.** The gate now reads the record, so
a `--username`/`--password` variant would no longer mint an unusable owner. It is still not built, for
the reason recorded in D-055: writing `config['admin_pass_hash']` from the CLI is a password-reset
primitive on any install, which belongs in a separately named command rather than hidden inside
"repair". What this command does — supply the missing email and let the product's own migration
rebuild the record from credentials that already work — needs no second code path that creates an
owner, which is the other reason it was chosen.

The first version of this command did exactly that. It was caught by the slice's own code review
before it shipped, and the design changed in response.

## Behaviour

| Situation | Result | Exit code |
|---|---|---|
| Owner record missing, config credentials intact, valid `--email` | Owner restored from config; existing password still applies | 0 |
| An owner already exists | Refused, naming the existing owner; nothing changed | 1 |
| `--email` missing or not a valid address | Refused with the usage line; nothing changed | 1 |
| `config['admin_user']` or `config['admin_pass_hash']` is gone | Refused — there is nothing left to restore *from*; nothing changed | 1 |

**Refusals exit non-zero.** A recovery command that exited 0 on "nothing was done" would tell an
automated recovery script that a repair which changed nothing had succeeded.

The last row matters: rather than creating an account nobody could use, the command refuses and says
so. An install in that state cannot be recovered by this command at all.

## Why it is a CLI command and not an admin page

Recovery has to work **with no session**, which rules out the admin panel by construction — a login
cannot succeed while the state exists.

`TerminalExecutor::dispatch()` performs no permission check, and `installer/cli.php` calls it
directly. That is deliberate rather than an oversight: reaching the CLI already means holding
filesystem access to the installation, which is strictly more power than any account has. The
`users.manage` permission the command declares is what gates it in the **web** terminal, where a
session does exist. Note the consequence: because being logged in presupposes an owner, the web
terminal can only ever reach this command's *refusal* branches.

## What it does not do

- **It does not change or reset any password.** If the config password is also lost, this command
  cannot help; it refuses rather than pretending.
- **It only ever restores the OWNER.** It rebuilds one record, from the one credential pair config
  carries. Other accounts are untouched — they are not what this command is for, and since **D-056**
  they log in on their own records anyway. *(This bullet used to say non-owner accounts could not log
  in at all, which was NEW-11 and is closed.)*
- **It is not atomic against a concurrent run.** `findOwner()` and the migration's write are separate
  steps. Two simultaneous invocations against the same ownerless install could race. Same trust
  boundary as the filesystem access the CLI already assumes; noted rather than papered over.

## Verifying it worked

```bash
php installer/cli.php users
```

The restored account appears with the `owner` role. Then **log in to the admin panel with your
existing password** — that is the check that matters, and it is the one the automated test performs
through `Auth::login()` rather than through the user manager.
