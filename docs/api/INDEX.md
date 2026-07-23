# API Index — Klytos CMS
> One line per public surface. Grep here FIRST; open the full doc only on a hit.
> Full per-surface docs are backfilled progressively (adoption rule): a surface gets its
> complete doc in docs/api/ or docs/reference/ the first time a slice touches it.
> Generated as-built on 2026-07-18 by mechanical extraction. Unverified inference is labelled.

## Summary
| Kind | Count |
|------|-------|
| Global helper functions | 146 |
| Classes and interfaces | 100 |
| Actions | 307 |
| Filters | 117 |
| MCP tools | 206 |
| HTTP routes | 34 |
| Terminal / CLI commands | 26 |
| Plugin extension contracts | 19 |
| **Total** | **955** |

Scope: everything under `installer/` except `installer/vendor-ai/` and
`installer/admin/assets/vendor/`, which are third-party and excluded.

## Global helper functions
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| klytos_add_action() | function | installer/core/helpers-global.php | — | Register a callback on an action hook |
| klytos_add_filter() | function | installer/core/helpers-global.php | — | Register a callback on a filter hook |
| klytos_add_notice() | function | installer/core/helpers-global.php | — | Queue a one-shot flash message for the admin UI, surviving a redirect |
| klytos_add_persistent_notice() | function | installer/core/helpers-global.php | — | Store an admin notice by ID that persists across page loads and sessions |
| klytos_add_shortcode() | function | installer/core/helpers-global.php | — | Bind a shortcode tag to a render callback |
| klytos_admin_url() | function | installer/core/helpers-global.php | — | Build an absolute URL into the admin panel from a relative path |
| klytos_admin_gate_key() | function | installer/core/admin-gate.php | docs/reference/authorization.md | Resolve the running admin script to its gate-map key, or null when it is outside admin/ |
| klytos_admin_gate_map() | function | installer/core/admin-gate.php | docs/reference/authorization.md | The capability required by every admin surface; an absent entry means denied |
| klytos_app() | function | installer/core/helpers-global.php | — | Return the App singleton that owns all core services |
| klytos_apply_filters() | function | installer/core/helpers-global.php | — | Pass a value through every callback registered on a filter and return the result |
| klytos_auth() | function | installer/core/helpers-global.php | — | Return the authentication manager used for login and permission checks |
| klytos_cache() | function | installer/core/helpers-global.php | — | Return the CacheManager service instance |
| klytos_cache_delete() | function | installer/core/helpers-global.php | — | Remove a single entry from the cache by key |
| klytos_cache_flush() | function | installer/core/helpers-global.php | — | Empty the whole cache store, dropping every group's entries |
| klytos_cache_flush_all() | function | installer/core/helpers-global.php | — | Empty every cache group and fire the cache.all_flushed action |
| klytos_cache_flush_group() | function | installer/core/helpers-global.php | — | Drop only the cached entries whose key starts with the given group prefix |
| klytos_cache_get() | function | installer/core/helpers-global.php | — | Read a cached value by key, falling back to a default when absent |
| klytos_cache_remember() | function | installer/core/helpers-global.php | — | Return a cached value or compute it once, store it, and return it |
| klytos_cache_set() | function | installer/core/helpers-global.php | — | Store a value under a cache key with an optional time-to-live |
| klytos_cache_stats() | function | installer/core/helpers-global.php | — | Return hit/miss counters and store metrics for diagnostics |
| klytos_cancel_scheduled_action() | function | installer/core/helpers-global.php | — | Remove a pending scheduled action identified by its ID |
| klytos_config() | function | installer/core/helpers-global.php | — | Read a configuration value addressed by dot-notation path |
| klytos_csrf_field() | function | installer/core/helpers-security.php | — | Build a ready-to-echo hidden input carrying the current CSRF token |
| klytos_current_admin_page() | function | installer/core/helpers-global.php | — | Return the identifier of the admin screen handling this request |
| klytos_current_surface() | function | installer/core/helpers-global.php | docs/reference/authorization.md | Report whether this request expects an api, mcp, cli or page response shape |
| klytos_current_user() | function | installer/core/helpers-global.php | — | Return the logged-in user's data array, or null when nobody is authenticated |
| klytos_date() | function | installer/core/helpers-time.php | — | Render a Unix timestamp in the site's configured timezone |
| klytos_datetime_to_timestamp() | function | installer/core/helpers-time.php | — | Parse an ISO 8601 or MySQL datetime string into a Unix timestamp |
| klytos_delete_meta() | function | installer/core/helpers-global.php | — | Remove one metadata key stored against an entity |
| klytos_delete_option() | function | installer/core/helpers-global.php | — | Remove a stored option and its value |
| klytos_delete_options_by_domain() | function | installer/core/helpers-global.php | — | Purge every option owned by a text domain and return how many were removed |
| klytos_delete_transient() | function | installer/core/helpers-global.php | — | Remove a transient before its expiry |
| klytos_deny() | function | installer/core/helpers-global.php | docs/reference/authorization.md | Refuse the current request with 401 or 403 in the shape its caller can parse, and stop |
| klytos_did_action() | function | installer/core/helpers-global.php | — | Return how many times an action has fired during this request |
| klytos_die() | function | installer/core/helpers-security.php | — | Abort the request and render a safe error page, after a plugin-overridable hook |
| klytos_dismiss_notice() | function | installer/core/helpers-global.php | — | Mark a persistent notice as hidden for the current user session |
| klytos_do_action() | function | installer/core/helpers-global.php | — | Fire an action hook, running every callback bound to it |
| klytos_do_shortcode() | function | installer/core/helpers-global.php | — | Expand every registered shortcode found in a string of content |
| klytos_enforce_admin_gate() | function | installer/core/admin-gate.php | docs/reference/authorization.md | The central default-deny admin gate, called once from admin/bootstrap.php |
| klytos_esc_attr() | function | installer/core/helpers-security.php | — | Escape text for an HTML attribute, also stripping tabs and newlines |
| klytos_esc_html() | function | installer/core/helpers-security.php | — | Escape dynamic text before echoing it into HTML body content |
| klytos_esc_js() | function | installer/core/helpers-security.php | — | Escape text for embedding inside a JavaScript string literal |
| klytos_esc_textarea() | function | installer/core/helpers-security.php | — | Escape text so it renders literally inside a textarea element |
| klytos_esc_url() | function | installer/core/helpers-security.php | — | Validate a URL against a protocol allowlist and escape it for href/src |
| klytos_format_datetime() | function | installer/core/helpers-time.php | — | Convert a stored UTC datetime string to local time and format it for display |
| klytos_forms() | function | installer/plugins/klytos-forms/klytos-forms.php | — | Return the FormManager service from the Klytos Forms plugin |
| klytos_get_all_meta() | function | installer/core/helpers-global.php | — | Return every metadata key/value pair stored for one entity |
| klytos_get_avatar_url() | function | installer/core/helpers-global.php | — | Resolve a user's avatar image, falling back to Gravatar when none is set |
| klytos_get_dashboard_widgets() | function | installer/core/helpers-global.php | — | Return every registered dashboard widget ordered by position |
| klytos_get_fired_actions() | function | installer/core/helpers-global.php | — | Return the list of action hooks that have fired during this request |
| klytos_get_meta() | function | installer/core/helpers-global.php | — | Read one metadata value stored against an entity |
| klytos_get_notices() | function | installer/core/helpers-global.php | — | Collect the admin notices that should be rendered on the given admin screen |
| klytos_get_option() | function | installer/core/helpers-global.php | — | Read a stored option, returning a default when it is missing |
| klytos_get_option_sensitivity() | function | installer/core/helpers-global.php | — | Report the data-sensitivity classification declared for an option |
| klytos_get_options_by_domain() | function | installer/core/helpers-global.php | — | Return every option registered under a given text domain |
| klytos_get_plugin_data() | function | installer/core/helpers-global.php | — | Return a plugin's merged manifest from its PHP header and klytos-plugin.json |
| klytos_get_registered_hooks() | function | installer/core/helpers-global.php | — | List every registered hook with its callback count, for introspection |
| klytos_get_transient() | function | installer/core/helpers-global.php | — | Read a transient value, or nothing once it has expired |
| klytos_gmdate() | function | installer/core/helpers-time.php | — | Render a Unix timestamp as UTC regardless of PHP's default timezone |
| klytos_gravatar_url() | function | installer/core/helpers-global.php | — | Build the Gravatar image URL for an email address at a given size |
| klytos_has_action() | function | installer/core/helpers-global.php | — | Report whether any callback is bound to an action hook |
| klytos_has_filter() | function | installer/core/helpers-global.php | — | Report whether any callback is bound to a filter hook |
| klytos_has_permission() | function | installer/core/helpers-global.php | — | Test the current user against a capability, extendable via auth.capabilities |
| klytos_http() | function | installer/core/helpers-global.php | — | Return the HttpClient service for outbound requests |
| klytos_http_get() | function | installer/core/helpers-global.php | — | Fetch a URL over HTTP GET and return the response array |
| klytos_http_post() | function | installer/core/helpers-global.php | — | Send a body to a URL over HTTP POST and return the response array |
| klytos_importer() | function | installer/plugins/klytos-importer/klytos-importer.php | — | Return the ImportSession service from the Klytos Importer plugin |
| klytos_integrity_check() | function | installer/core/helpers-global.php | — | Verify file hashes for core and every plugin, optionally bypassing the cache |
| klytos_integrity_status() | function | installer/core/helpers-global.php | — | Return the stored report from the last integrity verification run |
| klytos_is_admin() | function | installer/core/helpers-global.php | — | Report whether this request is being served by the admin panel |
| klytos_is_admin_page() | function | installer/core/helpers-global.php | — | Match the current admin screen against an identifier or a prefix pattern |
| klytos_is_cli() | function | installer/core/helpers-global.php | — | Report whether Klytos is executing from the command line |
| klytos_is_email() | function | installer/core/helpers-security.php | — | Validate that a string is a well-formed email address |
| klytos_is_maintenance_mode() | function | installer/core/helpers-global.php | — | Report whether the site is currently serving the maintenance page |
| klytos_is_mcp() | function | installer/core/helpers-global.php | — | Report whether this request came in through the MCP API |
| klytos_is_scheduled_action() | function | installer/core/helpers-global.php | — | Report whether a matching action is already pending in the scheduler |
| klytos_is_url() | function | installer/core/helpers-security.php | — | Validate that a string is a well-formed http or https URL |
| klytos_kses() | function | installer/core/helpers-security.php | — | Strip HTML down to a caller-supplied tag and attribute allowlist via DOMDocument |
| klytos_kses_post() | function | installer/core/helpers-security.php | — | Strip HTML to the default post-content allowlist, dropping script, style and iframe |
| klytos_local_to_utc() | function | installer/core/helpers-time.php | — | Convert a datetime expressed in site time into UTC for storage |
| klytos_log() | function | installer/core/helpers-global.php | — | Write a levelled entry to the Klytos debug log in the secret logs directory |
| klytos_log_alert() | function | installer/core/helpers-global.php | — | Write an alert-level entry to the debug log |
| klytos_log_critical() | function | installer/core/helpers-global.php | — | Write a critical-level entry to the debug log |
| klytos_log_debug() | function | installer/core/helpers-global.php | — | Write a debug-level entry to the debug log |
| klytos_log_emergency() | function | installer/core/helpers-global.php | — | Write an emergency-level entry to the debug log |
| klytos_log_error() | function | installer/core/helpers-global.php | — | Write an error-level entry to the debug log |
| klytos_log_info() | function | installer/core/helpers-global.php | — | Write an info-level entry to the debug log |
| klytos_log_notice() | function | installer/core/helpers-global.php | — | Write a notice-level entry to the debug log |
| klytos_log_warning() | function | installer/core/helpers-global.php | — | Write a warning-level entry to the debug log |
| klytos_mcp_tool_capabilities() | function | installer/core/mcp/tool-capabilities.php | docs/reference/mcp-authorization.md | The MCP tool→capability map for the authorization gate; an absent entry means denied |
| klytos_next_scheduled_action() | function | installer/core/helpers-global.php | — | Return when the next pending action for a hook is due, as a Unix timestamp |
| klytos_now_local() | function | installer/core/helpers-time.php | — | Return the current moment in site time as an ISO 8601 string, for display only |
| klytos_now_utc() | function | installer/core/helpers-time.php | — | Return the current moment in UTC as ISO 8601, the canonical form for storage |
| klytos_option_exists() | function | installer/core/helpers-global.php | — | Report whether an option key is present in storage |
| klytos_plugin_path() | function | installer/core/helpers-global.php | — | Resolve an absolute filesystem path inside a plugin's directory |
| klytos_plugin_url() | function | installer/core/helpers-global.php | — | Resolve a public URL into a plugin's assets directory |
| klytos_register_admin_page() | function | installer/core/helpers-global.php | — | Add a sidebar menu entry and wire it to a plugin-supplied admin screen |
| klytos_register_dashboard_widget() | function | installer/core/helpers-global.php | — | Add a widget to the admin dashboard with a position and capability gate |
| klytos_register_option() | function | installer/core/helpers-global.php | — | Declare an option and its data-sensitivity classification for privacy tooling |
| klytos_register_route() | function | installer/core/helpers-global.php | — | Map a URL pattern to a plugin callback that renders the response |
| klytos_require_permission() | function | installer/core/helpers-global.php | docs/reference/authorization.md | Require a capability or refuse and stop; the enforcing counterpart to klytos_has_permission |
| klytos_register_template_part() | function | installer/core/helpers-global.php | — | Hook a callback that supplies or rewrites the markup of a template part |
| klytos_register_templates() | function | installer/core/helpers-global.php | — | Make a plugin's page templates available to the template resolver |
| klytos_register_translations() | function | installer/core/helpers-global.php | — | Point the translation loader at a plugin's language files directory |
| klytos_remove_action() | function | installer/core/helpers-global.php | — | Unbind one specific callback from an action hook |
| klytos_remove_all_actions() | function | installer/core/helpers-global.php | — | Unbind every callback from an action hook, including other plugins' |
| klytos_remove_all_filters() | function | installer/core/helpers-global.php | — | Unbind every callback from a filter hook, including other plugins' |
| klytos_remove_filter() | function | installer/core/helpers-global.php | — | Unbind one specific callback from a filter hook |
| klytos_render_form() | function | installer/plugins/klytos-forms/klytos-forms.php | — | Render a Klytos Forms form to HTML by its ID |
| klytos_safe_http() | function | installer/core/helpers-global.php | docs/reference/safe-http.md | Return the SafeHttp fetcher for URLs an untrusted party influenced |
| klytos_sanitize_email() | function | installer/core/helpers-security.php | — | Clean an email address, yielding an empty string when it is invalid |
| klytos_sanitize_filename() | function | installer/core/helpers-security.php | — | Strip directory parts and unsafe characters from a filename before writing it |
| klytos_sanitize_float() | function | installer/core/helpers-security.php | — | Coerce an arbitrary input value into a float |
| klytos_sanitize_html() | function | installer/core/helpers-security.php | — | Clean HTML against the default allowlist, dropping dangerous tags and attributes |
| klytos_sanitize_int() | function | installer/core/helpers-security.php | — | Coerce an arbitrary input value into an integer |
| klytos_sanitize_key() | function | installer/core/helpers-security.php | — | Reduce a string to lowercase alphanumerics, dashes and underscores for use as an ID |
| klytos_sanitize_text() | function | installer/core/helpers-security.php | — | Strip tags and NULL bytes from an input field, then normalize and trim whitespace |
| klytos_sanitize_title() | function | installer/core/helpers-security.php | — | Normalize a string into a safe display title or slug |
| klytos_sanitize_url() | function | installer/core/helpers-security.php | — | Clean a URL for storage, rejecting dangerous protocols |
| klytos_schedule_recurring_action() | function | installer/core/helpers-global.php | — | Queue an action to run repeatedly from a start time at a fixed interval |
| klytos_schedule_single_action() | function | installer/core/helpers-global.php | — | Queue an action to run once at a given timestamp |
| klytos_set_config() | function | installer/core/helpers-global.php | — | Write a configuration value back to the main config file |
| klytos_set_meta() | function | installer/core/helpers-global.php | — | Create or replace one JSON-serialisable metadata value on an entity |
| klytos_set_option() | function | installer/core/helpers-global.php | — | Create or update a stored option value |
| klytos_set_option_for() | function | installer/core/helpers-global.php | — | Create or update an option attributed to an explicit text domain |
| klytos_set_profiler() | function | installer/core/helpers-global.php | — | Install a closure that times hook execution for the developer-mode DevBar |
| klytos_set_transient() | function | installer/core/helpers-global.php | — | Store a value that expires automatically after the given TTL |
| klytos_shortcode_exists() | function | installer/core/helpers-global.php | — | Report whether a shortcode tag has been registered |
| klytos_storage() | function | installer/core/helpers-global.php | — | Return the active storage backend, either file-based or database-backed |
| klytos_time() | function | installer/core/helpers-time.php | — | Return the current Unix timestamp through a filter plugins can override |
| klytos_timestamp_to_datetime() | function | installer/core/helpers-time.php | — | Convert a Unix timestamp into an ISO 8601 UTC datetime string |
| klytos_timezone() | function | installer/core/helpers-time.php | — | Return the site's configured timezone as a DateTimeZone object |
| klytos_timezone_list() | function | installer/core/helpers-time.php | — | Return all IANA timezones grouped by continent for building a select control |
| klytos_timezone_offset() | function | installer/core/helpers-time.php | — | Return the site's current UTC offset in seconds, accounting for DST |
| klytos_timezone_reset_cache() | function | installer/core/helpers-time.php | — | Discard the memoized timezone object after the timezone config changes |
| klytos_timezone_string() | function | installer/core/helpers-time.php | — | Return the site's IANA timezone name, defaulting to UTC when unset |
| klytos_unregister_dashboard_widget() | function | installer/core/helpers-global.php | — | Remove a previously registered dashboard widget by ID |
| klytos_unschedule_all_actions() | function | installer/core/helpers-global.php | — | Cancel every pending action matching a hook and return how many were removed |
| klytos_url() | function | installer/core/helpers-global.php | — | Build an absolute URL from a path relative to the site root |
| klytos_utc_to_local() | function | installer/core/helpers-time.php | — | Convert a stored UTC datetime into the site's local timezone |
| klytos_verify_csrf() | function | installer/core/helpers-security.php | — | Validate the request's CSRF token from POST body, header, or query string |
| klytos_version() | function | installer/core/helpers-global.php | — | Return the running Klytos version string |
| klytos_x402_config() | function | installer/core/x402-bootstrap.php | — | Return the configuration service for the x402 micropayments module |
| klytos_x402_log() | function | installer/core/x402-bootstrap.php | — | Return the x402 transaction log service recording micropayment activity |
| klytos_x402_providers() | function | installer/core/x402-bootstrap.php | — | Return the registry of x402 payment providers registered by plugins |
| klytos_x402_stats() | function | installer/core/x402-bootstrap.php | — | Return the x402 statistics service that aggregates the transaction log |

## Classes and interfaces
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| `Klytos\Core\ActionScheduler` | class | installer/core/action-scheduler.php | — | Schedules, queues, retries and prunes single and recurring background actions |
| `Klytos\Core\Ai\AiKeyManager` | class | installer/core/ai/ai-key-manager.php | — | Stores and selects per-provider AI API keys and their default models |
| `Klytos\Core\Ai\ChatEngine` | class | installer/core/ai/chat-engine.php | — | Runs AI chat loops via the php-llm SDK, executing MCP tools between turns |
| `Klytos\Core\Ai\ChatManager` | class | installer/core/ai/chat-manager.php | — | Persists chat conversations, their messages, token usage and search |
| `Klytos\Core\Ai\ChatResult` | class | installer/core/ai/chat-engine.php | — | Value object holding the outcome of a chat across AI and tool iterations |
| `Klytos\Core\AiImageGenerator` | class | installer/core/ai-image-generator.php | — | Generates images through configured AI providers and keeps a generation history |
| `Klytos\Core\AnalyticsManager` | class | installer/core/analytics-manager.php | — | Records page views and reports traffic summaries and top pages |
| `Klytos\Core\App` | class | installer/core/app.php | — | Singleton application container that boots the CMS and exposes every core service |
| `Klytos\Core\AssetManager` | class | installer/core/asset-manager.php | — | Manages media uploads, categories, image editing and asset usage tracking |
| `Klytos\Core\AuditLog` | class | installer/core/audit-log.php | — | Records and queries security-relevant admin activity, pruning old entries |
| `Klytos\Core\Auth` | class | installer/core/auth.php | docs/reference/security-headers.md | Handles login sessions, CSRF tokens, bearer tokens and application passwords; also decides the response security headers (`sendSecurityHeaders()` / `buildSecurityHeaders()`) and resolves MCP bearer-token roles (`createBearerToken()` role, `getBearerTokenActor()`, `migrateCredentialRoles()` — see docs/reference/mcp-authorization.md) |
| `Klytos\Core\BlockManager` | class | installer/core/block-manager.php | — | Stores, renders and manages reusable blocks, their slots and global block data |
| `Klytos\Core\BuildEngine` | class | installer/core/build-engine.php | — | Renders pages and generates the static HTML, CSS and JS output of the site |
| `Klytos\Core\Cache\ApcuCache` | class | installer/core/cache/apcu-cache.php | — | Cache driver backed by APCu shared memory |
| `Klytos\Core\Cache\FileCache` | class | installer/core/cache/file-cache.php | — | Cache driver persisting entries as files on disk |
| `Klytos\Core\Cache\MemcachedCache` | class | installer/core/cache/memcached-cache.php | — | Cache driver backed by a Memcached server |
| `Klytos\Core\Cache\NullCache` | class | installer/core/cache/null-cache.php | — | No-op cache driver used when caching is disabled |
| `Klytos\Core\Cache\RedisCache` | class | installer/core/cache/redis-cache.php | — | Cache driver backed by a Redis server |
| `Klytos\Core\CacheInterface` | interface | installer/core/cache-interface.php | — | Contract every cache driver implements for get/set/flush and driver stats |
| `Klytos\Core\CacheManager` | class | installer/core/cache-manager.php | — | Front end to the configured cache driver, adding groups, remember and counters |
| `Klytos\Core\CommentManager` | class | installer/core/comment-manager.php | docs/reference/public-comments.md | Stores, moderates and renders page comments, including threaded output |
| `Klytos\Core\ConsentManager` | class | installer/core/consent-manager.php | — | Holds GDPR cookie-consent config, plugin declarations and audit reports |
| `Klytos\Core\CronManager` | class | installer/core/cron-manager.php | — | Runs the scheduled tasks that are due on each cron tick |
| `Klytos\Core\DatabaseStorage` | class | installer/core/database-storage.php | — | PDO-backed storage driver persisting encrypted records in database tables |
| `Klytos\Core\DevBar` | class | installer/core/dev-bar.php | — | Collects request profiling data (queries, hooks, memory) for the developer bar |
| `Klytos\Core\Encryption` | class | installer/core/encryption.php | — | Encrypts and decrypts stored data and manages encryption and RSA identity keys |
| `Klytos\Core\EncryptionLevelTrait` | trait | installer/core/encryption-level-trait.php | — | Shared per-record encryption-level decisions reused by storage drivers |
| `Klytos\Core\ExportManager` | class | installer/core/export-manager.php | — | Exports site content to JSON, CSV and WXR files |
| `Klytos\Core\FileStorage` | class | installer/core/file-storage.php | — | Filesystem storage driver reading and writing encrypted records as files |
| `Klytos\Core\Helpers` | class | installer/core/helpers.php | docs/reference/security-headers.md | Static utilities for URLs, slugs, sanitization, tokens and environment checks; `isHttps()` is the single TLS check (see the reference doc) |
| `Klytos\Core\Hooks` | class | installer/core/hooks.php | — | Action and filter registry that dispatches the CMS extensibility events |
| `Klytos\Core\HtmlToMarkdown` | class | installer/core/html-to-markdown.php | — | Converts HTML fragments into Markdown text |
| `Klytos\Core\HttpClient` | class | installer/core/http-client.php | — | Performs outbound HTTP requests on behalf of core and plugins |
| `Klytos\Core\I18n` | class | installer/core/i18n.php | — | Loads locale files and resolves translated interface strings |
| `Klytos\Core\IntegrityChecker` | class | installer/core/integrity-checker.php | — | Verifies core and plugin file hashes against signed manifests |
| `Klytos\Core\License` | class | installer/core/license.php | — | Activates and periodically verifies the installation license |
| `Klytos\Core\Logger` | class | installer/core/logger.php | — | Writes, reads, searches and deletes per-plugin log files |
| `Klytos\Core\Mailer` | class | installer/core/mailer.php | — | Sends plain-text, HTML and button-templated emails from the CMS |
| `Klytos\Core\MCP\JsonRpc` | class | installer/core/mcp/json-rpc.php | — | Parses JSON-RPC requests and builds the success and error response envelopes |
| `Klytos\Core\MCP\OAuthServer` | class | installer/core/mcp/oauth-server.php | — | OAuth client registration, authorization and access-token issuing for MCP |
| `Klytos\Core\MCP\PermissionDeniedException` | class | installer/core/mcp/permission-denied-exception.php | docs/reference/mcp-authorization.md | Thrown by ToolRegistry::call() when the authorization gate refuses a tool; each transport catches and shapes it |
| `Klytos\Core\MCP\RateLimiter` | class | installer/core/mcp/rate-limiter.php | — | Throttles MCP requests per client and blocks repeated auth failures |
| `Klytos\Core\MCP\Server` | class | installer/core/mcp/server.php | — | HTTP entry point handling the MCP GET and POST transport requests |
| `Klytos\Core\MCP\TokenAuth` | class | installer/core/mcp/token-auth.php | docs/reference/mcp-authorization.md | Authenticates MCP requests (bearer, OAuth, app password) and resolves the caller's actor {user_id, role} for the gate |
| `Klytos\Core\MCP\ToolNotFoundException` | class | installer/core/mcp/tool-not-found-exception.php | docs/reference/mcp-authorization.md | Thrown by ToolRegistry::call() for a mapped-but-unhandled tool (post-gate); the transport answers "Unknown tool" without masking other errors (NEW-30, D-050) |
| `Klytos\Core\MCP\ToolRegistrationException` | class | installer/core/mcp/tool-registration-exception.php | docs/reference/mcp-authorization.md | Thrown by the tool loader (registerToolFile) when a listed file is missing or registers no tools — fail-loud (D-049) |
| `Klytos\Core\MCP\ToolRegistry` | class | installer/core/mcp/tool-registry.php | docs/reference/mcp-authorization.md | Registers all MCP tools (loader fails loudly on a dead file — registerToolFile), carries the request actor (setActor), default-denies tool calls at the gate, and treats a mapped filter-injected tool as existing (exists) so it is callable over HTTP |
| `Klytos\Core\MenuManager` | class | installer/core/menu-manager.php | — | Stores navigation menus and their items and renders them as HTML |
| `Klytos\Core\MetaManager` | class | installer/core/meta-manager.php | — | Stores free-form key/value metadata attached to entities |
| `Klytos\Core\NoticeManager` | class | installer/core/notice-manager.php | — | Creates, renders and dismisses admin notices, including transient flash messages |
| `Klytos\Core\OEmbedResolver` | class | installer/core/oembed-resolver.php | — | Resolves external URLs into embeddable oEmbed markup |
| `Klytos\Core\OptionsManager` | class | installer/core/options-manager.php | — | Persists options per text domain with sensitivity-based encryption |
| `Klytos\Core\PageManager` | class | installer/core/page-manager.php | — | CRUD, trash, scheduling, locking, hierarchy and breadcrumbs for pages |
| `Klytos\Core\PageTemplateManager` | class | installer/core/page-template-manager.php | — | Manages page templates, their block lists, approval and preview rendering |
| `Klytos\Core\PartManager` | class | installer/core/part-manager.php | — | Stores, resolves and renders template parts and their data |
| `Klytos\Core\PluginLoader` | class | installer/core/plugin-loader.php | — | Discovers, activates, updates and backs up plugins and loads them at boot |
| `Klytos\Core\PostTypeManager` | class | installer/core/post-type-manager.php | — | Defines custom post types with their taxonomies, terms and custom fields |
| `Klytos\Core\PrivacyManager` | class | installer/core/privacy-manager.php | — | Collects, exports and erases personal data for GDPR subject requests |
| `Klytos\Core\ProfilingStorage` | class | installer/core/profiling-storage.php | — | Storage decorator that records the timing of every storage operation |
| `Klytos\Core\RouteManager` | class | installer/core/route-manager.php | — | Registry matching custom routes contributed by core and plugins |
| `Klytos\Core\Router` | class | installer/core/router.php | — | Dispatches an incoming HTTP request to the matching handler |
| `Klytos\Core\SafeHttp` | class | installer/core/safe-http.php | docs/reference/safe-http.md | Fetches user- and AI-influenced URLs, refusing private addresses and re-validating every redirect hop |
| `Klytos\Core\ShortcodeManager` | class | installer/core/shortcode-manager.php | — | Registers shortcodes and expands them when processing content |
| `Klytos\Core\SiteConfig` | class | installer/core/site-config.php | — | Reads and writes global site configuration values |
| `Klytos\Core\SiteHealthManager` | class | installer/core/site-health-manager.php | — | Runs the site health checks and reports their results |
| `Klytos\Core\Storage` | class | installer/core/storage.php | — | Base storage helper for reading and writing encrypted data files |
| `Klytos\Core\StorageInterface` | interface | installer/core/storage-interface.php | — | Contract all storage drivers implement for read/write/list/search/transaction |
| `Klytos\Core\TaskManager` | class | installer/core/task-manager.php | — | CRUD, listing and completion tracking for editorial tasks |
| `Klytos\Core\TemplateResolver` | class | installer/core/template-resolver.php | — | Resolves template and part names to the file that should render them |
| `Klytos\Core\TerminalExecutor` | class | installer/core/terminal-executor.php | — | Executes web-terminal commands and keeps the command registry and history |
| `Klytos\Core\ThemeManager` | class | installer/core/theme-manager.php | — | Stores theme colors, fonts and layout and generates the CSS variables |
| `Klytos\Core\TranslationManager` | class | installer/core/translation-manager.php | — | Manages translation sources, reference keys and saved per-language strings |
| `Klytos\Core\TwoFactor` | class | installer/core/two-factor.php | — | TOTP, magic-link, passkey and recovery-code second-factor authentication |
| `Klytos\Core\Updater` | class | installer/core/updater.php | — | Checks for, installs and rolls back CMS updates, managing backups |
| `Klytos\Core\UserManager` | class | installer/core/user-manager.php | — | User CRUD, authentication, password resets, permissions and ownership transfer |
| `Klytos\Core\VersionManager` | class | installer/core/version-manager.php | — | Saves, lists, diffs, prunes and restores page revisions |
| `Klytos\Core\WebhookManager` | class | installer/core/webhook-manager.php | — | Manages webhook subscriptions and dispatches events to their endpoints |
| `Klytos\Core\X402\BotDetector` | class | installer/core/x402/bot-detector.php | — | Identifies AI bots and reads x402 payment receipts from incoming requests |
| `Klytos\Core\X402\Config` | class | installer/core/x402/config.php | — | Reads and updates x402 paywall configuration and effective per-page settings |
| `Klytos\Core\X402\Gate` | class | installer/core/x402/gate.php | — | Enforces the x402 paywall on a request before any content is served |
| `Klytos\Core\X402\HtaccessWriter` | class | installer/core/x402/htaccess-writer.php | — | Writes and removes the x402 rewrite rules for Apache and Nginx |
| `Klytos\Core\X402\Providers\PaymentProviderInterface` | interface | installer/core/x402/providers/payment-provider-interface.php | — | Contract x402 payment providers implement to price and verify payments |
| `Klytos\Core\X402\Providers\ProviderRegistry` | class | installer/core/x402/providers/provider-registry.php | — | Registers and resolves the available x402 payment providers |
| `Klytos\Core\X402\Stats` | class | installer/core/x402/stats.php | — | Aggregates x402 revenue, daily totals, top pages and top bots |
| `Klytos\Core\X402\TransactionLog` | class | installer/core/x402/transaction-log.php | — | Records and queries the x402 payment transaction history |
| `KlytosForms\FormConditionalEngine` | class | installer/plugins/klytos-forms/src/FormConditionalEngine.php | — | Evaluates conditional-logic rules that decide field visibility |
| `KlytosForms\FormManager` | class | installer/plugins/klytos-forms/src/FormManager.php | — | CRUD for forms, fields and entries plus submissions, stats and export |
| `KlytosForms\FormRenderer` | class | installer/plugins/klytos-forms/src/FormRenderer.php | — | Renders a form definition as HTML markup |
| `KlytosImporter\ContentExtractor` | class | installer/plugins/klytos-importer/src/ContentExtractor.php | — | Extracts the main article content from a fetched HTML document |
| `KlytosImporter\ContentMapper` | class | installer/plugins/klytos-importer/src/ContentMapper.php | — | Converts imported HTML and shortcodes into Klytos block markup |
| `KlytosImporter\ImportSession` | class | installer/plugins/klytos-importer/src/ImportSession.php | — | Tracks import sessions, their page queue, progress and retries |
| `KlytosImporter\ImportValidator` | class | installer/plugins/klytos-importer/src/ImportValidator.php | — | Validates and sanitizes import URLs, files and session identifiers |
| `KlytosImporter\MediaDownloader` | class | installer/plugins/klytos-importer/src/MediaDownloader.php | — | Downloads remote media and rewrites its URLs to the local assets |
| `KlytosImporter\PageFetcher` | class | installer/plugins/klytos-importer/src/PageFetcher.php | — | Fetches remote pages honoring robots.txt and discovers linked URLs |
| `KlytosImporter\SitemapParser` | class | installer/plugins/klytos-importer/src/SitemapParser.php | — | Parses sitemaps, classifies discovered URLs and suggests slugs |
| `KlytosImporter\StyleAnalyzer` | class | installer/plugins/klytos-importer/src/StyleAnalyzer.php | — | Analyzes a source site's CSS to infer its colors, fonts and layout |
| `KlytosImporter\WPXMLParser` | class | installer/plugins/klytos-importer/src/WPXMLParser.php | — | Parses WordPress WXR exports into analyzable page records |
| `KlytosTimezoneCache` | class | installer/core/timezone-cache.php | — | In-process holder caching resolved timezone objects for the request |
| `KlytosX402Coinbase\CoinbaseCdpProvider` | class | installer/plugins/klytos-x402-coinbase/src/CoinbaseCdpProvider.php | — | x402 payment provider verifying on-chain payments through Coinbase CDP |
| `KlytosX402Stripe\StripeProvider` | class | installer/plugins/klytos-x402-stripe/src/StripeProvider.php | — | x402 payment provider building and verifying payments through Stripe |

## Actions
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| admin.analytics.after | action | installer/admin/analytics.php | — | Emitted at the tail of the analytics screen markup; no payload, echo extra HTML |
| admin.analytics.before | action | installer/admin/analytics.php | — | Emitted at the top of the analytics screen markup; no payload, echo extra HTML |
| admin.assets.after | action | installer/admin/assets.php | — | Emitted at the tail of the media library screen; no payload, echo extra HTML |
| admin.assets.after_toolbar | action | installer/admin/assets.php | — | Emitted just below the media library toolbar so plugins can inject controls; no payload |
| admin.assets.before | action | installer/admin/assets.php | — | Emitted at the top of the media library screen; no payload, echo extra HTML |
| admin.assets.before_toolbar | action | installer/admin/assets.php | — | Emitted just above the media library toolbar so plugins can inject controls; no payload |
| admin.assets.detail_panel_extra | action | installer/admin/assets.php | — | Emitted inside the asset detail panel after the technical info block; no payload |
| admin.banner.recovery_warning | action | installer/admin/templates/sidebar.php | — | Emitted at the top of the admin main area to render the unconfirmed recovery keys banner |
| admin.block_data.after | action | installer/admin/block-data.php | — | Emitted at the tail of the global block data screen; no payload, echo extra HTML |
| admin.block_data.before | action | installer/admin/block-data.php | — | Emitted right after header and sidebar on the global block data screen; no payload |
| admin.blocks.after | action | installer/admin/blocks.php | — | Emitted at the tail of the blocks listing screen; no payload, echo extra HTML |
| admin.blocks.before | action | installer/admin/blocks.php | — | Emitted at the top of the blocks listing screen; no payload, echo extra HTML |
| admin.bulk_action.after | action | installer/core/mcp/tools/bulk-tools.php +1 more | — | Emitted once a bulk page operation finishes; receives the action, processed count and errors |
| admin.bulk_action.before | action | installer/core/mcp/tools/bulk-tools.php +1 more | — | Emitted before a bulk page operation runs; receives the action name and the target slugs |
| admin.consent.after | action | installer/admin/consent.php | — | Emitted at the tail of the cookie consent settings screen; no payload, echo extra HTML |
| admin.consent.before | action | installer/admin/consent.php | — | Emitted at the top of the cookie consent settings screen; no payload, echo extra HTML |
| admin.dashboard.after | action | installer/admin/index.php | — | Emitted at the tail of the dashboard screen; no payload, echo extra HTML |
| admin.dashboard.after_stats | action | installer/admin/index.php | — | Emitted right after the dashboard stat tiles row; no payload, echo extra HTML |
| admin.dashboard.after_widgets | action | installer/admin/index.php | — | Emitted right after the dashboard widget grid is rendered; no payload |
| admin.dashboard.before | action | installer/admin/index.php | — | Emitted at the top of the dashboard screen; no payload, echo extra HTML |
| admin.dashboard.before_stats | action | installer/admin/index.php | — | Emitted just above the dashboard stat tiles row; no payload, echo extra HTML |
| admin.dashboard.before_widgets | action | installer/admin/index.php | — | Emitted just above the dashboard widget grid; no payload, echo extra HTML |
| admin.dashboard.init | action | installer/admin/index.php | — | Emitted before the dashboard renders so plugins can register their widgets; no payload |
| admin.editor.after | action | installer/admin/page-editor.php | — | Emitted at the tail of the page editor screen; no payload, echo extra HTML |
| admin.editor.before | action | installer/admin/page-editor.php | — | Emitted at the top of the page editor screen; no payload, echo extra HTML |
| admin.footer | action | installer/admin/templates/footer.php | — | Emitted in the shared admin footer; receives the CSP nonce for safe inline scripts |
| admin.form_editor.after | action | installer/plugins/klytos-forms/admin/form-editor.php | — | Emitted at the tail of the form editor screen; receives the form being edited |
| admin.form_editor.before | action | installer/plugins/klytos-forms/admin/form-editor.php | — | Emitted at the top of the form editor screen; receives the form being edited |
| admin.form_entries.after | action | installer/plugins/klytos-forms/admin/form-entries.php | — | Emitted at the tail of the form entries screen; no payload, echo extra HTML |
| admin.form_entries.before | action | installer/plugins/klytos-forms/admin/form-entries.php | — | Emitted at the top of the form entries screen; no payload, echo extra HTML |
| admin.forms.after | action | installer/plugins/klytos-forms/admin/forms.php | — | Emitted at the tail of the forms listing screen; no payload, echo extra HTML |
| admin.forms.before | action | installer/plugins/klytos-forms/admin/forms.php | — | Emitted at the top of the forms listing screen; no payload, echo extra HTML |
| admin.head | action | installer/admin/templates/header.php | — | Emitted inside the admin document head; receives the CSP nonce for assets and inline code |
| admin.head_meta | action | installer/admin/templates/header.php | — | Emitted in the admin head meta area for extra meta or link tags; receives the CSP nonce |
| admin.logs.after | action | installer/admin/logs.php | — | Emitted at the tail of the logs viewer screen; no payload, echo extra HTML |
| admin.logs.before | action | installer/admin/logs.php | — | Emitted at the top of the logs viewer screen; no payload, echo extra HTML |
| admin.mcp.after | action | installer/admin/mcp.php | — | Emitted at the tail of the MCP settings screen; no payload, echo extra HTML |
| admin.mcp.before | action | installer/admin/mcp.php | — | Emitted at the top of the MCP settings screen; no payload, echo extra HTML |
| admin.page.after_content | action | installer/admin/templates/footer.php | — | Emitted after any admin screen body closes; receives the current admin page identifier |
| admin.page.before_content | action | installer/admin/templates/sidebar.php | — | Emitted before any admin screen body opens; receives the current admin page identifier |
| admin.pages.after | action | installer/admin/pages.php | — | Emitted at the tail of the pages listing screen; no payload, echo extra HTML |
| admin.pages.before | action | installer/admin/pages.php | — | Emitted at the top of the pages listing screen; no payload, echo extra HTML |
| admin.plugin_page.after_render | action | installer/admin/plugin-page.php | — | Emitted once a plugin-owned admin screen has rendered; receives plugin id and page name |
| admin.plugin_page.before_render | action | installer/admin/plugin-page.php | — | Emitted before a plugin-owned admin screen renders; receives plugin id and page name |
| admin.plugins.after | action | installer/admin/plugins.php | — | Emitted at the tail of the plugins screen; no payload, echo extra HTML |
| admin.plugins.before | action | installer/admin/plugins.php | — | Emitted at the top of the plugins screen; no payload, echo extra HTML |
| admin.plugins_after_table | action | installer/admin/plugins.php | — | Emitted right below the installed plugins table; no payload, echo extra HTML |
| admin.plugins_before_table | action | installer/admin/plugins.php | — | Emitted right above the installed plugins table; no payload, echo extra HTML |
| admin.plugins_column_ | action | installer/admin/plugins.php | — | Dynamic per-column hook rendering a plugin-added cell; receives the plugin row data |
| admin.plugins_page_scripts | action | installer/admin/plugins.php | — | Emitted after the plugins screen JS is enqueued; receives the CSP nonce for extra scripts |
| admin.post_type_edit.after_settings | action | installer/admin/post-type-edit.php | — | Emitted below the post type settings form; receives the post type array and its id |
| admin.post_types.after | action | installer/admin/post-types.php | — | Emitted at the tail of the post types screen; no payload, echo extra HTML |
| admin.post_types.before | action | installer/admin/post-types.php | — | Emitted at the top of the post types screen; no payload, echo extra HTML |
| admin.privacy.after | action | installer/admin/privacy.php | — | Emitted at the tail of the privacy tools screen; no payload, echo extra HTML |
| admin.privacy.before | action | installer/admin/privacy.php | — | Emitted at the top of the privacy tools screen; no payload, echo extra HTML |
| admin.profile.after | action | installer/admin/profile.php | — | Emitted at the tail of the user profile screen; no payload, echo extra HTML |
| admin.profile.after_fields | action | installer/admin/profile.php | — | Emitted below the built-in profile form fields; receives the user being edited |
| admin.profile.before | action | installer/admin/profile.php | — | Emitted at the top of the user profile screen; no payload, echo extra HTML |
| admin.profile.before_fields | action | installer/admin/profile.php | — | Emitted above the built-in profile form fields; receives the user being edited |
| admin.profile.custom_fields | action | installer/admin/profile.php | — | Emitted where plugins render their own profile inputs; receives the user being edited |
| admin.security.after_2fa | action | installer/admin/security.php | — | Emitted below the two-factor authentication panel; no payload, echo extra HTML |
| admin.security.after_encryption | action | installer/admin/security.php | — | Emitted below the encryption settings panel; no payload, echo extra HTML |
| admin.security.before | action | installer/admin/security.php | — | Emitted at the top of the security screen; no payload, echo extra HTML |
| admin.security.before_2fa | action | installer/admin/security.php | — | Emitted above the two-factor authentication panel; no payload, echo extra HTML |
| admin.security.before_encryption | action | installer/admin/security.php | — | Emitted above the encryption settings panel; no payload, echo extra HTML |
| admin.settings.after | action | installer/admin/settings.php | — | Emitted at the tail of the site settings screen; no payload, echo extra HTML |
| admin.settings.after_save | action | installer/admin/settings.php | — | Emitted once a settings section has been persisted; receives the section slug and raw POST |
| admin.settings.after_section | action | installer/admin/settings.php | — | Emitted below each settings section block; receives the section slug being rendered |
| admin.settings.before | action | installer/admin/settings.php | — | Emitted at the top of the site settings screen; no payload, echo extra HTML |
| admin.settings.before_save | action | installer/admin/settings.php | — | Emitted before a settings section is persisted; receives the section slug and raw POST |
| admin.settings.before_section | action | installer/admin/settings.php | — | Emitted above each settings section block; receives the section slug being rendered |
| admin.settings.render_custom_sections | action | installer/admin/settings.php | — | Emitted after all core sections so plugins can print their own; receives the site config |
| admin.sidebar.after | action | installer/admin/templates/sidebar.php | — | Emitted right after the sidebar aside element closes; no payload, echo extra HTML |
| admin.sidebar.after_search | action | installer/admin/templates/sidebar.php | — | Emitted just below the sidebar search box; no payload, echo extra HTML |
| admin.sidebar.after_section | action | installer/admin/templates/sidebar.php | — | Emitted below each sidebar menu section; receives that section's name |
| admin.sidebar.after_sections | action | installer/admin/templates/sidebar.php | — | Emitted once every sidebar menu section is rendered; no payload, echo extra HTML |
| admin.sidebar.before | action | installer/admin/templates/sidebar.php | — | Emitted right before the sidebar aside element opens; no payload, echo extra HTML |
| admin.sidebar.before_search | action | installer/admin/templates/sidebar.php | — | Emitted just above the sidebar search box; no payload, echo extra HTML |
| admin.sidebar.before_section | action | installer/admin/templates/sidebar.php | — | Emitted above each sidebar menu section; receives that section's name |
| admin.sidebar.before_sections | action | installer/admin/templates/sidebar.php | — | Emitted before any sidebar menu section is rendered; no payload, echo extra HTML |
| admin.sidebar.footer | action | installer/admin/templates/sidebar.php | — | Emitted at the bottom of the sidebar nav, past the no-results notice; no payload |
| admin.sidebar_order.reset | action | installer/admin/api/sidebar-order.php | — | Emitted when a user restores the default sidebar ordering; receives that user id |
| admin.sidebar_order.saved | action | installer/admin/api/sidebar-order.php | — | Emitted after a custom sidebar order is stored; receives user id, section and item order |
| admin.system_options.after | action | installer/admin/system-options.php | — | Emitted at the tail of the system options screen; no payload, echo extra HTML |
| admin.system_options.before | action | installer/admin/system-options.php | — | Emitted at the top of the system options screen; no payload, echo extra HTML |
| admin.tasks.after | action | installer/admin/tasks.php | — | Emitted at the tail of the tasks screen; no payload, echo extra HTML |
| admin.tasks.before | action | installer/admin/tasks.php | — | Emitted at the top of the tasks screen; no payload, echo extra HTML |
| admin.templates.after | action | installer/admin/templates.php | — | Emitted at the tail of the templates screen; no payload, echo extra HTML |
| admin.templates.before | action | installer/admin/templates.php | — | Emitted at the top of the templates screen; no payload, echo extra HTML |
| admin.theme.after | action | installer/admin/theme.php | — | Emitted at the tail of the theme customization screen; no payload, echo extra HTML |
| admin.theme.before | action | installer/admin/theme.php | — | Emitted at the top of the theme customization screen; no payload, echo extra HTML |
| admin.topbar_after | action | installer/admin/templates/sidebar.php | — | Emitted right after the admin top bar markup; no payload, echo extra HTML |
| admin.topbar_before | action | installer/admin/templates/sidebar.php | — | Emitted right before the admin top bar markup opens; no payload, echo extra HTML |
| admin.translations.after | action | installer/admin/translations.php | — | Emitted at the tail of the translations screen; no payload, echo extra HTML |
| admin.translations.after_filters | action | installer/admin/translations.php | — | Emitted below the translations filter controls; no payload, echo extra HTML |
| admin.translations.after_table | action | installer/admin/translations.php | — | Emitted below the translation strings table; no payload, echo extra HTML |
| admin.translations.before | action | installer/admin/translations.php | — | Emitted at the top of the translations screen; no payload, echo extra HTML |
| admin.translations.before_filters | action | installer/admin/translations.php | — | Emitted above the translations filter controls; no payload, echo extra HTML |
| admin.translations.before_table | action | installer/admin/translations.php | — | Emitted above the translation strings table; no payload, echo extra HTML |
| admin.users.after | action | installer/admin/users.php | — | Emitted at the tail of the users screen; no payload, echo extra HTML |
| admin.users.before | action | installer/admin/users.php | — | Emitted at the top of the users screen; no payload, echo extra HTML |
| admin.users.edit_form.after_fields | action | installer/admin/users.php | — | Emitted at the end of the user edit form markup; receives the current users array |
| admin.users.edit_form.before_fields | action | installer/admin/users.php | — | Emitted just above the first user edit form field; receives the current users array |
| admin.webhooks.after | action | installer/admin/webhooks.php | — | Emitted at the very bottom of the webhooks admin screen; no payload |
| admin.webhooks.after_form | action | installer/admin/webhooks.php | — | Emitted right below the webhook create/edit form markup; no payload |
| admin.webhooks.before | action | installer/admin/webhooks.php | — | Emitted at the top of the webhooks admin screen, before its content; no payload |
| admin.webhooks.before_form | action | installer/admin/webhooks.php | — | Emitted right above the webhook create/edit form markup; no payload |
| ai.chat.after_send | action | installer/core/ai/chat-engine.php | — | Emitted once the AI provider replied; receives user ID, the result and provider ID |
| ai.chat.before_send | action | installer/core/ai/chat-engine.php | — | Emitted in processMessage before calling the provider; gets user ID, messages, provider ID |
| ai.chat.error | action | installer/core/ai/chat-engine.php | — | Emitted when the AI provider call throws; receives the provider ID and the exception |
| ai.chat.tool_executed | action | installer/core/ai/chat-engine.php | — | Emitted around an MCP tool invocation from chat; gets tool name, input and user ID |
| ai.key.configured | action | installer/core/ai/ai-key-manager.php | — | Emitted after an API key is stored for a provider; receives the provider ID |
| ai.key.removed | action | installer/core/ai/ai-key-manager.php | — | Emitted after a provider's stored API key is deleted; receives the provider ID |
| asset.after_delete | action | installer/core/asset-manager.php | — | Emitted once the media file is gone; receives its path and the deleted asset record |
| asset.after_edit | action | installer/core/asset-manager.php | — | Emitted after image edits are written; gets absolute path, relative path and operations |
| asset.after_upload | action | installer/core/asset-manager.php | — | Emitted once an uploaded file is stored; receives the upload result array and filename |
| asset.before_delete | action | installer/core/asset-manager.php | — | Emitted before the media file is unlinked; gets its path and the asset record |
| asset.before_edit | action | installer/core/asset-manager.php | — | Emitted in editImage before transforms run; gets the file path and requested operations |
| asset.before_upload | action | installer/core/asset-manager.php | — | Emitted after upload validation but before the write; gets filename and target directory |
| asset.metadata_error | action | installer/core/asset-manager.php | — | Emitted when post-upload metadata extraction fails; gets the error message and rel. path |
| asset_category.after_create | action | installer/core/asset-manager.php | — | Emitted once a media category row is stored; receives the created category record |
| asset_category.after_delete | action | installer/core/asset-manager.php | — | Emitted once a media category is removed; receives the deleted category ID |
| asset_category.before_create | action | installer/core/asset-manager.php | — | Emitted before a media category is stored; receives the proposed name and slug |
| asset_category.before_delete | action | installer/core/asset-manager.php | — | Emitted before a media category is removed; receives the category ID |
| auth.access_denied | action | installer/core/helpers-global.php | docs/reference/authorization.md | Fires immediately before a request is refused; audit hook, cannot reverse the decision |
| auth.after_login | action | installer/core/auth.php | — | Emitted on successful sign-in (also after 2FA); receives the username and user ID |
| auth.before_login | action | installer/core/auth.php | — | Emitted before credentials are validated; receives the submitted username |
| backup.after | action | installer/core/updater.php | — | Emitted when a manual backup finished; receives type 'local' and the backup directory |
| backup.before | action | installer/core/updater.php | — | Emitted when a manual backup starts; receives the backup type 'local' |
| block.after_save | action | installer/core/block-manager.php | — | Emitted once a block definition is persisted; receives the stored block array |
| block.before_save | action | installer/core/block-manager.php | — | Emitted before a block definition is written; receives the block array to be saved |
| block.global_data_changed | action | installer/core/block-manager.php | — | Emitted when a block's shared global data is replaced; gets the block ID and new data |
| build.after | action | installer/core/build-engine.php | — | Emitted at the end of a full site build; receives the pages built count and error list |
| build.assets_changed | action | installer/core/plugin-loader.php | — | Emitted on plugin activate/deactivate so hook JS and plugins.css are rebuilt; no payload |
| build.before | action | installer/core/build-engine.php | — | Emitted at the start of buildAll, before any page is rendered; no payload |
| build.llms_generated | action | installer/core/build-engine.php | — | Emitted after llms.txt is written during a build; receives the generation stats array |
| build.page.after | action | installer/core/build-engine.php | — | Emitted once a single page is written to disk; gets the page array and output path |
| build.page.before | action | installer/core/build-engine.php | — | Emitted before a single page is rendered during a build; receives the page array |
| cache.all_flushed | action | installer/core/cache-manager.php | — | Emitted after every cache group is cleared; receives the resolved cache driver name |
| cache.flushed | action | installer/core/cache-manager.php | — | Emitted after the cache is cleared so plugins can drop theirs; gets the driver name |
| cache.group_flushed | action | installer/core/cache-manager.php | — | Emitted after one cache group is cleared; gets the group name and driver name |
| comment.after_delete | action | installer/core/comment-manager.php | — | Emitted once a comment row is removed; receives the deleted comment ID |
| comment.after_save | action | installer/core/comment-manager.php | — | Emitted once a submitted comment is stored; gets the comment array and 'create' |
| comment.before_delete | action | installer/core/comment-manager.php | — | Emitted before a comment row is removed; receives the comment ID |
| comment.before_save | action | installer/core/comment-manager.php | — | Emitted before a submitted comment is written; receives the comment array |
| comment.honeypot_rejected | action | installer/public/comment-submit.php | docs/reference/public-comments.md | Emitted when the honeypot catches a bot; receives the page slug and client IP |
| comment.moderated | action | installer/core/comment-manager.php | — | Emitted when a comment's moderation state changes; gets the comment and new status |
| comment.rate_limited | action | installer/public/comment-submit.php | docs/reference/public-comments.md | Emitted when an anonymous submission is refused by the rate limiter; receives the client IP |
| consent.after_save | action | installer/core/consent-manager.php | — | Emitted once the cookie consent config is stored; receives the sanitized config |
| consent.before_save | action | installer/core/consent-manager.php | — | Emitted after sanitizing but before writing consent config; gets the sanitized config |
| cron.run | action | installer/core/cron-manager.php | — | Emitted at the end of a due-task sweep; receives executed task list and error list |
| custom_field.after_delete | action | installer/core/post-type-manager.php | — | Emitted once a field is dropped from a post type; gets the post type ID and field ID |
| custom_field.after_reorder | action | installer/core/post-type-manager.php | — | Emitted after fields are resorted; receives the post type ID and ordered field ID list |
| custom_field.after_save | action | installer/core/post-type-manager.php | — | Emitted after a field is added or updated; gets post type ID, field data and the mode |
| custom_field.before_delete | action | installer/core/post-type-manager.php | — | Emitted before a field is dropped from a post type; gets post type ID and field ID |
| custom_field.before_save | action | installer/core/post-type-manager.php | — | Emitted before a field is added or updated; gets post type ID, field data and the mode |
| editor.after_canvas | action | installer/admin/page-editor.php | — | Emitted below the editor content canvas; receives the page array and the editing flag |
| editor.after_custom_fields | action | installer/admin/page-editor.php | — | Emitted below the custom fields panel; receives the page array and the editing flag |
| editor.before_canvas | action | installer/admin/page-editor.php | — | Emitted above the editor content canvas; receives the page array and the editing flag |
| editor.before_custom_fields | action | installer/admin/page-editor.php | — | Emitted above the custom fields panel; receives the page array and the editing flag |
| editor.sidebar.after_panels | action | installer/admin/page-editor.php | — | Emitted after the last editor sidebar panel; gets the page array and the editing flag |
| editor.sidebar.after_seo | action | installer/admin/page-editor.php | — | Emitted below the sidebar SEO panel; receives the page array and the editing flag |
| editor.sidebar.before_seo | action | installer/admin/page-editor.php | — | Emitted above the sidebar SEO panel; receives the page array and the editing flag |
| form.after_create | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted once a new form definition is stored; receives the created form array |
| form.after_delete | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted once a form definition is removed; receives the deleted form ID |
| form.after_update | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted once form changes are persisted; receives the updated form array |
| form.before_delete | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted before a form definition is removed; receives the form ID |
| form.before_validate | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted on submission before field validation; gets the form and the raw submitted data |
| form.entry_created | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted once a submission is stored as an entry; gets the entry and its form |
| form.notification_sent | action | installer/plugins/klytos-forms/src/FormManager.php | — | Emitted per notification dispatch attempt; gets notification, entry and success flag |
| http.after_request | action | installer/core/http-client.php | — | Emitted when an outbound HTTP call returns; gets result, method, URL and duration in ms |
| http.error | action | installer/core/http-client.php | — | Emitted when an outbound HTTP call fails; receives the error message, method and URL |
| http.safe.blocked | action | installer/core/safe-http.php | docs/reference/safe-http.md | Fires when SafeHttp refuses an outbound request; audit hook, cannot reverse the decision |
| http.safe.redirect | action | installer/core/safe-http.php | docs/reference/safe-http.md | Fires for each redirect hop before it is validated and followed |
| importer.after_analyze | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted when a sitemap or crawl scan finishes; gets the source kind and analysis result |
| importer.after_fetch | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted after a source URL is downloaded; receives the URL and the HTTP response |
| importer.after_import | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted when an import batch completes; receives the session ID and the results array |
| importer.after_media_download | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted when media fetching for a session ends; gets the session ID and result data |
| importer.after_page | action | installer/plugins/klytos-importer/admin/import.php | — | Emitted at the bottom of the importer admin screen; no payload |
| importer.after_upload_form | action | installer/plugins/klytos-importer/admin/import.php | — | Emitted right below the importer file upload form; no payload |
| importer.before_analyze | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted before a sitemap or crawl scan starts; gets the source kind and the start URL |
| importer.before_fetch | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted before a source URL is downloaded; receives that URL |
| importer.before_import | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted before an import batch runs; receives the session ID and the pages to import |
| importer.before_media_download | action | installer/plugins/klytos-importer/klytos-importer.php | — | Emitted before media fetching starts; gets the session ID and the media list |
| importer.before_page | action | installer/plugins/klytos-importer/admin/import.php | — | Emitted at the top of the importer admin screen; no payload |
| importer.before_upload_form | action | installer/plugins/klytos-importer/admin/import.php | — | Emitted right above the importer file upload form; no payload |
| integrity.after_verify | action | installer/core/integrity-checker.php | — | Emitted when file hash verification ends; receives the full integrity report array |
| integrity.before_verify | action | installer/core/integrity-checker.php | — | Emitted before file hash verification starts; receives the force-refresh flag |
| klytos.init | action | installer/core/app.php | — | Emitted once all core services are ready for plugin post-load setup; gets the app object |
| klytos_die | action | installer/core/helpers.php | — | Emitted before the fatal error screen renders so plugins can intercept; gets message, title, status |
| logger.after_delete_all | action | installer/core/logger.php | — | Emitted after every log file is purged; receives the number of files deleted |
| logger.after_write | action | installer/core/logger.php | — | Emitted after a log line is appended; receives the entry and the target log file |
| logger.before_delete | action | installer/core/logger.php | — | Emitted before a single log file is removed; receives its filename |
| login.after_fields | action | installer/admin/login.php | — | Emitted inside the login form after the credential inputs; no payload |
| login.after_form | action | installer/admin/login.php | — | Emitted just below the closing login form tag; no payload |
| login.before_form | action | installer/admin/login.php | — | Emitted just above the login form markup; no payload |
| login.footer | action | installer/admin/login.php | — | Emitted at the end of the login page body, for extra scripts; no payload |
| login.head | action | installer/admin/login.php | — | Emitted in the login page head, for extra styles or meta tags; no payload |
| mailer.after_send | action | installer/core/mailer.php | — | Emitted once delivery is attempted; gets recipients, subject and the send result |
| mailer.before_send | action | installer/core/mailer.php | — | Emitted before a mail is dispatched, for logging or analytics; gets recipients and subject |
| maintenance.disabled | action | installer/core/build-engine.php +2 more | — | Emitted when the site leaves maintenance mode; no payload |
| maintenance.enabled | action | installer/core/build-engine.php +2 more | — | Emitted when the site is put into maintenance mode; no payload |
| mcp.access_denied | action | installer/core/mcp/tool-registry.php | docs/reference/mcp-authorization.md | Fires before an MCP tool call is refused by the authorization gate; audit hook, cannot reverse it |
| mcp.tool_called | action | installer/core/mcp/tool-registry.php | — | Emitted when an MCP tool is invoked, for auditing; gets the tool name and its params |
| meta.after_delete | action | installer/core/meta-manager.php | — | Emitted once a meta key is removed; receives collection, entity ID and key |
| meta.after_set | action | installer/core/meta-manager.php | — | Emitted once a meta value is stored; gets collection, entity ID, key and value |
| meta.before_delete | action | installer/core/meta-manager.php | — | Emitted before a meta key is removed; receives collection, entity ID and key |
| meta.before_set | action | installer/core/meta-manager.php | — | Emitted before a meta value is written; gets collection, entity ID, key and value |
| notice.created | action | installer/core/notice-manager.php | — | Fired once an admin notice has been stored; receives the full notice array |
| notice.deleted | action | installer/core/notice-manager.php | — | Fired when a stored notice is removed; receives the notice ID |
| notice.dismissed | action | installer/core/notice-manager.php | — | Fired when a user dismisses a notice; receives the dismissed notice ID |
| notice.render.after | action | installer/core/notice-manager.php | — | Fired once the notice list has been echoed; receives the rendered notices array |
| notice.render.before | action | installer/core/notice-manager.php | — | Fired just before notices are echoed to the admin; receives the notices array |
| option.after_delete | action | installer/core/options-manager.php | — | Fired once an option row is removed from storage; receives the option key |
| option.after_set | action | installer/core/options-manager.php | — | Fired once an option is persisted; receives key, new value and previous value |
| option.before_delete | action | installer/core/options-manager.php | — | Fired before an option is removed from storage; receives the option key |
| option.before_set | action | installer/core/options-manager.php | — | Fired before an option is written; receives key, incoming value and old value |
| option.registered | action | installer/core/options-manager.php | — | Fired when an option is declared in the registry; receives key, sensitive flag and meta |
| page.after_delete | action | installer/core/page-manager.php | — | Notifies plugins once a page is permanently erased from disk; receives the slug |
| page.after_restore | action | installer/core/page-manager.php | — | Fired once a trashed page is put back live; receives the slug and restored page array |
| page.after_save | action | installer/core/page-manager.php +1 more | — | Fired once page data is written; receives the page array and the operation label |
| page.after_trash | action | installer/core/page-manager.php | — | Notifies plugins after a page is moved to trash; receives the slug and page array |
| page.before_delete | action | installer/core/page-manager.php | — | Notifies plugins before a page is permanently erased; receives the slug |
| page.before_restore | action | installer/core/page-manager.php | — | Fired before a trashed page is put back live; receives the slug and page array |
| page.before_save | action | installer/core/page-manager.php | — | Lets plugins inspect page data before it is written; receives page array and create/update label |
| page.before_trash | action | installer/core/page-manager.php | — | Notifies plugins before a page is moved to trash; receives the slug and page array |
| page.lock_acquired | action | installer/core/page-manager.php | — | Fired when an editing lock is taken on a page; receives the slug and owning user ID |
| page.lock_expired | action | installer/core/page-manager.php | — | Fired when a stale editing lock is cleaned up; receives the slug and prior owner ID |
| page.lock_released | action | installer/core/page-manager.php | — | Fired when an editing lock is voluntarily freed; receives the slug and user ID |
| page.lock_takeover | action | installer/admin/api/post-lock.php | — | Fired when an editor forcibly seizes another user's lock; receives slug, new and previous owner |
| page.scheduled_published | action | installer/core/page-manager.php | — | Fired when a scheduled page reaches its date and goes live; receives the page array |
| page.status_changed | action | installer/core/page-manager.php | — | Signals a workflow transition on update; receives the page plus old and new status |
| page_template.after_save | action | installer/core/page-template-manager.php | — | Fired once a page template definition is persisted; receives the template array |
| page_template.approved | action | installer/core/page-template-manager.php | — | Fired when a pending template is marked approved; receives the approved template array |
| page_template.before_save | action | installer/core/page-template-manager.php | — | Fired before a page template definition is written; receives the template array |
| part.after_save | action | installer/core/part-manager.php | — | Fired once a template part is persisted; receives the saved part array |
| part.before_save | action | installer/core/part-manager.php | — | Fired before a template part is written to storage; receives the part array |
| part.data_changed | action | installer/core/part-manager.php | — | Fired when a part's stored data payload is replaced; receives the part ID and new data |
| part.deleted | action | installer/core/part-manager.php | — | Fired when a template part is removed; receives the deleted part ID |
| plugin.activated | action | installer/core/plugin-loader.php | — | Lets a plugin set up its own hooks on activation; receives the plugin ID and manifest |
| plugin.backup_created | action | installer/core/plugin-loader.php | — | Fired after a plugin snapshot is stored and old ones purged; receives plugin ID and backup name |
| plugin.before_delete | action | installer/core/plugin-loader.php | — | Fired before a plugin directory is removed from disk; receives the plugin ID |
| plugin.deactivated | action | installer/core/plugin-loader.php | — | Fired when a plugin is switched off; receives the plugin ID and its manifest (may be null) |
| plugin.deleted | action | installer/core/plugin-loader.php | — | Fired once a plugin directory has been removed; receives the plugin ID |
| plugin.installed | action | installer/core/plugin-loader.php | — | Fired after a ZIP install completes; receives the plugin ID and whether it was an update |
| plugin.loaded | action | installer/core/plugin-loader.php | — | Signals that a plugin's entry point was included at boot; receives plugin ID and manifest |
| plugin.logs_disabled | action | installer/core/plugin-loader.php | — | Fired when per-plugin logging is switched off; receives the plugin ID |
| plugin.logs_enabled | action | installer/core/plugin-loader.php | — | Fired when per-plugin logging is switched on; receives the plugin ID |
| plugin.restored | action | installer/core/plugin-loader.php | — | Fired after a plugin is rolled back from a snapshot; receives plugin ID and backup name |
| plugin.uninstalled | action | installer/core/plugin-loader.php | — | Fired when a plugin's uninstall routine runs and its data is cleaned; receives the plugin ID |
| post_type.after_delete | action | installer/core/post-type-manager.php | — | Fired once a post type and its taxonomy term data are removed; receives the post type ID |
| post_type.after_save | action | installer/core/post-type-manager.php | — | Fired once a post type definition is persisted; receives the definition and create/update label |
| post_type.before_delete | action | installer/core/post-type-manager.php | — | Fired before a post type definition is removed; receives the post type ID |
| post_type.before_save | action | installer/core/post-type-manager.php | — | Fired before a post type definition is written; receives the definition and create/update label |
| privacy.before_erasure | action | installer/core/privacy-manager.php | — | Fired at the start of a GDPR erasure run; receives the user ID and chosen data sections |
| privacy.erase_section | action | installer/core/privacy-manager.php | — | Asks listeners to wipe one data section for a subject; receives section ID and user ID |
| privacy.erasure_complete | action | installer/core/privacy-manager.php | — | Fired once every erasure section has run; receives the user ID and per-section results |
| privacy.export_generated | action | installer/core/privacy-manager.php | — | Lets plugins append data sections during a subject export; receives user ID and export stage |
| privacy.export_sent | action | installer/admin/privacy.php | — | Fired when a personal-data export is delivered to the subject; receives the user ID |
| router.after_dispatch | action | installer/core/router.php | — | Lets plugins act once a matched route has run; receives the route definition |
| router.before_dispatch | action | installer/core/router.php | — | Lets plugins act just before a matched route handler runs; receives the route definition |
| scheduler.action_canceled | action | installer/core/action-scheduler.php | — | Fired when a queued action is cancelled before running; receives the action record |
| scheduler.action_complete | action | installer/core/action-scheduler.php | — | Fired after a queued action's callback finishes successfully; receives the action record |
| scheduler.action_created | action | installer/core/action-scheduler.php | — | Fired when a single or recurring job is queued; receives the new action record |
| scheduler.action_failed | action | installer/core/action-scheduler.php | — | Fired when an action's callback throws; receives the action record and the exception |
| scheduler.batch_complete | action | installer/core/action-scheduler.php | — | Fired after a queue-processing run ends; receives the batch results summary |
| shortcode.registered | action | installer/core/shortcode-manager.php | — | Fired when a shortcode handler is added to the registry; receives the shortcode tag |
| status.after_delete | action | installer/core/post-type-manager.php | — | Fired once a custom post status is removed; receives the status ID and post type ID |
| status.after_save | action | installer/core/post-type-manager.php | — | Fired once a custom status is persisted; receives the status definition, post type ID and mode |
| status.before_delete | action | installer/core/post-type-manager.php | — | Fired before a custom post status is removed; receives the status ID and post type ID |
| status.before_save | action | installer/core/post-type-manager.php | — | Fired before a custom status is written; receives the status definition, post type ID and mode |
| task.completed | action | installer/core/task-manager.php | — | Fired when a task is marked done; receives the completed task record |
| task.created | action | installer/core/task-manager.php | — | Fired when a new task record is added; receives the task array |
| taxonomy.after_delete | action | installer/core/post-type-manager.php | — | Fired once a taxonomy and all its terms are removed; receives post type ID and taxonomy ID |
| taxonomy.after_save | action | installer/core/post-type-manager.php | — | Fired once a taxonomy definition is persisted; receives post type ID, taxonomy data and mode |
| taxonomy.before_save | action | installer/core/post-type-manager.php | — | Fired before a taxonomy definition is written; receives post type ID, taxonomy data and mode |
| term.after_delete | action | installer/core/post-type-manager.php | — | Fired once a term is removed from a taxonomy; receives post type ID, taxonomy ID and term slug |
| term.after_save | action | installer/core/post-type-manager.php | — | Fired once a term is persisted; receives post type ID, taxonomy ID, term data and mode |
| term.before_delete | action | installer/core/post-type-manager.php | — | Fired before a term is removed; receives post type ID, taxonomy ID and term slug |
| term.before_save | action | installer/core/post-type-manager.php | — | Fired before a term is written; receives post type ID, taxonomy ID, term data and mode |
| terminal.after_execute | action | installer/core/terminal-executor.php | — | Fired once a terminal command handler returns; receives the command name and its output |
| terminal.before_execute | action | installer/core/terminal-executor.php | — | Fired before a terminal command handler runs; receives the command name and parsed args |
| theme.after_save | action | installer/core/theme-manager.php +1 more | — | Fired once theme settings (colors, fonts, layout) are persisted; receives the theme array |
| theme.before_save | action | installer/core/theme-manager.php | — | Fired before theme settings are written; receives the incoming theme data being saved |
| transient.delete_ | action | installer/core/helpers-global.php | — | Dynamic per-key hook fired when a cached transient is dropped; suffix is the transient key |
| transient.set_ | action | installer/core/helpers-global.php | — | Dynamic per-key hook fired when a transient is cached; receives the value and TTL |
| translations.after_bulk_save | action | installer/core/translation-manager.php | — | Fired once a whole locale set is written; receives source ID, locale and translations map |
| translations.after_save | action | installer/core/translation-manager.php | — | Fired once one string is stored; receives source ID, locale, string key and value |
| translations.before_bulk_save | action | installer/core/translation-manager.php | — | Fired before a whole locale set is written; receives source ID, locale and translations map |
| translations.before_save | action | installer/core/translation-manager.php | — | Fired before one string is stored; receives source ID, locale, string key and value |
| user.before_create | action | installer/core/user-manager.php | — | Lets plugins act just before a new account record is written; receives the pending user array |
| user.before_delete | action | installer/core/user-manager.php | — | Lets plugins act before an account is removed; receives the user ID and the user record |
| user.before_update | action | installer/core/user-manager.php | — | Lets plugins act before account changes are written; receives user ID, new data and current record |
| user.created | action | installer/core/user-manager.php | — | Hook for welcome mail or audit logging on signup; receives the sanitized user record |
| user.deleted | action | installer/core/user-manager.php | — | Fired once an account is removed; receives the user ID and the deleted username |
| user.login | action | installer/core/user-manager.php | — | Fired when credentials authenticate successfully; receives the sanitized user record |
| user.logout | action | installer/core/auth.php | — | Fired before the session is destroyed; receives the session username and user ID |
| user.ownership_transferred | action | installer/core/user-manager.php | — | Fired when site ownership moves to another account; receives previous and new owner IDs |
| user.role_changed | action | installer/core/user-manager.php | — | Fired on update only when the role actually differs; receives user ID, new role and old role |
| user.updated | action | installer/core/user-manager.php | — | Fired once account changes are persisted; receives the sanitized updated user record |
| webhook.after_create | action | installer/core/webhook-manager.php | — | Lets plugins act once a webhook subscription is stored; receives the webhook record |
| webhook.after_delete | action | installer/core/webhook-manager.php | — | Lets plugins act once a webhook subscription is removed; receives the webhook ID |
| webhook.before_create | action | installer/core/webhook-manager.php | — | Lets plugins act before a webhook subscription is stored; receives the submitted data |
| webhook.before_delete | action | installer/core/webhook-manager.php | — | Lets plugins act before a webhook subscription is removed; receives the webhook ID |
| x402.config.updated | action | installer/core/x402-mcp-tools.php +1 more | — | Fired when x402 paywall settings are changed; receives the map of updated config values |
| x402.payment_failed | action | installer/core/x402/gate.php | — | Fired when paywall verification rejects a request; receives slug, error text and user agent |
| x402.payment_received | action | installer/core/x402/gate.php | — | Fired when paywall verification succeeds; receives slug, USD price, tx hash and user agent |

## Filters
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| admin.dashboard.widgets | filter | installer/core/helpers-global.php | — | Filters the admin dashboard widget list so plugins can add, remove or reorder widgets |
| admin.gate_map | filter | installer/core/admin-gate.php | docs/reference/authorization.md | Filters the admin gate map so plugins can gate their own admin files |
| admin.logs_file_list | filter | installer/admin/logs.php | — | Filters the list of log files shown on the admin Logs page |
| admin.logs_toolbar | filter | installer/admin/logs.php | — | Filters extra HTML injected into the Logs page toolbar |
| admin.page_title | filter | installer/admin/templates/header.php | — | Filters the admin page title before the header template renders it |
| admin.plugin_page_capability | filter | installer/admin/plugin-page.php | — | Filters the capability required to view a plugin admin page before the access check |
| admin.plugins_columns | filter | installer/admin/plugins.php | — | Filters the column set of the admin Plugins list table |
| admin.plugins_page_actions | filter | installer/admin/plugins.php | — | Filters the bulk actions offered on the admin Plugins page |
| admin.plugins_row_actions | filter | installer/admin/plugins.php | — | Filters the per-plugin row action links in the Plugins list |
| admin.plugins_row_data | filter | installer/admin/plugins.php | — | Filters a plugin's row data before it is rendered in the Plugins list |
| admin.post_type_edit.update_data | filter | installer/admin/post-type-edit.php | — | Filters the post type update payload before it is saved from the edit screen |
| admin.sidebar_items | filter | installer/admin/templates/sidebar.php | — | Filters the admin sidebar menu items so plugins can add, remove or modify entries |
| admin.sidebar_section_label | filter | installer/admin/templates/sidebar.php | — | Filters the displayed label of an admin sidebar section |
| admin.sidebar_section_order | filter | installer/admin/templates/sidebar.php | — | Filters the display order of admin sidebar sections |
| admin.stylesheets | filter | installer/admin/templates/header.php | — | Filters the array of extra stylesheet URLs loaded in the admin header |
| admin.theme | filter | installer/admin/templates/header.php | — | Filters the active admin theme (light/dark) used to render the admin shell |
| admin.topbar_actions | filter | installer/admin/templates/sidebar.php | — | Filters extra HTML appended to the admin top bar actions area |
| admin.topbar_ai_button | filter | installer/admin/templates/sidebar.php | — | Filters the HTML of the AI assistant button in the admin top bar |
| admin.topbar_center | filter | installer/admin/templates/sidebar.php | — | Filters extra HTML rendered in the center zone of the admin top bar |
| admin.topbar_left | filter | installer/admin/templates/sidebar.php | — | Filters extra HTML rendered in the left zone of the admin top bar |
| admin.topbar_right | filter | installer/admin/templates/sidebar.php | — | Filters extra HTML rendered in the right zone of the admin top bar |
| admin.topbar_user_display | filter | installer/admin/templates/sidebar.php | — | Filters the current user's display label shown in the admin top bar |
| admin.translations.row_actions | filter | installer/admin/translations.php | — | Filters extra row action HTML for a translation key on the Translations page |
| admin_bar.enabled | filter | installer/core/app.php | — | Filters whether the frontend admin bar is rendered for the current request |
| admin_bar.items | filter | installer/core/app.php | — | Filters the admin bar item list before it is serialized to the frontend |
| admin_bar.render | filter | installer/core/app.php | — | Filters the admin bar loader script markup before it is emitted |
| ai.system_prompt | filter | installer/core/ai/chat-engine.php | — | Filters the AI chat system prompt built for the current user and site |
| ai.tools_for_chat | filter | installer/core/ai/chat-engine.php | — | Filters the tool set exposed to the AI chat engine for a given user |
| analytics.event | filter | installer/core/analytics-manager.php | — | Filters an analytics page view entry before it is recorded |
| asset.allowed_types | filter | installer/core/helpers.php | — | Filters the allowed upload file extensions during asset upload validation |
| auth.capabilities | filter | installer/core/helpers-global.php +1 more | — | Filters the resolved capability list of a user before a permission check |
| block.available_types | filter | installer/core/block-manager.php | — | Filters the registered block types so plugins can add their own |
| block.css | filter | installer/core/build-engine.php | — | Filters a block's generated CSS during the build stylesheet generation |
| block.rendered_html | filter | installer/core/block-manager.php | — | Filters a block's wrapped HTML after rendering with its data |
| block.slot_types | filter | installer/core/block-manager.php | — | Filters the available block slot types so plugins can add custom ones |
| build.body_end_html | filter | installer/core/build-engine.php | — | Filters extra HTML injected just before the closing body tag of built pages |
| build.buildable_statuses | filter | installer/core/build-engine.php | — | Filters which public custom post statuses are included in a build |
| build.exclude_structural_blocks | filter | installer/core/build-engine.php | — | Filters the structural blocks excluded because the template already provides them |
| build.global_blocks | filter | installer/core/build-engine.php | — | Filters the cached global blocks (header, footer, top bar) used during the build |
| build.head_html | filter | installer/core/build-engine.php | — | Filters extra HTML injected into the head of built pages |
| build.icons_enabled | filter | installer/core/build-engine.php | — | Filters whether the icon stylesheet link is emitted in built pages |
| build.inject_top_bar | filter | installer/core/build-engine.php | — | Filters the top bar HTML before it is injected into the built page |
| build.llms_full_txt | filter | installer/core/build-engine.php | — | Filters the full llms-full.txt content before it is written to disk |
| build.llms_pages | filter | installer/core/build-engine.php | — | Filters the page entries used to generate the llms.txt files |
| build.llms_txt | filter | installer/core/build-engine.php | — | Filters the llms.txt summary index content before it is written to disk |
| build.oembed.providers | filter | installer/core/oembed-resolver.php | — | Filters the oEmbed provider list before an embed URL is resolved |
| build.page.output | filter | installer/core/build-engine.php | — | Filters the final page HTML before it is written to disk |
| build.page_markdown | filter | installer/core/build-engine.php | — | Filters the Markdown rendition of a page generated for LLM consumption |
| build.search_index | filter | installer/core/build-engine.php | — | Filters the search index entries before the index file is generated |
| build.sitemap_urls | filter | installer/core/build-engine.php | — | Filters extra plugin URLs added to the generated sitemap |
| build.structural_block_mapping | filter | installer/core/build-engine.php | — | Filters the template-to-structural-block mapping used for structure detection |
| cache.groups | filter | installer/core/cache-manager.php | — | Filters the cache group list flushed when all caches are cleared |
| comment.notification_recipient | filter | installer/public/comment-submit.php | docs/reference/public-comments.md | Filters the address told about a new comment; empty disables the notification |
| comment.rate_limit | filter | installer/public/comment-submit.php | docs/reference/public-comments.md | Filters how many comments one address may submit per 60-second window (default 2) |
| consent.audit_export | filter | installer/core/consent-manager.php | — | Filters the consent audit report payload returned for compliance export |
| consent.config | filter | installer/core/consent-manager.php | — | Filters the consent manager configuration when it is read |
| consent.declarations | filter | installer/core/consent-manager.php | — | Filters the cookie and script declarations collected from plugins |
| consent.init_config | filter | installer/core/build-engine.php | — | Filters the consent JS init config embedded in built pages |
| cron.tasks | filter | installer/core/cron-manager.php | — | Filters the registered cron task list so plugins can add scheduled tasks |
| devbar.data | filter | installer/core/dev-bar.php | — | Filters the developer bar payload (meta, performance, queries) before rendering |
| export.data | filter | installer/core/export-manager.php | — | Filters the gathered site export data so plugins can add their collections |
| form.after_validate | filter | installer/plugins/klytos-forms/src/FormManager.php | — | Filters form validation errors after core validation so plugins can add errors |
| form.before_render | filter | installer/plugins/klytos-forms/src/FormRenderer.php | — | Filters the form definition before it is rendered |
| http.before_request | filter | installer/core/http-client.php | — | Filters the method, URL and options tuple before an outgoing HTTP request |
| http.safe.allowed_schemes | filter | installer/core/safe-http.php | docs/reference/safe-http.md | Filters the URL schemes SafeHttp will fetch; CAN weaken a security control |
| http.safe.max_redirects | filter | installer/core/safe-http.php | docs/reference/safe-http.md | Filters how many redirect hops SafeHttp will follow |
| importer.page_data | filter | installer/plugins/klytos-importer/klytos-importer.php | — | Filters imported page HTML content before the page is created |
| importer.session_columns | filter | installer/plugins/klytos-importer/admin/import.php | — | Filters the columns of the importer sessions table in the admin UI |
| kses_post_allowed_tags | filter | installer/core/helpers.php | — | Filters the allowed HTML tag/attribute list used by the KSES post sanitizer |
| logger.before_write | filter | installer/core/logger.php | — | Filters a log entry before it is written, or returns null to suppress it |
| logger.log_files | filter | installer/core/logger.php | — | Filters the list of log files returned when enumerating logs |
| logger.log_format | filter | installer/core/logger.php | — | Filters the formatted log line before it is appended to the log file |
| logger.max_file_size | filter | installer/core/logger.php | — | Filters the maximum log file size before rotation is triggered |
| mailer.headers | filter | installer/core/mailer.php | — | Filters the email headers built for an outgoing message |
| mailer.html_template | filter | installer/core/mailer.php | — | Filters the base HTML email template used to wrap message content |
| mailer.send | filter | installer/core/mailer.php | — | Short-circuits sending: return true to have a plugin transport deliver the email |
| mcp.handle_tool | filter | installer/core/mcp/tool-registry.php | — | Lets plugins handle an unknown MCP tool call by returning a non-null result |
| mcp.tool_capabilities | filter | installer/core/mcp/tool-capabilities.php | docs/reference/mcp-authorization.md | Lets a plugin declare capabilities for its own MCP tools; cannot open a hole by omission |
| mcp.tool_response | filter | installer/core/mcp/tool-registry.php | — | Filters an MCP tool response before it is sent back to the client |
| mcp.tools_list | filter | installer/core/mcp/tool-registry.php | — | Filters the advertised MCP tool list so plugins can register their own tools |
| meta.get | filter | installer/core/meta-manager.php | — | Filters a metadata value when it is read for an entity |
| notice.before_render | filter | installer/core/notice-manager.php | — | Filters the admin notices about to be rendered on the current page |
| notice.render_html | filter | installer/core/notice-manager.php | — | Filters the generated HTML of an individual admin notice |
| notice.transient.add | filter | installer/core/notice-manager.php | — | Filters a transient notice before it is stored for the next page load |
| option.get | filter | installer/core/options-manager.php | — | Filters an option value when it is read from cache or storage |
| page.content | filter | installer/core/build-engine.php | — | Filters a page's rendered content before it is placed into the template |
| page.password_form | filter | installer/core/build-engine.php | — | Filters the password prompt form HTML used for password-protected pages |
| page_template.available_types | filter | installer/core/page-template-manager.php | — | Filters the registered page template types so plugins can add their own |
| page_template.structure | filter | installer/core/page-template-manager.php | — | Filters a page template's block structure before it is rendered |
| page_template.structure_after_dedup | filter | installer/core/page-template-manager.php | — | Filters the template structure after duplicate structural blocks are removed |
| page_template.wrapper_html | filter | installer/core/page-template-manager.php | — | Filters the template wrapper HTML before the content is inserted into it |
| pages.bulk_actions | filter | installer/admin/pages.php | — | Filters the bulk actions offered on the admin Pages list |
| part.rendered_html | filter | installer/core/part-manager.php | — | Filters a template part's HTML after it is rendered |
| privacy.erasable_data | filter | installer/core/privacy-manager.php | — | Filters the erasable data sections declared for a user's GDPR erasure request |
| privacy.erase_plugin_data | filter | installer/core/privacy-manager.php | — | Filters the erasure results so plugins can erase their own user data |
| privacy.export_data | filter | installer/core/privacy-manager.php | — | Filters the collected personal data sections for a user data export |
| privacy.export_html_sections | filter | installer/core/privacy-manager.php | — | Filters the HTML of a personal data export so plugins can append sections |
| security.hsts | filter | installer/core/auth.php | docs/reference/security-headers.md | Filters the Strict-Transport-Security value (HTTPS responses only); CAN weaken or suppress transport security |
| shortcode.output | filter | installer/core/shortcode-manager.php | — | Filters a shortcode's output after its callback runs |
| shortcode.pre_process | filter | installer/core/shortcode-manager.php | — | Filters content before shortcodes are parsed and executed |
| site_health.checks | filter | installer/core/site-health-manager.php | — | Filters the site health check list so plugins can add their own checks |
| template_part. | filter | installer/core/part-manager.php | — | Per-part dynamic filter letting a plugin supply the HTML for a template part |
| terminal.category_labels | filter | installer/core/terminal-executor.php | — | Filters the terminal command category labels shown in the help output |
| terminal.command_output | filter | installer/core/terminal-executor.php | — | Filters a terminal command's output before it is returned to the client |
| terminal.commands | filter | installer/core/terminal-executor.php | — | Filters the registered terminal commands so plugins can add their own |
| theme.data | filter | installer/core/theme-manager.php | — | Filters the active theme data (colors, fonts, layout) when it is read |
| time.now | filter | installer/core/helpers-time.php | — | Filters the current Unix timestamp returned by the time helper |
| time.timezone_list | filter | installer/core/helpers-time.php | — | Short-circuits the timezone list: return an array to replace the generated list |
| transient.pre_get_ | filter | installer/core/helpers-global.php | — | Per-key dynamic filter to short-circuit a transient read with a supplied value |
| translations.sources | filter | installer/core/translation-manager.php | — | Filters the registered translation sources before they are cached |
| translations.stats | filter | installer/core/translation-manager.php | — | Filters the translation coverage statistics before they are returned |
| user.avatar_url | filter | installer/core/helpers-global.php | — | Filters the avatar URL resolved for a user at a given size |
| user.profile_fields | filter | installer/core/user-manager.php | — | Filters the user record on update so plugins can add custom profile fields |
| webhooks.events | filter | installer/core/webhook-manager.php | — | Filters the available webhook event list so plugins can register more events |
| x402.bot_user_agents | filter | installer/core/x402/config.php | — | Filters the user agent patterns treated as AI bots by the x402 paywall |
| x402.payment_providers | filter | installer/core/x402-bootstrap.php | — | Collects x402 payment providers registered by plugins after plugin load |
| x402.response_payload | filter | installer/core/x402/gate.php | — | Filters the HTTP 402 Payment Required response payload for a gated page |

## MCP tools
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| klytos_activate_plugin | mcp tool | installer/core/mcp/tools/plugin-tools.php | — | Activate a plugin by ID, running its install script on first activation |
| klytos_add_block_to_template | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Insert a block into a page template at a given position |
| klytos_add_consent_declaration | mcp tool | installer/core/mcp/tools/consent-tools.php | — | Register a plugin's cookie and script declarations under a consent category |
| klytos_add_custom_field | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Define a new custom field on a post type's data schema |
| klytos_add_menu_item | mcp tool | installer/core/mcp/tools/menu-tools.php | — | Append a single item to the navigation menu |
| klytos_add_post_status | mcp tool | installer/core/mcp/tools/post-status-tools.php | — | Define a custom workflow status with label, color and visibility for a post type |
| klytos_add_taxonomy | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Attach a new hierarchical or flat taxonomy to an existing post type |
| klytos_add_term | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Create a term inside a taxonomy, optionally nested under a parent |
| klytos_ai_get_config | mcp tool | installer/core/mcp/tools/ai-tools.php | — | Read the active AI provider and model for chat, without exposing API keys |
| klytos_ai_get_usage | mcp tool | installer/core/mcp/tools/ai-tools.php | — | Report AI chat token usage for a week, month or all-time period |
| klytos_ai_list_providers | mcp tool | installer/core/mcp/tools/ai-tools.php | — | List AI providers with configuration status and available models |
| klytos_approve_page_template | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Promote a page template from draft to active status |
| klytos_asset_categories_create | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Create a category for organizing media files |
| klytos_asset_categories_list | mcp tool | installer/core/mcp/tools/asset-tools.php | — | List asset categories with the asset count of each |
| klytos_assets_cleanup_unused | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Permanently delete every unused asset and its metadata, guarded by a confirm flag |
| klytos_assets_get_unused | mcp tool | installer/core/mcp/tools/asset-tools.php | — | List registered assets not referenced by any page, part or theme setting |
| klytos_assets_get_usage | mcp tool | installer/core/mcp/tools/asset-tools.php | — | List every location where a given asset is used |
| klytos_assets_list_filtered | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Query registered assets by usage, category, MIME type or search term |
| klytos_assets_rebuild_usage | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Rebuild the asset usage index by rescanning all pages and theme config |
| klytos_assets_sync | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Register filesystem asset files that have no metadata record yet |
| klytos_assets_update_metadata | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Update an asset's title, alt text, description or categories |
| klytos_build_page | mcp tool | installer/core/mcp/tools/build-tools.php | — | Regenerate a single page without a full site rebuild |
| klytos_build_site | mcp tool | installer/core/mcp/tools/build-tools.php | — | Regenerate the whole static site including pages, CSS, sitemap and robots.txt |
| klytos_bulk_moderate_comments | mcp tool | installer/core/mcp/tools/comment-tools.php | — | Approve, reject or spam multiple comments in one batch |
| klytos_bulk_update_pages | mcp tool | installer/core/mcp/tools/bulk-tools.php | — | Apply a bulk status action (publish, draft, trash, delete, restore) to many pages |
| klytos_cancel_scheduled_action | mcp tool | installer/core/mcp/tools/scheduler-tools.php | — | Remove a scheduled action from the queue by ID |
| klytos_check_page_lock | mcp tool | installer/core/mcp/tools/page-tools.php | — | Report whether a page is locked for editing and by which user |
| klytos_complete_task | mcp tool | installer/core/mcp/tools/task-tools.php | — | Mark a review task as completed |
| klytos_create_block | mcp tool | installer/core/mcp/tools/block-tools.php | — | Create a reusable HTML block with configurable slots |
| klytos_create_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Create a page or custom post type entry with hierarchical URL and editor-specific content |
| klytos_create_page_template | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Define a page template listing which blocks appear and in what order |
| klytos_create_post_type | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Register a new custom post type with machine id and human-readable name |
| klytos_create_task | mcp tool | installer/core/mcp/tools/task-tools.php | — | Create a review task attached to a page |
| klytos_create_user | mcp tool | installer/core/mcp/tools/user-tools.php | — | Create a user account with a given role |
| klytos_create_webhook | mcp tool | installer/core/mcp/tools/webhook-tools.php | — | Subscribe a webhook to events and return its HMAC signing secret |
| klytos_deactivate_plugin | mcp tool | installer/core/mcp/tools/plugin-tools.php | — | Deactivate a plugin by ID while preserving its data |
| klytos_delete_asset | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Delete an uploaded asset file |
| klytos_delete_block | mcp tool | installer/core/mcp/tools/block-tools.php | — | Permanently delete a block definition |
| klytos_delete_comment | mcp tool | installer/core/mcp/tools/comment-tools.php | — | Permanently delete a comment |
| klytos_delete_consent_declaration | mcp tool | installer/core/mcp/tools/consent-tools.php | — | Remove a plugin's cookie/script consent audit entry without uninstalling it |
| klytos_delete_custom_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Delete a custom template file so resolution falls back down the hierarchy |
| klytos_delete_custom_template_part | mcp tool | installer/core/mcp/tools/template-tools.php | — | Delete a custom template part so the core or plugin part takes over |
| klytos_delete_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Soft-delete a page by moving it to trash for later restore |
| klytos_delete_part | mcp tool | installer/core/mcp/tools/part-tools.php | — | Delete a site part from storage, leaving file overrides untouched |
| klytos_delete_post_type | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Delete a custom post type with its taxonomies and terms, built-ins excluded |
| klytos_delete_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Delete a database-stored custom template, not built-in or file-based ones |
| klytos_delete_term | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Permanently remove a taxonomy term by slug |
| klytos_delete_webhook | mcp tool | installer/core/mcp/tools/webhook-tools.php | — | Permanently delete a webhook subscription |
| klytos_diff_versions | mcp tool | installer/core/mcp/tools/version-tools.php | — | Compare two page versions and list changed, added and removed fields |
| klytos_edit_image | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Crop, rotate, flip or resize an image server-side with GD |
| klytos_empty_trash | mcp tool | installer/core/mcp/tools/page-tools.php | — | Permanently delete every page currently in the trash |
| klytos_export_site | mcp tool | installer/core/mcp/tools/export-tools.php | — | Export site content as JSON archive, WordPress WXR XML, or CSV page list |
| klytos_force_logout_user | mcp tool | installer/core/mcp/tools/user-tools.php | — | Terminate all active sessions for a user |
| klytos_forms_add_field | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Add a field to a form at an optional position |
| klytos_forms_create | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Create a form with its fields, settings and notifications |
| klytos_forms_delete | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Delete a form, optionally with its entries, requiring confirmation |
| klytos_forms_delete_entry | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Delete a single form entry, requiring confirmation |
| klytos_forms_duplicate | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Copy an existing form under a new title |
| klytos_forms_export_entries | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Export a form's entries as CSV or JSON |
| klytos_forms_get | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Retrieve a form definition by ID |
| klytos_forms_get_entry | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Retrieve a single form entry by ID |
| klytos_forms_list | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | List forms, optionally filtered by active or inactive status |
| klytos_forms_list_entries | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | List a form's entries with status, search and pagination filters |
| klytos_forms_remove_field | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Remove a field from a form |
| klytos_forms_reorder_fields | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Reorder a form's fields from an ordered list of field IDs |
| klytos_forms_stats | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Return submission statistics for a form |
| klytos_forms_update | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Partially update a form's title, fields, settings, notifications or anti-spam |
| klytos_forms_update_entry_status | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Change the status of a form entry |
| klytos_forms_update_field | mcp tool | installer/plugins/klytos-forms/klytos-forms.php | — | Apply updates to a single field inside a form |
| klytos_generate_image | mcp tool | installer/core/mcp/tools/ai-image-tools.php | — | Generate an image with AI and save it into the site assets |
| klytos_get_all_field_values | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Return all custom field values of an entry alongside their definitions |
| klytos_get_analytics | mcp tool | installer/core/mcp/tools/analytics-tools.php | — | Summarize views, visitors, top pages, referrers and devices for a date range |
| klytos_get_block | mcp tool | installer/core/mcp/tools/block-tools.php | — | Retrieve a block definition with its HTML template, slots and data |
| klytos_get_block_slots | mcp tool | installer/core/mcp/tools/block-tools.php | — | List the editable slot definitions a block expects |
| klytos_get_build_status | mcp tool | installer/core/mcp/tools/build-tools.php | — | Report the last build result including LLM discoverability file stats |
| klytos_get_comment_settings | mcp tool | installer/core/mcp/tools/comment-tools.php | — | Read the current comment system settings and moderation mode |
| klytos_get_consent_audit | mcp tool | installer/core/mcp/tools/consent-tools.php | — | Produce a compliance audit of all consent declarations grouped by category |
| klytos_get_consent_config | mcp tool | installer/core/mcp/tools/consent-tools.php | — | Read Consent Manager configuration such as banner text, privacy URL and categories |
| klytos_get_custom_field | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Retrieve one custom field definition with its type, options and validation rules |
| klytos_get_custom_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Read the HTML content of a custom template file |
| klytos_get_field_types | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | List the 27 supported field types with categories and validation rules |
| klytos_get_field_value | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Read one custom field value from a page or entry |
| klytos_get_guide | mcp tool | installer/core/mcp/tools/guide-tools.php | — | Fetch an AI-facing guide such as gutenberg-blocks or design-patterns |
| klytos_get_maintenance_mode | mcp tool | installer/core/mcp/tools/maintenance-tools.php | — | Check whether maintenance mode is on and read its message |
| klytos_get_menu | mcp tool | installer/core/mcp/tools/menu-tools.php | — | Read the current navigation menu structure |
| klytos_get_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Retrieve a page by slug with all its data and HTML content |
| klytos_get_page_template | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Retrieve a page template by type with its block structure |
| klytos_get_part | mcp tool | installer/core/mcp/tools/part-tools.php | — | Resolve a site part's HTML, source level, slots and data before editing it |
| klytos_get_plugin_assets_status | mcp tool | installer/core/mcp/tools/template-tools.php | — | Report version hashes, existence and sizes of generated plugin JS and CSS |
| klytos_get_post_type | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Retrieve a post type definition with slugs, taxonomies and terms |
| klytos_get_scheduler_status | mcp tool | installer/core/mcp/tools/scheduler-tools.php | — | Report scheduler queue counts, last run time and fallback mode |
| klytos_get_site_config | mcp tool | installer/core/mcp/tools/site-tools.php | — | Read the global site configuration |
| klytos_get_task | mcp tool | installer/core/mcp/tools/task-tools.php | — | Retrieve a task by its ID |
| klytos_get_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Resolve a template's HTML through the custom, plugin, database and core hierarchy |
| klytos_get_template_content_schema | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Describe which blocks a template uses and what data each one needs |
| klytos_get_template_part | mcp tool | installer/core/mcp/tools/template-tools.php | — | Deprecated resolver for template part HTML, superseded by klytos_get_part |
| klytos_get_theme | mcp tool | installer/core/mcp/tools/theme-tools.php | — | Read the current theme configuration of colors, fonts and layout |
| klytos_get_top_pages | mcp tool | installer/core/mcp/tools/analytics-tools.php | — | Rank the most visited pages over a date range |
| klytos_get_translations | mcp tool | installer/core/mcp/tools/translation-tools.php | — | Compare a locale's translation keys against English and flag missing ones |
| klytos_get_version | mcp tool | installer/core/mcp/tools/version-tools.php | — | Retrieve one page version with its full data snapshot |
| klytos_import_analyze_sitemap | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Parse a sitemap and return discovered URLs with suggested slugs and content types |
| klytos_import_analyze_style | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Analyze a site's colors, fonts and layout into a scored Klytos theme mapping |
| klytos_import_analyze_wp_xml | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Summarize a WordPress WXR export's pages, posts, media, menus and authors |
| klytos_import_convert_content | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Convert raw HTML into Gutenberg blocks or clean TinyMCE HTML for the target post type |
| klytos_import_discover_site | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Crawl a site from a start URL and return its page hierarchy, respecting robots.txt |
| klytos_import_download_media | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Download external media into Klytos assets and return a URL map for content rewriting |
| klytos_import_execute_batch | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Import up to 20 pages per call as drafts, tracking progress in the import session |
| klytos_import_fetch_page | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Fetch one page and extract its main content, stripping nav, header, footer and scripts |
| klytos_import_fetch_wp_page | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Extract one page or post by slug from an analyzed WordPress XML export |
| klytos_import_session_status | mcp tool | installer/plugins/klytos-importer/klytos-importer.php | — | Report an import session's progress, errors and per-page status |
| klytos_integrity_check | mcp tool | installer/core/mcp/tools/integrity-tools.php | — | Run a full integrity verification over core files and all installed plugins |
| klytos_integrity_check_plugin | mcp tool | installer/core/mcp/tools/integrity-tools.php | — | Run an integrity check on one plugin by ID |
| klytos_integrity_status | mcp tool | installer/core/mcp/tools/integrity-tools.php | — | Return the last integrity report without re-running verification |
| klytos_list_ai_images | mcp tool | installer/core/mcp/tools/ai-image-tools.php | — | List AI-generated images with their prompts and metadata |
| klytos_list_assets | mcp tool | installer/core/mcp/tools/asset-tools.php | — | List uploaded assets, optionally filtered by directory |
| klytos_list_blocks | mcp tool | installer/core/mcp/tools/block-tools.php | — | List blocks, optionally filtered by category |
| klytos_list_comments | mcp tool | installer/core/mcp/tools/comment-tools.php | — | List comments with author, status and threading info, filterable by status and page |
| klytos_list_consent_declarations | mcp tool | installer/core/mcp/tools/consent-tools.php | — | List plugin cookie and script consent declarations for compliance audit |
| klytos_list_custom_fields | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | List a post type's custom field definitions in display order with full configuration |
| klytos_list_custom_templates | mcp tool | installer/core/mcp/tools/template-tools.php | — | List user custom templates and template parts stored as files |
| klytos_list_guides | mcp tool | installer/core/mcp/tools/guide-tools.php | — | Discover the available AI guides on markup, SEO and accessibility before editing content |
| klytos_list_page_templates | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | List all page templates |
| klytos_list_pages | mcp tool | installer/core/mcp/tools/page-tools.php | — | List pages, optionally filtered by status and language |
| klytos_list_parts | mcp tool | installer/core/mcp/tools/part-tools.php | — | List all site parts from every source with the effective source of each |
| klytos_list_plugins | mcp tool | installer/core/mcp/tools/plugin-tools.php | — | List installed plugins with status, version and metadata |
| klytos_list_post_statuses | mcp tool | installer/core/mcp/tools/post-status-tools.php | — | List system and custom statuses available for a post type |
| klytos_list_post_types | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | List registered post types with their taxonomies |
| klytos_list_scheduled_actions | mcp tool | installer/core/mcp/tools/scheduler-tools.php | — | List scheduled actions, filterable by status, group or hook |
| klytos_list_shortcodes | mcp tool | installer/core/mcp/tools/shortcode-tools.php | — | List registered shortcodes with their tags and descriptions |
| klytos_list_tasks | mcp tool | installer/core/mcp/tools/task-tools.php | — | List review tasks, filterable by status and page |
| klytos_list_template_parts | mcp tool | installer/core/mcp/tools/template-tools.php | — | Deprecated file-only listing of template parts; prefer klytos_list_parts |
| klytos_list_templates | mcp tool | installer/core/mcp/tools/template-tools.php | — | List templates from all four hierarchy levels showing each one's source |
| klytos_list_terms | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | List the terms of a taxonomy within a post type |
| klytos_list_translation_sources | mcp tool | installer/core/mcp/tools/translation-tools.php | — | List translation sources with per-language translation statistics |
| klytos_list_users | mcp tool | installer/core/mcp/tools/user-tools.php | — | List users with roles and status, excluding password hashes |
| klytos_list_versions | mcp tool | installer/core/mcp/tools/version-tools.php | — | List a page's version history metadata, newest first |
| klytos_list_webhook_events | mcp tool | installer/core/mcp/tools/webhook-tools.php | — | List available core and plugin-registered webhook events |
| klytos_list_webhooks | mcp tool | installer/core/mcp/tools/webhook-tools.php | — | List configured webhooks with their status and subscribed events |
| klytos_lock_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Acquire a 5-minute editing lock on a page to prevent concurrent edits |
| klytos_migrate_global_blocks_to_parts | mcp tool | installer/core/mcp/tools/part-tools.php | — | Idempotently migrate global-scope blocks to unified site parts, keeping the originals |
| klytos_moderate_comment | mcp tool | installer/core/mcp/tools/comment-tools.php | — | Approve, mark as spam or trash a single comment |
| klytos_options_classify | mcp tool | installer/core/mcp/tools/option-tools.php | — | Classify stored options as core, active, inactive, orphan or unknown |
| klytos_options_delete_domain | mcp tool | installer/core/mcp/tools/option-tools.php | — | Delete all options of a text domain, requiring explicit confirmation |
| klytos_options_list_by_domain | mcp tool | installer/core/mcp/tools/option-tools.php | — | List all options belonging to a plugin text domain |
| klytos_options_migrate | mcp tool | installer/core/mcp/tools/option-tools.php | — | Migrate legacy options without a text domain by inferring it from the key prefix |
| klytos_permanent_delete_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Irreversibly delete a page from storage without requiring it to be trashed first |
| klytos_preview_block | mcp tool | installer/core/mcp/tools/block-tools.php | — | Render a block with given data and return the resulting HTML |
| klytos_preview_page | mcp tool | installer/core/mcp/tools/build-tools.php | — | Render a page and return the HTML without writing to disk |
| klytos_preview_page_template | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Render a page template with sample or supplied data for preview |
| klytos_rebuild_block | mcp tool | installer/core/mcp/tools/build-tools.php | — | Re-render one global block and patch it across generated HTML without a full rebuild |
| klytos_rebuild_css | mcp tool | installer/core/mcp/tools/build-tools.php | — | Regenerate theme and block CSS files without rebuilding HTML pages |
| klytos_rebuild_plugin_assets | mcp tool | installer/core/mcp/tools/template-tools.php | — | Regenerate the plugin hooks JS and CSS bundles without a full site rebuild |
| klytos_remove_block_from_template | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Remove a block from a page template |
| klytos_remove_custom_field | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Remove a custom field definition while preserving existing stored values |
| klytos_remove_menu_item | mcp tool | installer/core/mcp/tools/menu-tools.php | — | Remove a navigation menu item by ID |
| klytos_remove_post_status | mcp tool | installer/core/mcp/tools/post-status-tools.php | — | Remove a custom status and reassign affected pages to draft |
| klytos_remove_taxonomy | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Delete a taxonomy and all its terms from a post type |
| klytos_reorder_custom_fields | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Reorder a post type's custom fields via a complete list of field IDs |
| klytos_reorder_template_blocks | mcp tool | installer/core/mcp/tools/page-template-tools.php | — | Reorder the blocks of a page template by supplying the new block ID order |
| klytos_reset_user_password | mcp tool | installer/core/mcp/tools/user-tools.php | — | Generate a password reset token and email the reset link to a user |
| klytos_resolve_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Debug template resolution by showing the candidate chain and the matching level |
| klytos_restore_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Restore a trashed page to its previous status |
| klytos_restore_version | mcp tool | installer/core/mcp/tools/version-tools.php | — | Overwrite a page with a previous version and record a new version entry |
| klytos_run_site_health | mcp tool | installer/core/mcp/tools/site-health-tools.php | — | Run site health checks and return a 0-100 score with per-category results |
| klytos_schedule_recurring_action | mcp tool | installer/core/mcp/tools/scheduler-tools.php | — | Schedule an action to repeat at a fixed interval |
| klytos_schedule_single_action | mcp tool | installer/core/mcp/tools/scheduler-tools.php | — | Schedule a one-time action for a given timestamp |
| klytos_set_bulk_field_values | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Set many validated custom field values on one entry in a single call |
| klytos_set_colors | mcp tool | installer/core/mcp/tools/theme-tools.php | — | Update only the theme color palette, leaving other settings untouched |
| klytos_set_comment_settings | mcp tool | installer/core/mcp/tools/comment-tools.php | — | Configure the comment system: enabling, moderation, threading depth and anti-spam |
| klytos_set_consent_config | mcp tool | installer/core/mcp/tools/consent-tools.php | — | Update the Consent Manager configuration and trigger a site rebuild |
| klytos_set_custom_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Create or update a file-based custom page template as a complete HTML document |
| klytos_set_custom_template_part | mcp tool | installer/core/mcp/tools/template-tools.php | — | Create a file template part that shadows stored parts, for permanent user overrides |
| klytos_set_field_value | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Set one custom field value on an entry after validating it against its definition |
| klytos_set_fonts | mcp tool | installer/core/mcp/tools/theme-tools.php | — | Update only the font configuration, leaving other theme settings untouched |
| klytos_set_global_block_data | mcp tool | installer/core/mcp/tools/block-tools.php | — | Set the shared data of a global-scope block so it applies to all pages |
| klytos_set_layout | mcp tool | installer/core/mcp/tools/theme-tools.php | — | Update only the layout configuration, leaving other theme settings untouched |
| klytos_set_maintenance_mode | mcp tool | installer/core/mcp/tools/maintenance-tools.php | — | Toggle maintenance mode, serving visitors a 503 page while admin stays reachable |
| klytos_set_menu | mcp tool | installer/core/mcp/tools/menu-tools.php | — | Replace the navigation menu with a complete new structure |
| klytos_set_part | mcp tool | installer/core/mcp/tools/part-tools.php | — | Create or update a site part with free HTML/CSS, rendered on every page referencing it |
| klytos_set_part_data | mcp tool | installer/core/mcp/tools/part-tools.php | — | Update only a site part's slot values without touching its HTML |
| klytos_set_site_config | mcp tool | installer/core/mcp/tools/site-tools.php | — | Update global site configuration: name, tagline, language, SEO, social and analytics |
| klytos_set_template | mcp tool | installer/core/mcp/tools/template-tools.php | — | Create or update a database-stored HTML template using placeholder variables |
| klytos_set_theme | mcp tool | installer/core/mcp/tools/theme-tools.php | — | Set the whole theme configuration: colors, fonts, layout and custom CSS |
| klytos_start_site_builder | mcp tool | installer/core/mcp/tools/site-builder-tools.php | — | Return the 9-phase guided walkthrough for building a complete website from scratch |
| klytos_test_webhook | mcp tool | installer/core/mcp/tools/webhook-tools.php | — | Send a test event to a webhook to verify delivery |
| klytos_translate | mcp tool | installer/core/mcp/tools/translation-tools.php | — | Save translations for a source and locale, preserving HTML tags and placeholders |
| klytos_translate_with_ai | mcp tool | installer/core/mcp/tools/translation-tools.php | — | Auto-translate missing keys for a locale using a configured AI provider |
| klytos_unlock_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Release a page's editing lock |
| klytos_update_block | mcp tool | installer/core/mcp/tools/block-tools.php | — | Update selected fields of an existing block |
| klytos_update_custom_field | mcp tool | installer/core/mcp/tools/custom-field-tools.php | — | Update selected properties of a custom field definition within a post type |
| klytos_update_page | mcp tool | installer/core/mcp/tools/page-tools.php | — | Update selected fields of an existing page |
| klytos_update_post_status | mcp tool | installer/core/mcp/tools/post-status-tools.php | — | Update a custom post status definition; system statuses are immutable |
| klytos_update_post_type | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Update a custom post type's name, slug, i18n slugs, taxonomies or custom statuses |
| klytos_update_task | mcp tool | installer/core/mcp/tools/task-tools.php | — | Update selected fields of a review task |
| klytos_update_taxonomy | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Update a taxonomy's name, slug, hierarchical mode or i18n slugs |
| klytos_update_term | mcp tool | installer/core/mcp/tools/post-type-tools.php | — | Update a taxonomy term identified by its current slug |
| klytos_update_user | mcp tool | installer/core/mcp/tools/user-tools.php | — | Update selected fields of an existing user, including the password |
| klytos_upload_asset | mcp tool | installer/core/mcp/tools/asset-tools.php | — | Upload a base64-encoded file such as an image, CSS, JS or font |
| klytos_x402_bulk_set_status | mcp tool | installer/core/x402-mcp-tools.php | — | Enable or disable x402 payment protection across many pages at once |
| klytos_x402_get_config | mcp tool | installer/core/x402-mcp-tools.php | — | Read the global x402 configuration: provider, wallet, pricing and bot detection |
| klytos_x402_get_page_status | mcp tool | installer/core/x402-mcp-tools.php | — | Get a page's x402 protection status with its effective cascade-resolved settings |
| klytos_x402_get_stats | mcp tool | installer/core/x402-mcp-tools.php | — | Report x402 revenue for day, week, month and total, broken down by provider |
| klytos_x402_list_providers | mcp tool | installer/core/x402-mcp-tools.php | — | List registered x402 payment providers, their capabilities and the active one |
| klytos_x402_list_transactions | mcp tool | installer/core/x402-mcp-tools.php | — | List recent x402 payment transactions with optional date, slug and provider filters |
| klytos_x402_set_config | mcp tool | installer/core/x402-mcp-tools.php | — | Update global x402 settings: wallet address, pricing, network and active provider |
| klytos_x402_set_page_status | mcp tool | installer/core/x402-mcp-tools.php | — | Toggle x402 payment protection on a page so AI bots must pay to access it |

## HTTP routes
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| /mcp | route | installer/core/router.php | — | JSON-RPC MCP server entry point; handles GET (SSE), POST (calls) and OPTIONS preflight |
| /oauth/authorize | route | installer/core/router.php | — | OAuth 2.1 authorization screen where an MCP client obtains user consent |
| /oauth/token | route | installer/core/router.php | — | OAuth token exchange and refresh endpoint for MCP clients |
| /.well-known/oauth-authorization-server | route | installer/core/router.php | — | Serves OAuth authorization-server metadata for client auto-discovery |
| /cron | route | installer/core/router.php | — | Web-triggered scheduler tick that runs due scheduled actions |
| /install | route | installer/core/router.php | — | First-run installation wizard, disabled once the site is installed |
| /t | route | installer/core/router.php | — | Analytics tracking pixel that records a page view |
| /t.php | route | installer/core/router.php | — | Legacy alias of the analytics tracking pixel |
| (dynamic routes) | route | installer/core/router.php | — | Falls through to RouteManager::match for plugin routes of type api, webhook or page |
| (static fallback) | route | installer/core/router.php | — | Serves the generated static site from public/, resolving .html and directory index files |
| /admin/api/ai-chat.php | endpoint | installer/admin/api/ai-chat.php | — | Backs the built-in admin AI chat, relaying prompts to the configured provider |
| /admin/api/assets-management.php | endpoint | installer/admin/api/assets-management.php | — | Manages asset metadata, categories, usage tracking, sync and cleanup |
| /admin/api/autosave.php | endpoint | installer/admin/api/autosave.php | — | Persists editor content every 60 seconds to protect against lost work |
| /admin/api/download-identity.php | endpoint | installer/admin/api/download-identity.php | — | Serves the klytos-identity.pem private key under elevated access checks |
| /admin/api/image-edit.php | endpoint | installer/admin/api/image-edit.php | — | Applies crop, rotate, flip and resize operations to an image via GD |
| /admin/api/inline-edit.php | endpoint | installer/admin/api/inline-edit.php | — | Receives content edits made through the frontend inline editor |
| /admin/api/integrity.php | endpoint | installer/admin/api/integrity.php | — | Runs file-hash integrity verification and returns per-file results |
| /admin/api/logs.php | endpoint | installer/admin/api/logs.php | — | Lists, reads and deletes system log files |
| /admin/api/media-upload.php | endpoint | installer/admin/api/media-upload.php | — | Accepts editor file uploads and returns a Gutenberg-shaped media object |
| /admin/api/notices.php | endpoint | installer/admin/api/notices.php | — | Lists admin notices and records per-user dismissals |
| /admin/api/oembed.php | endpoint | installer/admin/api/oembed.php | — | Proxies oEmbed provider lookups so the editor can preview embedded media |
| /admin/api/options-management.php | endpoint | installer/admin/api/options-management.php | — | Lists, deletes and migrates stored options, including whole-domain deletion |
| /admin/api/plugins.php | endpoint | installer/admin/api/plugins.php | — | Activates, deactivates, deletes, uninstalls, installs from ZIP and restores plugins |
| /admin/api/post-lock.php | endpoint | installer/admin/api/post-lock.php | — | Acquires, renews, releases, checks and takes over concurrent editing locks |
| /admin/api/sidebar-order.php | endpoint | installer/admin/api/sidebar-order.php | — | Saves, reads and resets each user's custom sidebar menu order |
| /admin/api/tasks.php | endpoint | installer/admin/api/tasks.php | — | Creates, updates and lists tasks from the review widget and admin panel |
| /admin/api/terminal-autocomplete.php | endpoint | installer/admin/api/terminal-autocomplete.php | — | Returns command-name matches for Tab completion in the web terminal |
| /admin/api/terminal-revalidate.php | endpoint | installer/admin/api/terminal-revalidate.php | — | Verifies a 2FA code to resume a terminal session after inactivity |
| /admin/api/terminal.php | endpoint | installer/admin/api/terminal.php | — | Executes a terminal command and returns its output; requires session plus 2FA |
| /admin/api/translations-ai.php | endpoint | installer/admin/api/translations-ai.php | — | Translates supplied strings through a configured AI provider |
| /admin/api/translations.php | endpoint | installer/admin/api/translations.php | — | Saves individual translation strings from the translations screen |
| /admin/api/update-install.php | endpoint | installer/admin/api/update-install.php | — | Drives core update installation so the UI can show a progress overlay |
| /admin/api/webauthn-challenge.php | endpoint | installer/admin/api/webauthn-challenge.php | — | Issues and verifies passkey registration and authentication challenges |
| /comment-submit.php | endpoint | installer/public/comment-submit.php | docs/reference/public-comments.md | Anonymous comment submission, at the WEB ROOT so no admin directory name reaches a public URL |

## Terminal / CLI commands
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| build | command | installer/core/terminal-executor.php | — | Regenerate the whole static site; requires build.run |
| build:page | command | installer/core/terminal-executor.php | — | Regenerate a single page identified by slug; requires build.run |
| pages | command | installer/core/terminal-executor.php | — | List pages, optionally narrowed with --status; requires pages.view |
| pages:count | command | installer/core/terminal-executor.php | — | Report how many pages exist in each status; requires pages.view |
| tasks | command | installer/core/terminal-executor.php | — | List tasks, optionally narrowed with --status; requires tasks.manage |
| tasks:count | command | installer/core/terminal-executor.php | — | Report the total number of tasks; requires tasks.manage |
| status | command | installer/core/terminal-executor.php | — | Summarise overall system state; requires site.configure |
| version | command | installer/core/terminal-executor.php | — | Print the installed Klytos release; no permission required |
| cache:clear | command | installer/core/terminal-executor.php | — | Purge rate-limit and cron caches; requires site.configure |
| cron:run | command | installer/core/terminal-executor.php | — | Execute every due scheduled action immediately; requires site.configure |
| users | command | installer/core/terminal-executor.php | — | List admin panel accounts; requires users.manage |
| plugins | command | installer/core/terminal-executor.php | — | List installed plugins with activation state; requires plugins.manage |
| plugins:activate | command | installer/core/terminal-executor.php | — | Activate a plugin by its ID; requires plugins.manage |
| plugins:deactivate | command | installer/core/terminal-executor.php | — | Deactivate a plugin by its ID; requires plugins.manage |
| analytics | command | installer/core/terminal-executor.php | — | Summarise traffic over a period selected with --period; requires analytics.view |
| help | command | installer/core/terminal-executor.php | — | List commands by category, or detail one command; no permission required |
| clear | command | installer/core/terminal-executor.php | — | Wipe the terminal screen buffer; no permission required |
| backup:create | command | installer/core/terminal-executor.php | — | Take a manual system backup, optionally labelled; requires site.configure |
| backup:list | command | installer/core/terminal-executor.php | — | Show the available backups; requires site.configure |
| backup:restore | command | installer/core/terminal-executor.php | — | Restore the system from a named backup; requires site.configure |
| update:check | command | installer/core/terminal-executor.php | — | Query whether a newer Klytos release is available; requires site.configure |
| update:run | command | installer/core/terminal-executor.php | — | Download and install the latest release; requires site.configure |
| config:get | command | installer/core/terminal-executor.php | — | Print the stored value of one configuration key; requires site.configure |
| config:set | command | installer/core/terminal-executor.php | — | Write a value into one configuration key; requires site.configure |
| logs | command | installer/core/terminal-executor.php | — | Read system log entries, filtered by --date and --lines; requires site.configure |
| webhooks | command | installer/core/terminal-executor.php | — | List the configured outbound webhooks; requires site.configure |

## Plugin extension contracts
| Surface | Kind | Code file | Doc | Purpose (one line) |
|---------|------|-----------|-----|--------------------|
| `Klytos\Core\X402\Providers\PaymentProviderInterface` | interface | installer/core/x402/providers/payment-provider-interface.php | — | Contract a payment plugin implements to settle x402 micropayments: advertises networks and assets, builds payment requirements, verifies a payment header |
| `Klytos\Core\StorageInterface` | interface | installer/core/storage-interface.php | — | Contract a storage backend implements so plugins persist records through file or database drivers interchangeably |
| `Klytos\Core\CacheInterface` | interface | installer/core/cache-interface.php | — | Contract a cache driver implements to back klytos_cache() with APCu, file, Redis or Memcached |
| {plugin-id}.php | entry point | installer/plugins/{plugin-id}/{plugin-id}.php | — | Mandatory bootstrap file whose name matches the directory; loaded by PluginLoader with a PHP plugin header |
| klytos-plugin.json | manifest | installer/plugins/{plugin-id}/klytos-plugin.json | — | Optional manifest extending the PHP header with richer metadata, requirements and capability declarations |
| install.php / deactivate.php / uninstall.php | lifecycle hook | installer/plugins/klytos-importer/install.php | — | Optional lifecycle scripts PluginLoader runs on activation, deactivation and uninstall |
| klytos_register_route() | registration helper | installer/core/helpers-global.php | — | Attach a plugin URL pattern of type api, webhook or page to the dynamic router |
| klytos_register_admin_page() | registration helper | installer/core/helpers-global.php | — | Add a plugin-owned screen to the admin panel, with slug, title and render callback |
| klytos_register_templates() | registration helper | installer/core/helpers-global.php | — | Contribute page templates the build engine can resolve for a plugin |
| klytos_register_template_part() | registration helper | installer/core/helpers-global.php | — | Bind a callback that renders markup at a named template part slot |
| klytos_register_translations() | registration helper | installer/core/helpers-global.php | — | Point a plugin text domain at its .po/.mo language directory |
| klytos_register_option() | registration helper | installer/core/helpers-global.php | — | Declare an option key with its sensitivity and metadata so it is classified and purgeable |
| klytos_register_dashboard_widget() | registration helper | installer/core/helpers-global.php | — | Place a plugin widget on the admin dashboard with position and capability gating |
| klytos_add_action() | registration helper | installer/core/helpers-global.php | — | Subscribe a plugin callback to any core action hook at a given priority |
| klytos_add_filter() | registration helper | installer/core/helpers-global.php | — | Subscribe a plugin callback that rewrites a filtered value at a given priority |
| mcp.tools_list | extension filter | installer/plugins/klytos-forms/klytos-forms.php | — | Filter through which a plugin appends its own tool definitions to the advertised MCP tool list |
| mcp.handle_tool | extension filter | installer/plugins/klytos-forms/klytos-forms.php | — | Filter through which a plugin claims and executes an MCP tool call, short-circuiting core |
| x402.payment_providers | extension filter | installer/plugins/klytos-x402-stripe/klytos-x402-stripe.php | — | Filter through which a plugin appends a PaymentProviderInterface instance to the provider registry |
| admin.sidebar_items | extension filter | installer/plugins/klytos-forms/klytos-forms.php | — | Filter through which a plugin inserts its own entries into the admin sidebar menu |
