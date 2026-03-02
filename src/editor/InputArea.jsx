/**
 * Chat input area with send/stop controls and contextual prompt chips.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback, useRef, useEffect, useMemo } from '@wordpress/element';
import { css } from '@emotion/css';
import { Send, Square, Cpu } from 'lucide-react';
import { colors, radii, spacing, fontSizes, focusRing, fadeIn } from './styles';
import VoiceInput from './VoiceInput';

/* ── Styles ─────────────────────────────────────────────────────── */

const wrapper = css`
	padding: ${ spacing.md } ${ spacing.lg };
	background: ${ colors.bg };
	box-shadow: 0 -1px 3px ${ colors.shadow };
	position: relative;
	z-index: 2;
`;

const chipsRow = css`
	display: flex;
	gap: 6px;
	padding-bottom: ${ spacing.sm };
	overflow-x: auto;
	scrollbar-width: none;
	animation: ${ fadeIn } 0.2s ease-out;

	&::-webkit-scrollbar {
		display: none;
	}
`;

const chip = css`
	${ focusRing };
	flex-shrink: 0;
	padding: 4px 10px;
	font-size: 11px;
	font-weight: 500;
	color: ${ colors.primary };
	background: ${ colors.primaryLight };
	border: 1px solid ${ colors.primaryLighter };
	border-radius: ${ radii.full };
	cursor: pointer;
	white-space: nowrap;
	transition: all 0.15s ease;

	&:hover {
		background: ${ colors.primaryLighter };
		border-color: ${ colors.primary };
	}

	&:active {
		transform: scale(0.97);
	}
`;

const inputRow = css`
	display: flex;
	align-items: flex-end;
	gap: ${ spacing.sm };
	padding: 6px;
	background: ${ colors.bgSubtle };
	border: 1px solid ${ colors.border };
	border-radius: ${ radii.xl };
	transition: border-color 0.15s ease, box-shadow 0.15s ease;

	&:focus-within {
		border-color: ${ colors.primary };
		box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
	}
`;

const textarea = css`
	flex: 1;
	resize: none;
	border: none;
	background: transparent;
	padding: 6px ${ spacing.sm };
	font-size: ${ fontSizes.sm };
	line-height: 1.5;
	color: ${ colors.text };
	outline: none;

	&::placeholder {
		color: ${ colors.textMuted };
	}

	&:disabled {
		opacity: 0.5;
	}
`;

const btnBase = css`
	${ focusRing };
	flex-shrink: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: none;
	border-radius: ${ radii.md };
	cursor: pointer;
	outline: none;
	transition: all 0.15s ease;
`;

const sendBtn = css`
	${ btnBase };
	background: ${ colors.primary };
	color: ${ colors.textInverse };

	&:hover {
		background: ${ colors.primaryHover };
		transform: scale(1.05);
	}

	&:active {
		transform: scale(0.97);
	}

	&:disabled {
		opacity: 0.35;
		cursor: not-allowed;
		transform: none;
	}
`;

const stopBtn = css`
	${ btnBase };
	background: ${ colors.error };
	color: ${ colors.textInverse };

	&:hover {
		background: ${ colors.errorHover };
		transform: scale(1.05);
	}

	&:active {
		transform: scale(0.97);
	}
`;

const hintRow = css`
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-top: 6px;
	user-select: none;
`;

const hint = css`
	font-size: 10px;
	color: ${ colors.textMuted };
`;

const charCount = css`
	font-size: 10px;
	color: ${ colors.textMuted };
	font-variant-numeric: tabular-nums;
`;

const charCountWarn = css`
	font-size: 10px;
	color: ${ colors.error };
	font-variant-numeric: tabular-nums;
`;

const modelPill = css`
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 8px;
	font-size: 10px;
	font-weight: 500;
	color: ${ colors.textSecondary };
	background: ${ colors.bgSubtle };
	border: 1px solid ${ colors.borderLight };
	border-radius: ${ radii.full };
	margin-bottom: 6px;
`;

const MAX_CHARS = 4000;

/* ── Prompt Chip Data ──────────────────────────────────────────── */

const CHIPS_BLANK_PAGE = [
	{ label: 'Landing page', message: 'Build a professional landing page' },
	{ label: 'About page', message: 'Create an about page for my business' },
	{ label: 'Contact page', message: 'Build a contact page with a form' },
	{ label: 'Hero section', message: 'Add a hero section with heading and CTA' },
];

const CHIPS_BLANK_POST = [
	{ label: 'Blog post', message: 'Draft a blog post about' },
	{ label: 'How-to guide', message: 'Write a how-to guide about' },
	{ label: 'Product review', message: 'Write a product review for' },
	{ label: 'Listicle', message: 'Write a listicle: Top 10' },
];

const CHIPS_HAS_CONTENT = [
	{ label: 'Improve it', message: 'Review and improve the current content' },
	{ label: 'Add section', message: 'Add a new section below the existing content' },
	{ label: 'Add images', message: 'Find and add relevant images to this content' },
	{ label: 'SEO check', message: 'Optimize this content for search engines' },
];

const CHIPS_PUBLISHED = [
	{ label: 'Refresh', message: 'Refresh this published content with updates' },
	{ label: 'Add section', message: 'Extend this published content with a new section' },
	{ label: 'SEO audit', message: 'Audit the SEO of this published content' },
	{ label: 'Readability', message: 'Improve the readability of this content' },
];

function getChipsForContext( context ) {
	if ( ! context ) {
		return CHIPS_BLANK_POST;
	}
	if ( context.type === 'published' ) {
		return CHIPS_PUBLISHED;
	}
	if ( context.type === 'has-content' ) {
		return CHIPS_HAS_CONTENT;
	}
	if ( context.postType === 'page' ) {
		return CHIPS_BLANK_PAGE;
	}
	return CHIPS_BLANK_POST;
}

/* ── Component ──────────────────────────────────────────────────── */

const InputArea = ( { onSend, onStop, isStreaming, disabled, showChips, editorContext, modelName } ) => {
	const [ value, setValue ] = useState( '' );
	const textareaRef = useRef( null );
	const voiceBufferRef = useRef( '' );

	const chips = useMemo(
		() => getChipsForContext( editorContext ),
		[ editorContext?.type, editorContext?.postType ]
	);

	const handleChange = useCallback( ( e ) => setValue( e.target.value ), [] );

	const handleSubmit = useCallback( () => {
		const trimmed = value.trim();
		if ( ! trimmed || isStreaming || disabled ) {
			return;
		}
		onSend( trimmed );
		setValue( '' );
		voiceBufferRef.current = '';

		// Reset textarea height.
		if ( textareaRef.current ) {
			textareaRef.current.style.height = 'auto';
		}
	}, [ value, isStreaming, disabled, onSend ] );

	const handleKeyDown = useCallback(
		( e ) => {
			if ( e.key === 'Enter' && ! e.shiftKey ) {
				e.preventDefault();
				handleSubmit();
			}
		},
		[ handleSubmit ]
	);

	const handleChipClick = useCallback(
		( message ) => {
			if ( disabled || isStreaming ) {
				return;
			}
			onSend( message );
		},
		[ onSend, disabled, isStreaming ]
	);

	// Voice input: stream interim transcript into textarea.
	const handleVoiceTranscript = useCallback( ( transcript ) => {
		setValue( ( prev ) => {
			const base = voiceBufferRef.current;
			return base ? base + ' ' + transcript : transcript;
		} );
	}, [] );

	// Voice input: finalize transcript and accumulate.
	const handleVoiceFinal = useCallback( ( transcript ) => {
		voiceBufferRef.current = voiceBufferRef.current
			? voiceBufferRef.current + ' ' + transcript
			: transcript;
		setValue( voiceBufferRef.current );
	}, [] );

	// Auto-resize textarea.
	useEffect( () => {
		const el = textareaRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, 120 ) + 'px';
	}, [ value ] );

	const shouldShowChips = showChips && ! isStreaming && ! value.trim();

	const len = value.length;
	const isNearLimit = len > MAX_CHARS * 0.9;

	return (
		<div className={ wrapper }>
			{ modelName && (
				<div className={ modelPill }>
					<Cpu size={ 10 } />
					{ modelName }
				</div>
			) }
			{ shouldShowChips && (
				<div className={ chipsRow }>
					{ chips.map( ( c ) => (
						<button
							key={ c.label }
							type="button"
							className={ chip }
							onClick={ () => handleChipClick( c.message ) }
						>
							{ c.label }
						</button>
					) ) }
				</div>
			) }
			<div className={ inputRow }>
				<textarea
					ref={ textareaRef }
					className={ textarea }
					placeholder="Ask JARVIS..."
					aria-label="Type your message"
					value={ value }
					onChange={ handleChange }
					onKeyDown={ handleKeyDown }
					rows={ 1 }
					disabled={ disabled }
					maxLength={ MAX_CHARS }
				/>
				<VoiceInput
					onTranscript={ handleVoiceTranscript }
					onFinalTranscript={ handleVoiceFinal }
					disabled={ disabled || isStreaming }
				/>
				{ isStreaming ? (
					<button
						type="button"
						onClick={ onStop }
						className={ stopBtn }
						aria-label="Stop generating"
					>
						<Square size={ 14 } />
					</button>
				) : (
					<button
						type="button"
						onClick={ handleSubmit }
						disabled={ ! value.trim() || disabled }
						className={ sendBtn }
						aria-label="Send message"
					>
						<Send size={ 14 } />
					</button>
				) }
			</div>
			<div className={ hintRow }>
				<span className={ hint }>
					Enter to send, Shift+Enter for new line
				</span>
				{ len > 0 && (
					<span className={ isNearLimit ? charCountWarn : charCount }>
						{ len.toLocaleString() } / { MAX_CHARS.toLocaleString() }
					</span>
				) }
			</div>
		</div>
	);
};

export default InputArea;
