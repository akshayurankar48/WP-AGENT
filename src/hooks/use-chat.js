/**
 * Chat bridge hook.
 *
 * Connects React components to the wp-agent/chat store
 * and syncs the current editor post ID.
 *
 * @package
 * @since 1.0.0
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useCallback } from '@wordpress/element';
import { store as editorStore } from '@wordpress/editor';
import { STORE_NAME } from '../store/constants';

/**
 * Hook that provides chat state and actions to components.
 *
 * @return {Object} Chat state and dispatch functions.
 */
export function useChat() {
	const {
		conversationId,
		messages,
		isStreaming,
		streamingContent,
		error,
		isLoading,
		hasApiKey,
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
		};
	}, [] );

	const postId = useSelect( ( select ) => {
		try {
			return select( editorStore ).getCurrentPostId();
		} catch {
			return null;
		}
	}, [] );

	const {
		sendMessage,
		stopStreaming,
		startNewConversation,
		loadConversation,
		restoreConversation,
		setPostId,
		setError,
	} = useDispatch( STORE_NAME );

	// Sync editor post ID into the store.
	useEffect( () => {
		if ( postId ) {
			setPostId( postId );
		}
	}, [ postId, setPostId ] );

	// Auto-restore the most recent conversation for this post on mount.
	useEffect( () => {
		if ( postId && ! conversationId && ! isLoading && ! isStreaming ) {
			restoreConversation( postId );
		}
	}, [ postId ] ); // eslint-disable-line react-hooks/exhaustive-deps -- Only on mount/postId change.

	return {
		conversationId,
		messages,
		isStreaming,
		streamingContent,
		error,
		isLoading,
		hasApiKey,
		actionProgress,
		postId,
		sendMessage,
		stopStreaming,
		startNewConversation,
		loadConversation,
		clearError: useCallback( () => setError( null ), [ setError ] ),
	};
}
