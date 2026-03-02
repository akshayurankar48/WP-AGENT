/**
 * Admin chat hook — editor-free version of useChat.
 *
 * Used by the global admin drawer so it never imports
 * @wordpress/editor or @wordpress/block-editor.
 *
 * @package
 * @since 1.0.0
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback } from '@wordpress/element';
import { STORE_NAME } from '../store/constants';

export function useChatAdmin() {
	const {
		conversationId,
		messages,
		isStreaming,
		streamingContent,
		error,
		isLoading,
		hasApiKey,
		actionProgress,
		completedSteps,
		lastFailedMessage,
	} = useSelect( ( select ) => {
		const store = select( STORE_NAME );
		return {
			conversationId: store.getConversationId(),
			messages: store.getMessages(),
			isStreaming: store.getIsStreaming(),
			streamingContent: store.getStreamingContent(),
			error: store.getError(),
			isLoading: store.getIsLoading(),
			hasApiKey: store.getHasApiKey(),
			actionProgress: store.getActionProgress(),
			completedSteps: store.getCompletedSteps(),
			lastFailedMessage: store.getLastFailedMessage(),
		};
	}, [] );

	const {
		sendMessage,
		stopStreaming,
		startNewConversation,
		loadConversation,
		setError,
		setLastFailedMessage,
	} = useDispatch( STORE_NAME );

	const retryLastMessage = useCallback( () => {
		if ( lastFailedMessage ) {
			setLastFailedMessage( null );
			setError( null );
			sendMessage( lastFailedMessage );
		}
	}, [ lastFailedMessage, setLastFailedMessage, setError, sendMessage ] );

	return {
		conversationId,
		messages,
		isStreaming,
		streamingContent,
		error,
		isLoading,
		hasApiKey,
		actionProgress,
		completedSteps,
		lastFailedMessage,
		sendMessage,
		stopStreaming,
		startNewConversation,
		loadConversation,
		clearError: useCallback( () => setError( null ), [ setError ] ),
		retryLastMessage,
	};
}
