/**
 * Chat panel — root sidebar container.
 *
 * @package
 * @since 1.0.0
 */

import { css } from '@emotion/css';
import { useChat } from '../hooks/use-chat';
import { useBlockActions } from '../hooks/use-block-actions';
import { useEditorContext } from '../hooks/use-editor-context';
import MessageList from './MessageList';
import InputArea from './InputArea';
import WelcomeScreen from './WelcomeScreen';
import { SquarePen, AlertCircle, X, Bot } from 'lucide-react';
import {
	colors,
	spacing,
	radii,
	fontSizes,
	baseFont,
	resetBox,
	focusRing,
} from './styles';

/* ── Styles ─────────────────────────────────────────────────────── */

const panel = css`
	${ baseFont };
	${ resetBox };
	display: flex;
	flex-direction: column;
	height: calc( 100vh - 56px );
	background: ${ colors.bg };
	overflow: hidden;
`;

const header = css`
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: ${ spacing.md } ${ spacing.lg };
	background: ${ colors.headerGradient };
	border-bottom: 1px solid ${ colors.borderLight };
	box-shadow: 0 1px 3px ${ colors.shadow };
	position: relative;
	z-index: 2;
`;

const headerLeft = css`
	display: flex;
	align-items: center;
	gap: 8px;
`;

const headerIcon = css`
	width: 28px;
	height: 28px;
	border-radius: 8px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: ${ colors.primaryLight };
	color: ${ colors.primary };
`;

const headerTitle = css`
	font-size: ${ fontSizes.sm };
	font-weight: 700;
	color: ${ colors.text };
	letter-spacing: -0.02em;
`;

const newChatBtn = css`
	${ focusRing };
	display: flex;
	align-items: center;
	gap: 6px;
	padding: 5px 10px;
	border: none;
	border-radius: ${ radii.sm };
	background: transparent;
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: ${ colors.textSecondary };
	cursor: pointer;
	transition: all 0.15s ease;

	&:hover {
		color: ${ colors.text };
		background: ${ colors.bgSubtle };
	}

	&:disabled {
		opacity: 0.4;
		cursor: not-allowed;
	}
`;

const errorBanner = css`
	display: flex;
	align-items: flex-start;
	gap: ${ spacing.sm };
	padding: ${ spacing.sm } ${ spacing.md };
	background: ${ colors.errorBg };
	border-bottom: 1px solid ${ colors.errorBorder };
`;

const errorIcon = css`
	flex-shrink: 0;
	color: ${ colors.error };
	margin-top: 1px;
`;

const errorMsg = css`
	flex: 1;
	font-size: ${ fontSizes.xs };
	color: ${ colors.errorText };
	line-height: 1.4;
`;

const errorDismiss = css`
	${ focusRing };
	flex-shrink: 0;
	background: none;
	border: none;
	padding: 2px;
	color: ${ colors.textMuted };
	cursor: pointer;
	border-radius: ${ radii.sm };
	transition: color 0.15s ease;

	&:hover {
		color: ${ colors.errorText };
	}
`;

/* ── Component ──────────────────────────────────────────────────── */

const ChatPanel = () => {
	const {
		messages,
		isStreaming,
		streamingContent,
		error,
		isLoading,
		hasApiKey,
		actionProgress,
		sendMessage,
		stopStreaming,
		startNewConversation,
		clearError,
	} = useChat();

	const editorContext = useEditorContext();

	// Process pending client-side block actions from the AI.
	useBlockActions();

	const hasMessages = messages.length > 0;

	return (
		<div id="wp-agent-sidebar" className={ panel }>
			{ /* Header */ }
			<div className={ header }>
				<div className={ headerLeft }>
					<div className={ headerIcon }>
						<Bot size={ 14 } />
					</div>
					<h2 className={ headerTitle }>JARVIS</h2>
				</div>
				{ hasApiKey && (
					<button
						type="button"
						onClick={ startNewConversation }
						className={ newChatBtn }
						aria-label="Start new chat"
						disabled={ isStreaming }
					>
						<SquarePen size={ 14 } />
						New Chat
					</button>
				) }
			</div>

			{ /* Error banner */ }
			{ error && (
				<div className={ errorBanner }>
					<AlertCircle size={ 14 } className={ errorIcon } />
					<p className={ errorMsg }>{ error }</p>
					<button
						type="button"
						onClick={ clearError }
						className={ errorDismiss }
						aria-label="Dismiss error"
					>
						<X size={ 12 } />
					</button>
				</div>
			) }

			{ /* Main content area */ }
			{ ! hasApiKey || ( ! hasMessages && ! isStreaming ) ? (
				<WelcomeScreen
					hasApiKey={ hasApiKey }
					onSendMessage={ sendMessage }
					editorContext={ editorContext }
				/>
			) : (
				<MessageList
					messages={ messages }
					isStreaming={ isStreaming }
					streamingContent={ streamingContent }
					isLoading={ isLoading }
					actionProgress={ actionProgress }
				/>
			) }

			{ /* Input area */ }
			{ hasApiKey && (
				<InputArea
					onSend={ sendMessage }
					onStop={ stopStreaming }
					isStreaming={ isStreaming }
					disabled={ isLoading }
					showChips={ ! hasMessages }
					editorContext={ editorContext }
				/>
			) }
		</div>
	);
};

export default ChatPanel;
