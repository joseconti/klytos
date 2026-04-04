---
name: klytos-time
description: Guide for working with dates, times, and timezones in Klytos CMS. Use when formatting dates, converting timezones, scheduling actions with timestamps, displaying local time, working with UTC storage, building timezone selectors, or using any klytos_date/klytos_gmdate/klytos_timezone functions.
---

# Klytos Time & Timezone API

## Philosophy

**Store in UTC, display in local.**

- All internal timestamps are stored as ISO 8601 UTC strings.
- The site's timezone is an IANA string (e.g. `Europe/Madrid`) — DST is handled automatically.
- All functions use `DateTimeImmutable` — no accidental mutation.
- No legacy offset system, no hybrid timestamps.

## Files

| File | Purpose |
|------|---------|
| `installer/core/timezone-cache.php` | Internal cache class for the resolved DateTimeZone |
| `installer/core/helpers-time.php` | All `klytos_*` time functions |
| `installer/core/helpers.php` | `Helpers::now()` delegates to `klytos_now_utc()` |

Both are loaded in `app.php` boot sequence, before plugins.

---

## Timezone Resolution

### Get the site's timezone

```php
// As DateTimeZone object (cached per-request)
$tz = klytos_timezone();
// → DateTimeZone('Europe/Madrid')

// As IANA string
$tzString = klytos_timezone_string();
// → 'Europe/Madrid'

// Current UTC offset in seconds (DST-aware)
$offset = klytos_timezone_offset();
// → 7200 (UTC+2 in summer)
```

The timezone is read from `klytos_config('timezone')`. Falls back to `'UTC'` if missing or invalid.

### After changing timezone config

```php
// Reset the in-process cache so new value is picked up
klytos_timezone_reset_cache();
```

---

## Current Time

### For storage (always UTC)

```php
// ISO 8601 UTC — use this for ALL stored timestamps
$now = klytos_now_utc();
// → '2026-04-04T17:20:00+00:00'
```

### For display (local timezone)

```php
$nowLocal = klytos_now_local();
// → '2026-04-04T19:20:00+02:00' (Europe/Madrid in summer)
```

### Unix timestamp (filterable)

```php
$ts = klytos_time();
// → 1775063400

// Plugins can override the clock for testing:
klytos_add_filter('time.now', fn() => strtotime('2026-01-01'));
```

---

## Formatting Dates

### UTC formatting (for IDs, filenames, internal use)

```php
// Format current time in UTC
$date = klytos_gmdate( 'Y-m-d' );
// → '2026-04-04'

$id = klytos_gmdate( 'Ymd-His' );
// → '20260404-172000'

// Format a specific Unix timestamp in UTC
$formatted = klytos_gmdate( 'Y-m-d H:i:s', $timestamp );
```

**Use `klytos_gmdate()` instead of bare `date()` or `gmdate()`.**

### Local formatting (for user-facing display)

```php
// Format current time in site's timezone
$localDate = klytos_date( 'Y-m-d H:i:s' );
// → '2026-04-04 19:20:00'

// Format a specific Unix timestamp in local time
$localFormatted = klytos_date( 'Y-m-d H:i', $timestamp );
```

### Format an ISO 8601 string for display

```php
// Converts UTC ISO string to local formatted display
$display = klytos_format_datetime( '2026-04-04T17:20:00+00:00', 'Y-m-d H:i' );
// → '2026-04-04 19:20' (in Europe/Madrid)

// Default format is 'Y-m-d H:i:s'
$display = klytos_format_datetime( $page['created_at'] );
```

---

## Timezone Conversions

### UTC to local

```php
$local = klytos_utc_to_local( '2026-04-04T17:20:00+00:00' );
// → '2026-04-04T19:20:00+02:00'

// With custom output format
$local = klytos_utc_to_local( '2026-04-04T17:20:00+00:00', 'Y-m-d H:i:s' );
// → '2026-04-04 19:20:00'
```

### Local to UTC

```php
$utc = klytos_local_to_utc( '2026-04-04 19:20:00' );
// → '2026-04-04T17:20:00+00:00'
```

---

## Unix Timestamp Helpers

### ISO string to timestamp

```php
$ts = klytos_datetime_to_timestamp( '2026-04-04T17:20:00+00:00' );
// → 1775063400
```

### Timestamp to ISO string

```php
$iso = klytos_timestamp_to_datetime( 1775063400 );
// → '2026-04-04T17:20:00+00:00'
```

---

## Timezone Listing (Admin UI)

For building timezone selectors:

```php
$timezones = klytos_timezone_list();
// Returns grouped array:
// [
//   'Africa'  => [
//     ['id' => 'Africa/Abidjan', 'label' => 'Abidjan (UTC+00:00)', 'offset' => '+00:00'],
//     ...
//   ],
//   'America' => [...],
//   'Europe'  => [
//     ['id' => 'Europe/Madrid', 'label' => 'Madrid (UTC+02:00)', 'offset' => '+02:00'],
//     ...
//   ],
//   'UTC' => [...]
// ]
```

Continents are sorted alphabetically with UTC last. Entries within each group are sorted alphabetically. Offsets reflect the current DST state.

Filterable via `time.timezone_list`.

---

## Common Patterns

### Storing a timestamp

```php
$record = [
    'created_at' => klytos_now_utc(),   // Always UTC
    'updated_at' => klytos_now_utc(),
];
```

### Scheduling an action for a future time

```php
// "Run in 1 hour" — use Unix timestamps
$timestamp = klytos_time() + 3600;
klytos_schedule_single_action( $timestamp, 'my.hook' );

// The scheduler stores it as UTC ISO:
// klytos_timestamp_to_datetime( $timestamp )
```

### Displaying a stored timestamp to the user

```php
$page = klytos_get_page( 'about' );
$displayDate = klytos_format_datetime( $page['created_at'], 'd/m/Y H:i' );
// → '04/04/2026 19:20' (local time)
```

### Computing a cutoff date for pruning

```php
$cutoff = klytos_gmdate( 'c', strtotime("-{$retentionDays} days") );
```

### Generating unique IDs with timestamps

```php
$entryId = klytos_gmdate( 'Ymd-His' ) . '-' . Helpers::randomHex(4);
// → '20260404-172000-a1b2c3d4'
```

---

## Rules

1. **NEVER use bare `date()` or `gmdate()`** — always use `klytos_gmdate()` or `klytos_date()`.
2. **NEVER use `date('c', $ts)`** — use `klytos_timestamp_to_datetime( $ts )`.
3. **Store in UTC** — use `klytos_now_utc()` for all persisted timestamps.
4. **Display in local** — use `klytos_format_datetime()`, `klytos_date()`, or `klytos_utc_to_local()`.
5. **IANA only** — timezones are always full IANA identifiers (e.g. `Europe/Madrid`), never manual offsets.
6. **DST is automatic** — selecting a city handles DST transitions transparently.

## Hooks

| Hook | Type | Purpose |
|------|------|---------|
| `time.now` | Filter | Override the Unix timestamp returned by `klytos_time()` |
| `time.timezone_list` | Filter | Customize the timezone list for admin UI |

## Function Reference

| Function | Returns | Purpose |
|----------|---------|---------|
| `klytos_timezone()` | `DateTimeZone` | Site's timezone object (cached) |
| `klytos_timezone_string()` | `string` | IANA timezone string |
| `klytos_timezone_offset()` | `int` | UTC offset in seconds (DST-aware) |
| `klytos_timezone_reset_cache()` | `void` | Clear timezone cache after config change |
| `klytos_now_utc()` | `string` | Current UTC time as ISO 8601 |
| `klytos_now_local()` | `string` | Current local time as ISO 8601 |
| `klytos_time()` | `int` | Current Unix timestamp (filterable) |
| `klytos_gmdate( $fmt, $ts )` | `string` | Format in UTC |
| `klytos_date( $fmt, $ts )` | `string` | Format in local timezone |
| `klytos_format_datetime( $iso, $fmt )` | `string` | Format ISO string for local display |
| `klytos_utc_to_local( $utc, $fmt )` | `string` | Convert UTC string to local |
| `klytos_local_to_utc( $local, $fmt )` | `string` | Convert local string to UTC |
| `klytos_datetime_to_timestamp( $dt )` | `int` | ISO/MySQL string to Unix timestamp |
| `klytos_timestamp_to_datetime( $ts )` | `string` | Unix timestamp to ISO 8601 UTC |
| `klytos_timezone_list()` | `array` | Grouped IANA timezones for UI selectors |
