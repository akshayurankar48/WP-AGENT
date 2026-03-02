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
		};
	}, [] );

	const {
		sendMessage,
		stopStreaming,
		startNewConversation,
		setError,
	} = useDispatch( STORE_NAME );

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
		sendMessage,
		stopStreaming,
		startNewConversation,
		clearError: useCallback( () => setError( null ), [ setError ] ),
	};
}
