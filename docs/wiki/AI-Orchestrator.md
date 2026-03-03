# AI Orchestrator

The orchestrator (`ai/orchestrator.php`) manages the full message lifecycle -- from receiving a user message to streaming the final response. It implements the Plan-Confirm-Execute workflow with a tool loop, retry logic, and model fallback.

## Message Lifecycle

1. **Receive** -- User message arrives via `/stream` or `/chat` endpoint
2. **Persist** -- Create or continue a conversation in `agent_conversations`, store user message in `agent_messages`
3. **Build Prompt** -- Prompt Builder assembles the system prompt with site context, action definitions, pattern library, and conversation history
4. **Route Model** -- Model Router selects the provider and model based on tier/complexity
5. **Send to AI** -- Request sent to the AI provider (OpenRouter or direct)
6. **Tool Loop** -- Process AI response; if it contains tool calls, dispatch actions and loop
7. **Stream** -- SSE events sent to the client throughout the process
8. **Finalize** -- Store assistant message, update token counts, close SSE connection

## Tool Loop

The orchestrator runs a tool loop with a maximum of **25 iterations** per request. Each iteration:

```
AI Response
    |
    +-- Contains tool_calls?
    |     |
    |     +-- Yes: For each tool call:
    |     |     1. Look up action in Action_Registry
    |     |     2. Check user capability for the action
    |     |     3. Create checkpoint (snapshot_before)
    |     |     4. Execute action
    |     |     5. Store checkpoint (snapshot_after)
    |     |     6. Log to agent_history
    |     |     7. Send SSE progress events
    |     |     8. Append tool result to conversation
    |     |
    |     +-- Send updated conversation back to AI -> next iteration
    |
    +-- No tool_calls (text response):
          -> Stream content to client
          -> Exit loop
```

If the loop hits 25 iterations, it forces a final text response.

## Retry Logic

Failed AI API calls are retried with exponential backoff:

- **Max retries:** 2
- **Backoff:** 1s, then 2s
- **Retried errors:** Rate limits (429), server errors (500, 502, 503), timeouts
- **Not retried:** Auth errors (401, 403), bad requests (400)

## Model Fallback

If the primary model fails after all retries, the orchestrator falls back through the configured provider chain. For OpenRouter, the fallback chain is based on model tiers. For direct providers, it follows the `preferred_provider` order.

## SSE Streaming Events

During the tool loop, the stream controller emits these event types:

| Event Type | When | Payload |
|------------|------|---------|
| `content` | AI generates text | `{ "content": "..." }` |
| `progress` | Action starts | `{ "stage": "action_start", "action": "create_post", "index": 1, "total": 3 }` |
| `progress` | Action completes | `{ "stage": "action_complete", "action": "create_post", "success": true }` |
| `action` | Client-side action needed | `{ "action": "insert_blocks", "data": {...} }` |
| `error` | Error occurs | `{ "message": "...", "code": "..." }` |
| `done` | Stream complete | `{ "conversation_id": 42 }` |

The final frame is always `data: [DONE]`.

## Error Handling

- **Rate limit exceeded** -- Returns `429` with retry-after header
- **Invalid API key** -- Returns `401`, suggests checking settings
- **Action failure** -- Logged to history, AI informed of failure, loop continues
- **Checkpoint failure** -- Action still executes but undo is unavailable
- **Max iterations** -- Forces a summary response after 25 loops

## Conversation Persistence

All conversations and messages are stored in custom DB tables (see [Database Schema](Database-Schema)). Messages include:

- `role` -- user, assistant, system, tool
- `metadata` -- JSON containing tool calls, action plans, confirmation requests
- `tokens` -- Token count for billing/tracking
- `model` -- Which model generated the message

## See Also

- [AI Providers](AI-Providers) -- Provider configuration and model routing
- [Action Catalog](Action-Catalog) -- All 77 available actions
- [REST API Reference](REST-API-Reference) -- Stream and chat endpoints
- [Security Model](Security-Model) -- Capability checks during dispatch
