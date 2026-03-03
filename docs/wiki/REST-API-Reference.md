# REST API Reference

All endpoints use the `jarvis-ai/v1` namespace. Base URL: `/wp-json/jarvis-ai/v1/`.

## Authentication

Every request must include the `X-WP-Nonce` header with a valid WordPress REST nonce. The nonce is localized via the `jarvisAiData` script object on admin pages.

```
X-WP-Nonce: <wp_rest nonce>
```

Unauthenticated requests receive a `401` response. Requests from users lacking the required capability receive `403`.

## Permission Model

Endpoints use two permission tiers:

- **edit_posts + allowed role** -- The user must have the `edit_posts` capability AND hold a role listed in the `jarvis_ai_allowed_roles` option (default: `administrator`). Checked via `REST_Permissions::current_user_has_allowed_role()`.
- **manage_options** -- Administrator-only. Standard WordPress capability check.
- **public** -- No authentication required (rate-limited by IP).

---

## Endpoints

### 1. POST /chat

**Controller:** `Chat_Controller`
**Permission:** `edit_posts` + allowed role

Send a message to the AI orchestrator and receive the full response synchronously.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | Yes | User message text. Cannot be empty. |
| `conversation_id` | integer | No | Existing conversation ID to continue. |
| `post_id` | integer | No | Associated post ID for editor context. |
| `model` | string | No | Override model ID (e.g. `anthropic/claude-sonnet-4`). |

**Response (200):**

```json
{
  "conversation_id": 42,
  "content": "Here is my response...",
  "actions_taken": [
    { "name": "create_post", "params": {}, "result": {} }
  ],
  "model": "anthropic/claude-sonnet-4",
  "usage": {
    "prompt_tokens": 1200,
    "completion_tokens": 450,
    "total_tokens": 1650
  }
}
```

**Error Codes:** `400` empty_message, `403` rest_forbidden, `429` rate_limit_exceeded, `500` db_error / api_error.

---

### 2. POST /stream

**Controller:** `Stream_Controller`
**Permission:** `edit_posts` + allowed role

Opens an SSE (Server-Sent Events) connection and streams AI response chunks in real time.

**Parameters:** Same as `/chat`.

**Response:** SSE stream with `Content-Type: text/event-stream`. Each frame is a JSON-encoded line:

```
data: {"type":"content","content":"Hello"}
data: {"type":"progress","stage":"action_start","action":"create_post","index":1,"total":2}
data: {"type":"progress","stage":"action_complete","action":"create_post","index":1,"total":2,"success":true}
data: {"type":"action","action":"insert_blocks","data":{...}}
data: {"type":"done","conversation_id":42}
data: {"type":"error","message":"Rate limit exceeded","code":"rate_limit_exceeded"}
data: [DONE]
```

**SSE Event Types:**

| Type | Description |
|------|-------------|
| `content` | Text content chunk from the AI. |
| `tool_call` | Internal -- buffered by the stream controller, not forwarded. |
| `progress` | Action execution progress (`action_start` / `action_complete`). |
| `action` | Client-side action data (e.g. `insert_blocks` with `execution: "client"`). |
| `error` | Error message. |
| `done` | Stream complete with `conversation_id`. |

---

### 3. GET /history

**Controller:** `History_Controller`
**Permission:** `edit_posts` + allowed role

Returns a paginated list of the current user's conversations. All queries are scoped to the authenticated user.

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | 1 | Page number. |
| `per_page` | integer | No | 20 | Items per page (max 100). |
| `post_id` | integer | No | -- | Filter by associated post. |

**Response (200):**

```json
{
  "conversations": [
    {
      "id": 42,
      "post_id": 101,
      "title": "Create a landing page",
      "status": "active",
      "model": "anthropic/claude-sonnet-4",
      "tokens_used": 5200,
      "created_at": "2026-03-01 10:00:00",
      "updated_at": "2026-03-01 10:05:00"
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20,
  "total_pages": 1
}
```

**Response Headers:** `X-WP-Total`, `X-WP-TotalPages`.

---

### 4. GET /history/{id}

**Controller:** `History_Controller`
**Permission:** `edit_posts` + allowed role

Returns a single conversation with all its messages. Verifies the current user owns the conversation.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Conversation ID. |

**Response (200):**

```json
{
  "conversation": {
    "id": 42,
    "post_id": 101,
    "title": "Create a landing page",
    "status": "active",
    "model": "anthropic/claude-sonnet-4",
    "tokens_used": 5200,
    "created_at": "2026-03-01 10:00:00",
    "updated_at": "2026-03-01 10:05:00"
  },
  "messages": [
    {
      "id": 1,
      "role": "user",
      "content": "Build me a landing page",
      "metadata": null,
      "tokens": 0,
      "model": "",
      "created_at": "2026-03-01 10:00:00"
    }
  ]
}
```

**Error Codes:** `404` not_found, `403` forbidden (not owner).

---

### 5. DELETE /history/{id}

**Controller:** `History_Controller`
**Permission:** `edit_posts` + allowed role

Deletes a single conversation and all its messages. Verifies ownership.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Conversation ID. |

**Response (200):**

```json
{ "deleted": true, "id": 42 }
```

**Error Codes:** `404` not_found, `403` forbidden.

---

### 6. POST /history/bulk-delete

**Controller:** `History_Controller`
**Permission:** `edit_posts` + allowed role

Deletes multiple conversations at once. Verifies the current user owns all specified IDs.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ids` | array of integers | Yes | Conversation IDs. Non-empty, max 50. |

**Response (200):**

```json
{ "deleted": true, "count": 3 }
```

**Error Codes:** `400` invalid_ids / too_many_ids, `403` forbidden.

---

### 7. POST /history/{id}/rename

**Controller:** `History_Controller`
**Permission:** `edit_posts` + allowed role

Renames a conversation. Title is truncated to 255 characters.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Conversation ID. |
| `title` | string | Yes | New title. Cannot be empty. |

**Response (200):**

```json
{ "id": 42, "title": "My new title" }
```

**Error Codes:** `400` empty_title, `404` not_found, `403` forbidden.

---

### 8. GET /settings

**Controller:** `Settings_Controller`
**Permission:** `manage_options`

Returns current plugin configuration.

**Response (200):**

```json
{
  "has_api_key": true,
  "has_tavily_key": false,
  "default_model": "openai/gpt-4o-mini",
  "allowed_roles": ["administrator"],
  "brand": {},
  "rate_limit": 30,
  "daily_limit": 500,
  "ai_backend": "openrouter",
  "configured_providers": { "anthropic": false, "openai": false, "google": false },
  "preferred_provider": ["anthropic", "openai", "google"]
}
```

---

### 9. POST /settings

**Controller:** `Settings_Controller`
**Permission:** `manage_options`

Updates plugin settings. Only provided fields are modified.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `api_key` | string | No | OpenRouter API key. Validated and encrypted. Empty to clear. |
| `tavily_api_key` | string | No | Tavily API key (must start with `tvly-`). |
| `default_model` | string | No | Default model ID. Validated against known models. |
| `allowed_roles` | array | No | WordPress role slugs. `administrator` always included. |
| `brand` | object | No | Brand presets (brand_name, tagline, colors, tone, font_preference). |
| `ai_backend` | string | No | `openrouter` or `providers`. |
| `anthropic_api_key` | string | No | Direct Anthropic API key. Validated and encrypted. |
| `openai_api_key` | string | No | Direct OpenAI API key. Validated and encrypted. |
| `google_api_key` | string | No | Direct Google API key. Validated and encrypted. |
| `preferred_provider` | array | No | Provider priority order (e.g. `["anthropic","openai","google"]`). |

**Response (200):**

```json
{ "success": true, "updated": { "api_key": true, "default_model": "anthropic/claude-sonnet-4" } }
```

**Error Codes:** `400` invalid_model / invalid_roles / invalid_tavily_key, `500` encryption_failed.

---

### 10. POST /verify-provider

**Controller:** `Settings_Controller`
**Permission:** `manage_options`

Validates a direct provider API key without saving it.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `provider` | string | Yes | One of: `anthropic`, `openai`, `google`. |
| `api_key` | string | Yes | API key to validate. |

**Response (200):**

```json
{ "success": true, "provider": "anthropic", "message": "Anthropic API key is valid." }
```

---

### 11. POST /action/execute

**Controller:** `Action_Controller`
**Permission:** `edit_posts` + allowed role (baseline). Per-action capability checks run inside dispatch.

Dispatches an action through the Action Registry outside of a chat flow.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Action slug (e.g. `create_post`). |
| `params` | object | No | Action parameters. Default `{}`. |

**Response (200):** Action-specific result object with `success`, `data`, and `message` fields.

**Error Codes:** `400` action-specific errors, `403` rest_forbidden / insufficient capability.

---

### 12. POST /action/undo

**Controller:** `Action_Controller`
**Permission:** `edit_posts` + allowed role

Marks a checkpoint as restored. Verifies the checkpoint belongs to a conversation owned by the current user.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `checkpoint_id` | integer | Yes | Checkpoint ID to restore. |

**Response (200):**

```json
{ "success": true, "checkpoint_id": 7, "action_type": "edit_post" }
```

**Error Codes:** `404` not_found, `403` forbidden, `409` already_restored, `500` db_error.

---

### 13. POST /ab-track

**Controller:** `Ab_Tracking_Controller`
**Permission:** Public (no auth required)

Records an A/B test impression or click event. Rate limited to 30 events per IP per minute.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `test_id` | string | Yes | Test identifier (alphanumeric + underscores, max 64 chars). |
| `event` | string | Yes | `impression` or `click`. |
| `variant` | string | Yes | `a` or `b`. |

**Response (200):**

```json
{ "success": true }
```

**Error Codes:** `400` invalid test_id, `404` test not found or inactive, `429` rate limited.

---

### 14. GET /ai-pulse

**Controller:** `AI_Pulse_Controller`
**Permission:** `manage_options`

Returns aggregated AI news feed items from RSS sources (OpenAI, Anthropic, The Verge, TechCrunch, YouTube channels). Cached for 12 hours.

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `refresh` | boolean | No | false | Force cache refresh. |

**Response (200):**

```json
{
  "items": [
    {
      "title": "Article title",
      "link": "https://...",
      "summary": "Truncated summary...",
      "published": "2026-03-01T10:00:00+00:00",
      "source": "OpenAI",
      "type": "blog",
      "icon": "openai"
    }
  ],
  "fetched_at": "2026-03-01T12:00:00+00:00",
  "sources": 7
}
```

---

### 15. GET /schedules

**Controller:** `Schedules_Controller`
**Permission:** `manage_options`

Returns all scheduled tasks with their status and next run time.

**Response (200):**

```json
[
  {
    "id": "daily_backup",
    "name": "Daily Backup",
    "schedule": "daily",
    "action_count": 2,
    "actions": ["export_site", "optimize_performance"],
    "status": "active",
    "next_run": "2026-03-04 00:00:00",
    "last_run": "2026-03-03 00:00:00",
    "created_at": "2026-02-15 09:00:00"
  }
]
```

---

### 16. POST /schedules/{task_id}/{action}

**Controller:** `Schedules_Controller`
**Permission:** `manage_options`

Pause, resume, or delete a scheduled task.

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `task_id` | string | Yes | Task identifier (alphanumeric + underscores). |
| `action` | string | Yes | One of: `pause`, `resume`, `delete`. |

**Response (200):**

```json
{ "success": true, "message": "Task paused successfully." }
```

**Error Codes:** `400` jarvis_ai_schedule_error.

---

### 17. GET /stats

**Controller:** `Stats_Controller`
**Permission:** `manage_options`

Returns aggregate dashboard statistics for the current user.

**Response (200):**

```json
{
  "total_actions": 77,
  "conversations": 15,
  "actions_executed": 230,
  "schedules_active": 2,
  "memory_entries": 5,
  "total_tokens": 125000,
  "requests_today": 12,
  "recent_activity": [
    {
      "action": "create_post",
      "status": "success",
      "created_at": "2026-03-03 14:30:00",
      "conversation_id": 42,
      "conversation_title": "Build landing page"
    }
  ]
}
```

---

## Error Response Format

All error responses follow the WordPress REST API standard:

```json
{
  "code": "error_code",
  "message": "Human-readable error message.",
  "data": { "status": 403 }
}
```

## See Also

- [Action-Catalog](Action-Catalog) -- Complete list of AI-callable actions
- [AI-Orchestrator](AI-Orchestrator) -- Message lifecycle and tool loop details
- [Security-Model](Security-Model) -- Authentication, rate limiting, and access control
