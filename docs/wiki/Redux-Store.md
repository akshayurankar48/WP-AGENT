# Redux Store

JARVIS AI uses `@wordpress/data` with `createReduxStore` for frontend state management. The store is shared between all three entry points (editor, admin, drawer).

## Store Registration

- **Store name:** `jarvis-ai/chat`
- **Files:** `src/store/index.js`, `actions.js`, `reducer.js`, `selectors.js`, `constants.js`
- **Registration:** `createReduxStore()` + `register()` from `@wordpress/data`

## State Shape

```js
{
  conversations: [],          // List of conversation summaries
  currentConversation: null,  // Active conversation object
  messages: [],               // Messages in current conversation
  isLoading: false,           // AI is processing
  isStreaming: false,          // SSE stream is active
  streamContent: '',          // Accumulated streaming text
  error: null,                // Last error message
  abortController: null,      // AbortController for cancelling streams
  settings: {},               // Plugin settings cache
  stats: {},                  // Dashboard statistics
}
```

## Action Types

Defined in `src/store/constants.js`:

| Constant | Description |
|----------|-------------|
| `SET_CONVERSATIONS` | Replace conversation list |
| `SET_CURRENT_CONVERSATION` | Set active conversation |
| `SET_MESSAGES` | Replace messages array |
| `ADD_MESSAGE` | Append a message |
| `UPDATE_MESSAGE` | Update a message by ID |
| `SET_LOADING` | Toggle loading state |
| `SET_STREAMING` | Toggle streaming state |
| `SET_STREAM_CONTENT` | Update streaming text buffer |
| `APPEND_STREAM_CONTENT` | Append to streaming text |
| `SET_ERROR` | Set error message |
| `CLEAR_ERROR` | Clear error state |
| `SET_ABORT_CONTROLLER` | Store AbortController reference |
| `SET_SETTINGS` | Cache plugin settings |
| `SET_STATS` | Cache dashboard stats |

## Action Creators

Defined in `src/store/actions.js`. Includes both plain actions and thunks.

### Key Thunks

**`sendMessage( message, conversationId, postId )`**

The primary thunk for sending a chat message:

1. Dispatches `ADD_MESSAGE` with user message
2. Creates `AbortController` and stores it
3. Opens SSE connection to `/jarvis-ai/v1/stream` via `fetch()`
4. Reads the stream with `ReadableStream` reader
5. Parses SSE frames and dispatches:
   - `APPEND_STREAM_CONTENT` for `content` events
   - `ADD_MESSAGE` for `action` events
   - `SET_CURRENT_CONVERSATION` on `done`
6. On error or abort, dispatches `SET_ERROR`
7. Cleans up `AbortController` on completion

**`cancelStream()`**

Calls `abort()` on the stored `AbortController` to cancel an in-progress stream.

**`fetchConversations()`**

Fetches paginated conversation list from `/jarvis-ai/v1/history`.

**`fetchSettings()` / `saveSettings( settings )`**

Read/write plugin settings via `/jarvis-ai/v1/settings`.

## Selectors

Defined in `src/store/selectors.js`:

| Selector | Returns |
|----------|---------|
| `getConversations( state )` | Array of conversation summaries |
| `getCurrentConversation( state )` | Active conversation object or null |
| `getMessages( state )` | Messages in current conversation |
| `isLoading( state )` | Boolean loading state |
| `isStreaming( state )` | Boolean streaming state |
| `getStreamContent( state )` | Current streaming text buffer |
| `getError( state )` | Error message or null |
| `getSettings( state )` | Cached settings object |
| `getStats( state )` | Cached stats object |

## Usage in Components

```js
import { useSelect, useDispatch } from '@wordpress/data';

const messages = useSelect( ( select ) => select( 'jarvis-ai/chat' ).getMessages() );
const { sendMessage } = useDispatch( 'jarvis-ai/chat' );
```

## See Also

- [Frontend Architecture](Frontend-Architecture) -- Build system and entry points
- [Editor Sidebar](Editor-Sidebar) -- Components that consume the store
- [REST API Reference](REST-API-Reference) -- Endpoints the store calls
