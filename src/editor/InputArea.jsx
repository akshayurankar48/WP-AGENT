/**
 * Chat input area with send/stop controls.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { css } from '@emotion/css';
import { Send, Square } from 'lucide-react';
import { colors, radii, spacing, fontSizes, focusRing } from './styles';

/* ── Styles ─────────────────────────────────────────────────────── */

const wrapper = css`
	padding: ${ spacing.md } ${ spacing.lg };
	background: ${ colors.bg };
	box-shadow: 0 -1px 3px ${ colors.shadow };
	position: relative;
	z-index: 2;
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
	flex-shrink: 0;
	width: 32px;
	height: 32px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: none;
	border-radius: ${ radii.md };
	cursor: pointer;
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

const hint = css`
	font-size: 10px;
	color: ${ colors.textMuted };
	text-align: center;
	margin-top: 6px;
	user-select: none;
`;

/* ── Component ──────────────────────────────────────────────────── */

const InputArea = ( { onSend, onStop, isStreaming, disabled } ) => {
	const [ value, setValue ] = useState( '' );
	const textareaRef = useRef( null );

	const handleSubmit = useCallback( () => {
		const trimmed = value.trim();
		if ( ! trimmed || isStreaming || disabled ) {
			return;
		}
		onSend( trimmed );
		setValue( '' );

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

	// Auto-resize textarea.
	useEffect( () => {
		const el = textareaRef.current;
		if ( ! el ) {
			return;
		}
		el.style.height = 'auto';
		el.style.height = Math.min( el.scrollHeight, 120 ) + 'px';
	}, [ value ] );

	return (
		<div className={ wrapper }>
			<div className={ inputRow }>
				<textarea
					ref={ textareaRef }
					className={ textarea }
					placeholder="Ask JARVIS..."
					aria-label="Type your message"
					value={ value }
					onChange={ ( e ) => setValue( e.target.value ) }
					onKeyDown={ handleKeyDown }
					rows={ 1 }
					disabled={ disabled }
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
			<p className={ hint }>
				Enter to send, Shift+Enter for new line
			</p>
		</div>
	);
};

export default InputArea;
