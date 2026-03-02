/**
 * Slide-out chat panel for admin pages.
 *
 * Reuses editor MessageList, InputArea, and LoadingStates directly.
 *
 * @package
 * @since 1.0.0
 */

import { css, keyframes } from '@emotion/css';
import { useChatAdmin } from '../hooks/use-chat-admin';
import MessageList from '../editor/MessageList';
import InputArea from '../editor/InputArea';
import {
	colors,
	spacing,
	radii,
	fontSizes,
	baseFont,
	resetBox,
	focusRing,
} from '../editor/styles';
import {
	Bot,
	X,
	SquarePen,
	AlertCircle,
	FileText,
	Sliders,
	Sparkles,
	Search,
} from 'lucide-react';

/* ── Animations ────────────────────────────────────────────────── */

const slideIn = keyframes`
	from { transform: translateX(100%); }
	to   { transform: translateX(0); }
`;

const fadeIn = keyframes`
	from { opacity: 0; }
	to   { opacity: 1; }
`;

/* ── Styles ────────────────────────────────────────────────────── */

const backdrop = css`
	position: fixed;
	inset: 0;
	top: 32px;
	z-index: 99998;
	background: rgba(0, 0, 0, 0.2);
	animation: ${ fadeIn } 0.2s ease-out;
`;

const panel = css`
	${ baseFont };
	${ resetBox };
	position: fixed;
	top: 32px;
	right: 0;
	bottom: 0;
	width: 380px;
	z-index: 99999;
	display: flex;
	flex-direction: column;
	background: ${ colors.bg };
	box-shadow: -4px 0 24px rgba(0, 0, 0, 0.12);
	animation: ${ slideIn } 0.3s cubic-bezier(0.16, 1, 0.3, 1);

	@media (max-width: 480px) {
		width: 100vw;
	}
`;

const header = css`
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: ${ spacing.md } ${ spacing.lg };
	background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
	border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
	background: rgba(255, 255, 255, 0.2);
	color: #ffffff;
`;

const headerTitle = css`
	font-size: ${ fontSizes.sm };
	font-weight: 700;
	color: #ffffff;
	letter-spacing: -0.02em;
`;

const headerActions = css`
	display: flex;
	align-items: center;
	gap: 4px;
`;

const headerBtn = css`
	${ focusRing };
	display: flex;
	align-items: center;
	gap: 5px;
	padding: 5px 8px;
	border: none;
	border-radius: ${ radii.sm };
	background: rgba(255, 255, 255, 0.15);
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: rgba(255, 255, 255, 0.9);
	cursor: pointer;
	transition: background 0.15s ease;

	&:hover {
		background: rgba(255, 255, 255, 0.25);
	}

	&:disabled {
		opacity: 0.4;
		cursor: not-allowed;
	}
`;

const closeBtn = css`
	${ focusRing };
	display: flex;
	align-items: center;
	justify-content: center;
	width: 28px;
	height: 28px;
	border: none;
	border-radius: ${ radii.sm };
	background: rgba(255, 255, 255, 0.15);
	color: #ffffff;
	cursor: pointer;
	transition: background 0.15s ease;

	&:hover {
		background: rgba(255, 255, 255, 0.25);
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

const errorIconStyle = css`
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

/* ── Welcome (inline, no editor deps) ─────────────────────────── */

const welcomeWrap = css`
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: ${ spacing.xl };
	gap: ${ spacing.lg };
	overflow-y: auto;
`;

const welcomeIcon = css`
	width: 48px;
	height: 48px;
	border-radius: 14px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: ${ colors.primaryLight };
	color: ${ colors.primary };
`;

const welcomeTitle = css`
	font-size: ${ fontSizes.md };
	font-weight: 700;
	color: ${ colors.text };
	text-align: center;
`;

const welcomeSub = css`
	font-size: ${ fontSizes.xs };
	color: ${ colors.textSecondary };
	text-align: center;
	max-width: 260px;
	line-height: 1.5;
`;

const promptGrid = css`
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: ${ spacing.sm };
	width: 100%;
`;

const promptCard = css`
	${ focusRing };
	display: flex;
	align-items: center;
	gap: 8px;
	padding: ${ spacing.md };
	border: 1px solid ${ colors.border };
	border-radius: ${ radii.md };
	background: ${ colors.bgSubtle };
	cursor: pointer;
	transition: all 0.15s ease;
	text-align: left;

	&:hover {
		border-color: ${ colors.primary };
		background: ${ colors.primaryLight };
	}
`;

const promptIcon = css`
	flex-shrink: 0;
	color: ${ colors.primary };
`;

const promptLabel = css`
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: ${ colors.text };
	line-height: 1.3;
`;

const ADMIN_PROMPTS = [
	{ icon: Sliders, label: 'Manage my plugins', message: 'List all installed plugins and their status' },
	{ icon: FileText, label: 'Create a new page', message: 'Create a new blank page' },
	{ icon: Sparkles, label: 'Configure settings', message: 'Show me the current site settings' },
	{ icon: Search, label: 'Search my posts', message: 'Search all published posts' },
];

/* ── Component ─────────────────────────────────────────────────── */

export default function DrawerPanel( { onClose } ) {
	const {
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
		clearError,
	} = useChatAdmin();

	const hasMessages = messages.length > 0;

	return (
		<>
			<div
				className={ backdrop }
				onClick={ onClose }
				role="presentation"
			/>
			<div className={ panel } role="dialog" aria-label="JARVIS Assistant">
				{ /* Header */ }
				<div className={ header }>
					<div className={ headerLeft }>
						<div className={ headerIcon }>
							<Bot size={ 14 } />
						</div>
						<h2 className={ headerTitle }>JARVIS</h2>
					</div>
					<div className={ headerActions }>
						{ hasApiKey && (
							<button
								type="button"
								onClick={ startNewConversation }
								className={ headerBtn }
								aria-label="Start new chat"
								disabled={ isStreaming }
							>
								<SquarePen size={ 12 } />
								New
							</button>
						) }
						<button
							type="button"
							onClick={ onClose }
							className={ closeBtn }
							aria-label="Close assistant"
						>
							<X size={ 14 } />
						</button>
					</div>
				</div>

				{ /* Error banner */ }
				{ error && (
					<div className={ errorBanner }>
						<AlertCircle size={ 14 } className={ errorIconStyle } />
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

				{ /* Content */ }
				{ ! hasApiKey || ( ! hasMessages && ! isStreaming ) ? (
					<div className={ welcomeWrap }>
						<div className={ welcomeIcon }>
							<Bot size={ 24 } />
						</div>
						<h3 className={ welcomeTitle }>Hi! I'm JARVIS.</h3>
						<p className={ welcomeSub }>
							{ hasApiKey
								? 'Your WordPress AI assistant. Ask me anything about your site.'
								: 'Configure your API key in WP Agent settings to get started.' }
						</p>
						{ hasApiKey && (
							<div className={ promptGrid }>
								{ ADMIN_PROMPTS.map( ( prompt ) => (
									<button
										key={ prompt.label }
										type="button"
										className={ promptCard }
										onClick={ () => sendMessage( prompt.message ) }
									>
										<prompt.icon size={ 14 } className={ promptIcon } />
										<span className={ promptLabel }>{ prompt.label }</span>
									</button>
								) ) }
							</div>
						) }
					</div>
				) : (
					<MessageList
						messages={ messages }
						isStreaming={ isStreaming }
						streamingContent={ streamingContent }
						isLoading={ isLoading }
						actionProgress={ actionProgress }
						completedSteps={ completedSteps }
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
						editorContext={ null }
					/>
				) }
			</div>
		</>
	);
}
