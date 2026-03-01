/**
 * Store actions — plain action creators and thunks.
 *
 * @package
 * @since 1.0.0
 */

import { ACTION_TYPES } from './constants';
import { parseSSEStream } from '../hooks/use-streaming';

/**
 * Module-level AbortController reference.
 * Not stored in Redux state because it's not serializable.
 */
let currentAbortController = null;

// --- Plain action creators ---

export const setConversationId = ( conversationId ) => ( {
	type: ACTION_TYPES.SET_CONVERSATION_ID,
	conversationId,
} );

export const setPostId = ( postId ) => ( {
	type: ACTION_TYPES.SET_POST_ID,
	postId,
} );

export const addMessage = ( message ) => ( {
	type: ACTION_TYPES.ADD_MESSAGE,
	message,
} );

export const setMessages = ( messages ) => ( {
	type: ACTION_TYPES.SET_MESSAGES,
	messages,
} );

export const setIsStreaming = ( isStreaming ) => ( {
	type: ACTION_TYPES.SET_IS_STREAMING,
	isStreaming,
} );

export const appendStreamingContent = ( content ) => ( {
	type: ACTION_TYPES.APPEND_STREAMING_CONTENT,
	content,
} );

export const setStreamingContent = ( content ) => ( {
	type: ACTION_TYPES.SET_STREAMING_CONTENT,
	content,
} );

export const finalizeStreaming = () => ( {
	type: ACTION_TYPES.FINALIZE_STREAMING,
} );

export const setError = ( error ) => ( {
	type: ACTION_TYPES.SET_ERROR,
	error,
} );

export const setIsLoading = ( isLoading ) => ( {
	type: ACTION_TYPES.SET_IS_LOADING,
	isLoading,
} );

export const setHasApiKey = ( hasApiKey ) => ( {
	type: ACTION_TYPES.SET_HAS_API_KEY,
	hasApiKey,
} );

export const resetConversation = () => ( {
	type: ACTION_TYPES.RESET_CONVERSATION,
} );

export const addPendingAction = ( pendingAction ) => ( {
	type: ACTION_TYPES.ADD_PENDING_ACTION,
	pendingAction,
} );

export const clearPendingActions = () => ( {
	type: ACTION_TYPES.CLEAR_PENDING_ACTIONS,
} );

// --- Thunks ---

/**
 * Send a message and stream the AI response.
 *
 * @param {string} message User message text.
 * @return {Function} Thunk function.
 */
export const sendMessage =
	( message ) =>
		async ( { dispatch, select } ) => {
			const { restUrl, nonce } = window.wpAgentData || {};

			if ( ! restUrl || ! nonce ) {
				dispatch( setError( 'Plugin configuration is missing. Please reload the page.' ) );
				return;
			}

			// Add optimistic user message.
			const userMessage = {
				id: 'msg-' + Date.now(),
				role: 'user',
				content: message,
				timestamp: new Date().toISOString(),
			};
			dispatch( addMessage( userMessage ) );
			dispatch( setError( null ) );
			dispatch( setIsStreaming( true ) );
			dispatch( setStreamingContent( '' ) );

			// Create AbortController for this request.
			currentAbortController = new AbortController();

			try {
				const conversationId = select.getConversationId();
				const postId = select.getPostId();

				const body = { message };
				if ( conversationId ) {
					body.conversation_id = conversationId;
				}
				if ( postId ) {
					body.post_id = postId;
				}

				const response = await fetch( `${ restUrl }stream`, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': nonce,
					},
					body: JSON.stringify( body ),
					signal: currentAbortController.signal,
				} );

				if ( ! response.ok ) {
					const errorData = await response.json().catch( () => null );
					const errorMessage =
					errorData?.message || `Request failed with status ${ response.status }`;
					dispatch( setError( errorMessage ) );
					dispatch( setIsStreaming( false ) );
					return;
				}

				await parseSSEStream( response, ( chunk ) => {
					switch ( chunk.type ) {
						case 'content':
							dispatch( appendStreamingContent( chunk.content ) );
							break;
						case 'error':
							dispatch( setError( chunk.message ) );
							break;
						case 'action':
							dispatch( addPendingAction( { action: chunk.action, data: chunk.data } ) );
							break;
						case 'done':
							if ( chunk.conversation_id ) {
								dispatch(
									setConversationId( chunk.conversation_id )
								);
							}
							break;
					}
				} );

				// Finalize: move streamingContent into a message.
				dispatch( finalizeStreaming() );
			} catch ( error ) {
				if ( error.name === 'AbortError' ) {
				// User cancelled — finalize whatever content we have.
					dispatch( finalizeStreaming() );
				} else {
					dispatch( setError( error.message || 'An unexpected error occurred.' ) );
					dispatch( setIsStreaming( false ) );
				}
			} finally {
				currentAbortController = null;
			}
		};

/**
 * Stop the current streaming response.
 *
 * @return {Function} Thunk function.
 */
export const stopStreaming =
	() =>
		( { dispatch } ) => {
			if ( currentAbortController ) {
				currentAbortController.abort();
				currentAbortController = null;
			}
			dispatch( setIsStreaming( false ) );
		};

/**
 * Start a new conversation — clears all chat state.
 *
 * @return {Function} Thunk function.
 */
export const startNewConversation =
	() =>
		( { dispatch } ) => {
			if ( currentAbortController ) {
				currentAbortController.abort();
				currentAbortController = null;
			}
			dispatch( resetConversation() );
		};

/**
 * Restore the most recent conversation for a given post.
 *
 * Queries the history endpoint filtered by post_id. If a conversation
 * exists, loads it into the store. Otherwise does nothing (fresh post).
 *
 * @param {number} postId WordPress post ID.
 * @return {Function} Thunk function.
 */
export const restoreConversation =
	( postId ) =>
		async ( { dispatch } ) => {
			const { restUrl, nonce } = window.wpAgentData || {};

			if ( ! restUrl || ! nonce || ! postId ) {
				return;
			}

			try {
				const response = await fetch(
					`${ restUrl }history?post_id=${ postId }&per_page=1`,
					{ headers: { 'X-WP-Nonce': nonce } }
				);

				if ( ! response.ok ) {
					return;
				}

				const data = await response.json();
				const conversations = data.conversations || [];

				if ( conversations.length > 0 ) {
					dispatch( loadConversation( conversations[ 0 ].id ) );
				}
			} catch {
				// Silently fail — fresh post experience is the fallback.
			}
		};

/**
 * Load an existing conversation from history.
 *
 * @param {number} id Conversation ID.
 * @return {Function} Thunk function.
 */
export const loadConversation =
	( id ) =>
		async ( { dispatch } ) => {
			const { restUrl, nonce } = window.wpAgentData || {};

			if ( ! restUrl || ! nonce ) {
				dispatch( setError( 'Plugin configuration is missing.' ) );
				return;
			}

			dispatch( setIsLoading( true ) );
			dispatch( setError( null ) );

			try {
				const response = await fetch( `${ restUrl }history/${ id }`, {
					headers: { 'X-WP-Nonce': nonce },
				} );

				if ( ! response.ok ) {
					throw new Error( `Failed to load conversation (status ${ response.status })` );
				}

				const data = await response.json();

				dispatch( setConversationId( data.conversation?.id || id ) );
				dispatch(
					setMessages(
						( data.messages || [] )
							.filter( ( msg ) => msg.role === 'user' || msg.role === 'assistant' )
							.map( ( msg ) => ( {
								id: msg.id || 'msg-' + Date.now() + Math.random(),
								role: msg.role,
								content: msg.content,
								timestamp: msg.created_at,
							} ) )
					)
				);
			} catch ( error ) {
				dispatch( setError( error.message ) );
			} finally {
				dispatch( setIsLoading( false ) );
			}
		};
