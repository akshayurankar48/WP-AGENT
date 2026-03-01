/**
 * Scrollable message list with auto-scroll and loading states.
 *
 * @package
 * @since 1.0.0
 */

import { useRef, useEffect } from '@wordpress/element';
import { css } from '@emotion/css';
import MessageBubble from './MessageBubble';
import { ThinkingIndicator, ActionIndicator, SkeletonMessages } from './LoadingStates';
import { spacing, scrollbar } from './styles';

/* ── Styles ─────────────────────────────────────────────────────── */

const list = css`
	${ scrollbar };
	flex: 1;
	overflow-y: auto;
	padding: ${ spacing.md } ${ spacing.md } ${ spacing.xs };
`;

/* ── Helpers ────────────────────────────────────────────────────── */

/**
 * Detect whether the AI is currently executing tool calls (between chunks).
 *
 * Heuristic: streaming is active, there IS some content already, but the
 * content ends with a line that looks like an action cue (e.g. "Starting with
 * hero sections:" or "Now inserting the...").
 *
 * @param {string} streamingContent
 */
const isExecutingActions = ( streamingContent ) => {
	if ( ! streamingContent ) {
		return false;
	}
	const trimmed = streamingContent.trim();
	return trimmed.endsWith( ':' ) || trimmed.endsWith( '...' );
};

/* ── Component ──────────────────────────────────────────────────── */

const MessageList = ( { messages, isStreaming, streamingContent, isLoading } ) => {
	const bottomRef = useRef( null );

	// Auto-scroll to bottom when messages change or streaming content updates.
	useEffect( () => {
		bottomRef.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, streamingContent, isStreaming ] );

	// Loading skeleton while fetching conversation history.
	if ( isLoading ) {
		return (
			<div className={ list }>
				<SkeletonMessages />
			</div>
		);
	}

	const showThinking = isStreaming && ! streamingContent;
	const showAction = isStreaming && streamingContent && isExecutingActions( streamingContent );

	return (
		<div className={ list }>
			{ messages.map( ( msg ) => (
				<MessageBubble
					key={ msg.id }
					role={ msg.role }
					content={ msg.content }
				/>
			) ) }

			{ /* Streaming bubble (text appearing in real time) */ }
			{ isStreaming && streamingContent && (
				<MessageBubble
					key="streaming"
					role="assistant"
					content={ streamingContent }
				/>
			) }

			{ /* Thinking indicator: AI processing, no text yet */ }
			{ showThinking && <ThinkingIndicator /> }

			{ /* Action indicator: AI executing tool calls between chunks */ }
			{ showAction && <ActionIndicator label="Inserting blocks..." /> }

			<div ref={ bottomRef } />
		</div>
	);
};

export default MessageList;
