/**
 * Store selectors.
 *
 * @package
 * @since 1.0.0
 */

export const getConversationId = ( state ) => state.conversationId;
export const getPostId = ( state ) => state.postId;
export const getMessages = ( state ) => state.messages;
export const getIsStreaming = ( state ) => state.isStreaming;
export const getStreamingContent = ( state ) => state.streamingContent;
export const getError = ( state ) => state.error;
export const getIsLoading = ( state ) => state.isLoading;
export const getHasApiKey = ( state ) => state.hasApiKey;
export const getPendingActions = ( state ) => state.pendingActions;
export const getActionProgress = ( state ) => state.actionProgress;
