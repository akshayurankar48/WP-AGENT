# Security Model

Security is enforced at every layer of JARVIS AI -- from API key storage to action execution.

## API Key Encryption

All provider API keys are encrypted at rest:

- **Algorithm:** AES-256-CBC via `openssl_encrypt()`
- **Key derivation:** SHA-256 hash of WordPress `AUTH_KEY` constant
- **IV derivation:** First 16 bytes of MD5 hash of `AUTH_SALT` constant
- **Storage:** Base64-encoded ciphertext in `wp_options`
- **Decryption:** On-the-fly when making API calls, never cached in memory

Keys are never logged, never included in error responses, and never sent to any endpoint other than the configured provider.

## Authentication

### REST API Nonces

Every REST endpoint requires a valid `X-WP-Nonce` header:

- Nonce is generated via `wp_create_nonce( 'wp_rest' )`
- Localized to the frontend via the `jarvisAiData` script object
- Validated automatically by the WordPress REST API infrastructure

**Gotcha:** The `jarvisAiData` nonce is only localized on admin pages. It does not work on frontend pages.

### Capability Checks

Two-tier permission model:

1. **Endpoint-level:** Every REST endpoint checks a WordPress capability (`edit_posts` or `manage_options`)
2. **Action-level:** Each of the 77 actions declares its own required capability via `get_capability()`. The orchestrator verifies `current_user_can()` before executing any action.

### Role-Based Access

- The `jarvis_ai_allowed_roles` option controls which roles can access JARVIS
- `administrator` is always included and cannot be removed
- `REST_Permissions::current_user_has_allowed_role()` checks both capability and role

## Rate Limiting

Per-user rate limiting prevents abuse:

| Limit | Default | Scope |
|-------|---------|-------|
| Per-minute | 30 requests | Per user ID |
| Per-day | 500 requests | Per user ID |
| A/B tracking | 30 events/min | Per IP address |

Rate limiter uses WordPress transients (in-memory, not distributed). Returns `429` with `Retry-After` header when exceeded.

## Input Sanitization

All user input is sanitized before use:

| Function | Used For |
|----------|----------|
| `sanitize_text_field()` | General text input |
| `sanitize_key()` | Slugs, option names |
| `absint()` | Integer IDs |
| `esc_url_raw()` | URLs for storage |
| `wp_kses_post()` | HTML content |
| `sanitize_file_name()` | File paths |

## Output Escaping

All output is escaped at render time:

| Function | Used For |
|----------|----------|
| `esc_html()` | Plain text in HTML |
| `esc_attr()` | HTML attribute values |
| `esc_url()` | URLs in href/src |
| `wp_kses_post()` | Rich HTML content |

## SQL Injection Prevention

All direct database queries use `$wpdb->prepare()` with parameterized placeholders. No raw variable interpolation in SQL.

## SSRF Protection

External HTTP requests use `wp_safe_remote_get()` and `wp_safe_remote_post()` which block requests to internal/private IP ranges.

## File Operations

- Paths are validated and sanitized before file operations
- `wp_delete_file()` used instead of `unlink()`
- Export files are scheduled for cleanup via `wp_schedule_single_event()`
- No direct filesystem writes outside the uploads directory

## Checkpoint Security

Action checkpoints (undo/rollback) verify:

1. The checkpoint belongs to a conversation owned by the current user
2. The checkpoint has not already been restored (`is_restored` flag)
3. The user has the capability for the original action type

## See Also

- [AI Providers](AI-Providers) -- API key encryption details
- [REST API Reference](REST-API-Reference) -- Endpoint permissions
- [Action Catalog](Action-Catalog) -- Per-action capabilities
