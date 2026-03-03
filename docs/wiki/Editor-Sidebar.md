# Editor Sidebar

The editor sidebar is JARVIS AI's primary interface -- a chat panel inside the Gutenberg block editor. It allows users to operate WordPress through natural language without leaving the editor.

## Activation

- **Click:** JARVIS icon in the editor top toolbar
- **Keyboard shortcut:** `Cmd+J` (macOS) / `Ctrl+J` (Windows/Linux)
- **Registration:** `PluginSidebar` from `@wordpress/plugins`

## Components

### ChatPanel

The top-level sidebar component. Manages conversation state, renders the message list, input area, and welcome screen.

### WelcomeScreen

Shown when no conversation is active. Displays:

- Quick-start suggestions ("Create a landing page", "Install a plugin", etc.)
- Model selector dropdown
- Brand preset summary (if configured)

### MessageList

Scrollable list of conversation messages. Auto-scrolls to bottom on new messages. Renders `MessageBubble` for each message.

### MessageBubble

Individual message display. Handles:

- **User messages** -- Right-aligned, simple text
- **Assistant messages** -- Left-aligned, Markdown rendering with code blocks
- **Action progress** -- Inline progress indicators for tool execution
- **Client actions** -- "Insert blocks" buttons for content insertion
- **Error messages** -- Red-styled error display

### InputArea

Message input with:

- Auto-resizing textarea
- Send button (disabled during streaming)
- Cancel button (visible during streaming, calls `cancelStream()`)
- Voice input button (if browser supports Speech Recognition API)
- Model override selector

### DesignScore

Optional component that displays an AI-generated design quality score for the current post. Shown after content generation actions.

### VoiceInput

Browser Speech Recognition API integration. Converts spoken input to text in the input area. Only available in supported browsers (Chrome, Edge).

## Styling

All sidebar styles use **Emotion CSS** (`@emotion/css`). This is required because:

1. Gutenberg's `PluginSidebar` renders as a body-level portal
2. Tailwind CSS scoped styles do not reach body-level portals
3. Emotion generates `<style>` tags dynamically, which work in any DOM position

Example:

```js
import { css } from '@emotion/css';

const messageStyle = css`
  padding: 12px 16px;
  border-radius: 8px;
  font-size: 14px;
`;
```

## Sidebar Width

The sidebar is constrained to approximately **280px** by Gutenberg. This is a WordPress core limitation. All components are designed to work within this narrow width.

## Data Flow

```
User types message
      |
      v
InputArea dispatches sendMessage() thunk
      |
      v
Redux store opens SSE connection
      |
      v
Stream events update store state
      |
      v
MessageList re-renders via useSelect()
      |
      v
New content appears in real-time
```

## Block Insertion

When the AI generates blocks (via `insert_blocks` action), the sidebar receives a client-side action event:

1. SSE sends `{ "type": "action", "action": "insert_blocks", "data": {...} }`
2. MessageBubble renders an "Insert blocks" button
3. User clicks the button
4. Blocks are inserted into the editor via `@wordpress/data` dispatch to `core/block-editor`

## See Also

- [Frontend Architecture](Frontend-Architecture) -- Entry points and build system
- [Redux Store](Redux-Store) -- State management
- [AI Orchestrator](AI-Orchestrator) -- SSE streaming events
