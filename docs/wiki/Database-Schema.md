# Database Schema

JARVIS AI uses 6 custom tables prefixed with `{wpdb_prefix}agent_`. Tables are created via `dbDelta()` on activation and upgraded automatically via version comparison on `admin_init`.

## Migration System

- Schema version stored in `jarvis_ai_db_version` option
- Current version: `1.1.0`
- `Database::maybe_upgrade()` runs on `admin_init`, compares stored version with `SCHEMA_VERSION`
- `dbDelta()` is idempotent -- safe to re-run, adds missing columns automatically

## Tables

### agent_conversations

Chat sessions. One row per conversation, scoped to a user and optionally a post.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint(20) unsigned | AUTO_INCREMENT | Primary key |
| `user_id` | bigint(20) unsigned | -- | WordPress user ID |
| `post_id` | bigint(20) unsigned | NULL | Associated post (optional) |
| `title` | varchar(255) | `''` | Conversation title |
| `status` | varchar(20) | `'active'` | Status: active, archived |
| `model` | varchar(100) | `''` | AI model used |
| `tokens_used` | int(10) unsigned | `0` | Total tokens consumed |
| `created_at` | datetime | CURRENT_TIMESTAMP | Creation time |
| `updated_at` | datetime | CURRENT_TIMESTAMP | Last update time |

**Indexes:** `idx_user_id`, `idx_post_id`, `idx_status`

### agent_messages

Individual messages within a conversation.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint(20) unsigned | AUTO_INCREMENT | Primary key |
| `conversation_id` | bigint(20) unsigned | -- | FK to conversations |
| `role` | varchar(20) | `'user'` | user, assistant, system, tool |
| `content` | longtext | -- | Message content |
| `metadata` | longtext | NULL | JSON: tool calls, action plans |
| `tokens` | int(10) unsigned | `0` | Token count for this message |
| `model` | varchar(100) | `''` | Model that generated this message |
| `created_at` | datetime | CURRENT_TIMESTAMP | Creation time |

**Indexes:** `idx_conversation_id`, `idx_role`

### agent_checkpoints

Pre/post action snapshots for undo/rollback.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint(20) unsigned | AUTO_INCREMENT | Primary key |
| `conversation_id` | bigint(20) unsigned | -- | FK to conversations |
| `message_id` | bigint(20) unsigned | -- | FK to messages |
| `action_type` | varchar(100) | `''` | Action slug (e.g. `edit_post`) |
| `snapshot_before` | longtext | -- | State before action (JSON) |
| `snapshot_after` | longtext | NULL | State after action (JSON) |
| `entity_type` | varchar(50) | `''` | post, plugin, user, etc. |
| `entity_id` | bigint(20) unsigned | NULL | WordPress entity ID |
| `is_restored` | tinyint(1) | `0` | Whether undo was performed |
| `created_at` | datetime | CURRENT_TIMESTAMP | Creation time |

**Indexes:** `idx_conversation_id`, `idx_message_id`

### agent_history

Audit log of all executed actions.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint(20) unsigned | AUTO_INCREMENT | Primary key |
| `user_id` | bigint(20) unsigned | -- | WordPress user ID |
| `conversation_id` | bigint(20) unsigned | NULL | FK to conversations |
| `action_type` | varchar(100) | `''` | Action slug |
| `action_data` | longtext | NULL | JSON parameters |
| `result_status` | varchar(20) | `''` | success, error |
| `result_message` | text | -- | Human-readable result |
| `created_at` | datetime | CURRENT_TIMESTAMP | Creation time |

**Indexes:** `idx_user_id`, `idx_conversation_id`, `idx_action_type`

### agent_scheduled_tasks

Scheduled/recurring action chains for automation.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint(20) unsigned | AUTO_INCREMENT | Primary key |
| `name` | varchar(255) | `''` | Task display name |
| `action_chain` | longtext | -- | JSON array of actions |
| `schedule` | varchar(50) | `'daily'` | Recurrence: daily, weekly, etc. |
| `next_run` | datetime | NULL | Next scheduled execution |
| `last_run` | datetime | NULL | Last execution time |
| `status` | varchar(20) | `'active'` | active, paused, deleted |
| `created_by` | bigint(20) unsigned | -- | WordPress user ID |
| `created_at` | datetime | CURRENT_TIMESTAMP | Creation time |

**Indexes:** `idx_status`, `idx_next_run`

### agent_memory

Key-value memory pairs for cross-conversation context persistence.

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| `id` | bigint(20) unsigned | AUTO_INCREMENT | Primary key |
| `memory_key` | varchar(255) | `''` | Unique memory identifier |
| `memory_value` | longtext | -- | Stored value |
| `category` | varchar(100) | `'general'` | Category grouping |
| `relevance_score` | float | `1.0` | Relevance weight |
| `created_at` | datetime | CURRENT_TIMESTAMP | Creation time |
| `updated_at` | datetime | CURRENT_TIMESTAMP | Last update time |

**Indexes:** `idx_memory_key` (UNIQUE), `idx_category`

## Cleanup

On uninstall (`uninstall.php`), `Database::drop_tables()` drops all 6 tables and deletes the `jarvis_ai_db_version` option.

## See Also

- [Architecture Overview](Architecture-Overview)
- [AI Orchestrator](AI-Orchestrator) -- How conversations and messages are created
