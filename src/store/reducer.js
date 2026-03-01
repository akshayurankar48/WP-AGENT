/**
 * Store reducer.
 *
 * @package
 * @since 1.0.0
 */

import { ACTION_TYPES } from './constants';

const { hasApiKey } = window.wpAgentData || {};

const DEFAULT_STATE = {
	conversationId: null,
	postId: null,
	messages: [],
	isStreaming: false,
	streamingContent: '',
	error: null,
	isLoading: false,
	hasApiKey: !! hasApiKey,
	pendingActions: [],
	actionProgress: null,
};

const reducer = ( state = DEFAULT_STATE, action ) => {
	switch ( action.type ) {
		case ACTION_TYPES.SET_CONVERSATION_ID:
			return { ...state, conversationId: action.conversationId };

		case ACTION_TYPES.SET_POST_ID:
			return { ...state, postId: action.postId };

		case ACTION_TYPES.ADD_MESSAGE:
			return { ...state, messages: [ ...state.messages, action.message ] };

		case ACTION_TYPES.SET_MESSAGES:
			return { ...state, messages: action.messages };

		case ACTION_TYPES.SET_IS_STREAMING:
			return { ...state, isStreaming: action.isStreaming };

		case ACTION_TYPES.APPEND_STREAMING_CONTENT:
			return {
				...state,
				streamingContent: state.streamingContent + action.content,
			};

		case ACTION_TYPES.SET_STREAMING_CONTENT:
			return { ...state, streamingContent: action.content };

		case ACTION_TYPES.FINALIZE_STREAMING: {
			const assistantMessage = {
				id: 'msg-' + Date.now(),
				role: 'assistant',
				content: state.streamingContent,
				timestamp: new Date().toISOString(),
			};
			return {
				...state,
				messages: [ ...state.messages, assistantMessage ],
				streamingContent: '',
				isStreaming: false,
			};
		}

		case ACTION_TYPES.SET_ERROR:
			return { ...state, error: action.error, isStreaming: false };

		case ACTION_TYPES.SET_IS_LOADING:
			return { ...state, isLoading: action.isLoading };

		case ACTION_TYPES.SET_HAS_API_KEY:
			return { ...state, hasApiKey: action.hasApiKey };

		case ACTION_TYPES.RESET_CONVERSATION:
			return {
				...state,
				conversationId: null,
				messages: [],
				streamingContent: '',
				error: null,
				isStreaming: false,
				pendingActions: [],
				actionProgress: null,
			};

		case ACTION_TYPES.ADD_PENDING_ACTION:
			return {
				...state,
				pendingActions: [ ...state.pendingActions, action.pendingAction ],
			};

		case ACTION_TYPES.CLEAR_PENDING_ACTIONS:
			return { ...state, pendingActions: [] };

		case ACTION_TYPES.SET_ACTION_PROGRESS:
			return { ...state, actionProgress: action.progress };

		case ACTION_TYPES.CLEAR_ACTION_PROGRESS:
			return { ...state, actionProgress: null };

		default:
			return state;
	}
};

export default reducer;
