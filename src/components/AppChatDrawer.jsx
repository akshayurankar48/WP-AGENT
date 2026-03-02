/**
 * Force UI-based chat drawer for WP Agent admin pages.
 *
 * Renders a floating JARVIS button and a right-side Drawer with chat UI.
 * Reuses MessageList and InputArea from the editor components.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import { Drawer, Button } from '@bsf/force-ui';
import { useChatAdmin } from '../hooks/use-chat-admin';
import MessageList from '../editor/MessageList';
import InputArea from '../editor/InputArea';
import {
	Bot,
	SquarePen,
	AlertCircle,
	X,
	FileText,
	Sliders,
	Sparkles,
	Search,
} from 'lucide-react';

const ADMIN_PROMPTS = [
	{ icon: Sliders, label: 'Manage my plugins', message: 'List all installed plugins and their status' },
	{ icon: FileText, label: 'Create a new page', message: 'Create a new blank page' },
	{ icon: Sparkles, label: 'Configure settings', message: 'Show me the current site settings' },
	{ icon: Search, label: 'Search my posts', message: 'Search all published posts' },
];

function WelcomeContent( { hasApiKey, onSend } ) {
	return (
		<div className="flex flex-col items-center justify-center flex-1 py-8 gap-4">
			<div className="flex items-center justify-center size-12 rounded-xl bg-brand-primary-80">
				<Bot className="size-6 text-brand-800" />
			</div>
			<h3 className="text-base font-bold text-text-primary">Hi! I'm JARVIS.</h3>
			<p className="text-xs text-text-secondary text-center max-w-[260px] leading-relaxed">
				{ hasApiKey
					? 'Your WordPress AI assistant. Ask me anything about your site.'
					: 'Configure your API key in WP Agent settings to get started.' }
			</p>
			{ hasApiKey && (
				<div className="grid grid-cols-2 gap-2 w-full">
					{ ADMIN_PROMPTS.map( ( prompt ) => (
						<button
							key={ prompt.label }
							type="button"
							className="flex items-center gap-2 p-3 text-left border border-solid border-border-subtle rounded-lg bg-background-secondary hover:border-border-interactive hover:bg-brand-background-50 cursor-pointer transition-all duration-150"
							onClick={ () => onSend( prompt.message ) }
						>
							<prompt.icon className="size-3.5 text-brand-800 shrink-0" />
							<span className="text-xs font-medium text-text-primary leading-snug">{ prompt.label }</span>
						</button>
					) ) }
				</div>
			) }
		</div>
	);
}

export default function AppChatDrawer() {
	const [ isOpen, setIsOpen ] = useState( false );

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

	const handleSend = useCallback( ( msg ) => {
		sendMessage( msg );
	}, [ sendMessage ] );

	return (
		<Drawer
			open={ isOpen }
			setOpen={ setIsOpen }
			position="right"
			exitOnClickOutside={ true }
			exitOnEsc={ true }
			scrollLock={ false }
			trigger={
				<button
					type="button"
					style={ {
						position: 'fixed',
						bottom: '24px',
						right: '24px',
						zIndex: 99999,
						width: '48px',
						height: '48px',
						borderRadius: '50%',
						border: 'none',
						background: 'linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%)',
						color: '#ffffff',
						boxShadow: '0 4px 14px rgba(79, 70, 229, 0.4)',
						cursor: 'pointer',
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						transition: 'transform 0.2s ease, box-shadow 0.2s ease',
					} }
					aria-label="Open JARVIS assistant"
				>
					<Bot size={ 22 } />
				</button>
			}
		>
			<Drawer.Backdrop />
			<Drawer.Panel className="!w-96">
				{ /* Header */ }
				<div className="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600">
					<div className="flex items-center gap-2">
						<div className="flex items-center justify-center size-7 rounded-lg bg-white/20">
							<Bot className="size-3.5 text-white" />
						</div>
						<span className="text-sm font-bold text-white tracking-tight">JARVIS</span>
					</div>
					<div className="flex items-center gap-1">
						{ hasApiKey && (
							<button
								type="button"
								onClick={ startNewConversation }
								disabled={ isStreaming }
								className="flex items-center gap-1 px-2 py-1 border-none rounded bg-white/15 text-white/90 text-xs font-medium cursor-pointer hover:bg-white/25 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
							>
								<SquarePen className="size-3" />
								New
							</button>
						) }
						<Drawer.CloseButton />
					</div>
				</div>

				{ /* Error banner */ }
				{ error && (
					<div className="flex items-start gap-2 px-3 py-2 bg-red-50 border-b border-solid border-red-200">
						<AlertCircle className="size-3.5 text-red-500 shrink-0 mt-0.5" />
						<p className="flex-1 text-xs text-red-700 leading-snug">{ error }</p>
						<button
							type="button"
							onClick={ clearError }
							className="shrink-0 p-0.5 border-none bg-transparent text-red-400 hover:text-red-600 cursor-pointer rounded"
							aria-label="Dismiss error"
						>
							<X className="size-3" />
						</button>
					</div>
				) }

				{ /* Body */ }
				<Drawer.Body className="!p-0 !pt-0">
					{ ! hasApiKey || ( ! hasMessages && ! isStreaming ) ? (
						<div className="px-4">
							<WelcomeContent hasApiKey={ hasApiKey } onSend={ handleSend } />
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
				</Drawer.Body>

				{ /* Footer — Input */ }
				{ hasApiKey && (
					<Drawer.Footer className="!p-0">
						<div className="w-full">
							<InputArea
								onSend={ handleSend }
								onStop={ stopStreaming }
								isStreaming={ isStreaming }
								disabled={ isLoading }
								showChips={ ! hasMessages }
								editorContext={ null }
							/>
						</div>
					</Drawer.Footer>
				) }
			</Drawer.Panel>
		</Drawer>
	);
}
