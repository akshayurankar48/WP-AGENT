/**
 * Single chat message bubble.
 *
 * Supports lightweight markdown: **bold**, `code`, ```code blocks```,
 * ## headings, bullet lists, numbered lists, [links](url), and --- rules.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { css, cx } from '@emotion/css';
import { Bot, User, Copy, Check } from 'lucide-react';
import {
	colors,
	radii,
	spacing,
	fontSizes,
	fadeIn,
	focusRing,
} from './styles';

/* ── Styles (pre-computed — no dynamic css() at render time) ──── */

const rowBase = css`
	display: flex;
	align-items: flex-start;
	gap: ${ spacing.sm };
	margin-bottom: ${ spacing.md };
	animation: ${ fadeIn } 0.25s ease-out;
`;

const rowUser = css`
	${ rowBase };
	flex-direction: row-reverse;
`;

const rowAssistant = rowBase;

const avatarBase = css`
	flex-shrink: 0;
	width: 28px;
	height: 28px;
	border-radius: ${ radii.full };
	display: flex;
	align-items: center;
	justify-content: center;
`;

const avatarUser = css`
	${ avatarBase };
	background: ${ colors.primaryLight };
	color: ${ colors.primary };
`;

const avatarAssistant = css`
	${ avatarBase };
	background: ${ colors.bgSubtle };
	color: ${ colors.textSecondary };
`;

const bubbleBase = css`
	position: relative;
	max-width: 85%;
	padding: ${ spacing.sm } ${ spacing.md };
	font-size: ${ fontSizes.sm };
	line-height: 1.55;
	letter-spacing: -0.005em;
	word-break: break-word;
	white-space: normal;

	strong {
		font-weight: 600;
	}

	ul, ol {
		white-space: normal;
	}
`;

const bubbleUser = css`
	${ bubbleBase };
	white-space: pre-wrap;
	background: ${ colors.userBubbleGradient };
	color: ${ colors.textInverse };
	border-radius: ${ radii.lg } ${ radii.sm } ${ radii.lg } ${ radii.lg };
	box-shadow: 0 1px 3px rgba(79, 70, 229, 0.2);

	code {
		font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', monospace;
		font-size: 0.9em;
		padding: 1px 5px;
		border-radius: 4px;
		background: rgba(255, 255, 255, 0.15);
	}
`;

const bubbleAssistant = css`
	${ bubbleBase };
	background: ${ colors.assistantBg };
	color: ${ colors.text };
	border: 1px solid ${ colors.border };
	border-radius: ${ radii.sm } ${ radii.lg } ${ radii.lg } ${ radii.lg };
	box-shadow: 0 1px 2px ${ colors.shadow };

	code {
		font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', monospace;
		font-size: 0.9em;
		padding: 1px 5px;
		border-radius: 4px;
		background: ${ colors.bgSubtle };
		border: 1px solid ${ colors.borderLight };
	}
`;

const copyWrap = css`
	position: absolute;
	bottom: -22px;
	right: 0;
	opacity: 0;
	transition: opacity 0.15s ease;

	*:hover > & {
		opacity: 1;
	}
`;

const copyBtn = css`
	${ focusRing };
	background: none;
	border: none;
	padding: 3px;
	color: ${ colors.textMuted };
	cursor: pointer;
	border-radius: ${ radii.sm };
	transition: color 0.15s ease;

	&:hover {
		color: ${ colors.textSecondary };
	}
`;

const copiedBtn = css`
	color: ${ colors.primary };
`;

const timestampStyle = css`
	font-size: 10px;
	color: ${ colors.textMuted };
	margin-top: 4px;
	user-select: none;
`;

const timestampUser = css`
	${ timestampStyle };
	text-align: right;
`;

const timestampAssistant = css`
	${ timestampStyle };
	text-align: left;
	margin-left: 36px;
`;

const markdownH2 = css`
	font-size: 14px;
	font-weight: 600;
	margin: 8px 0 4px;
	line-height: 1.3;
`;

const markdownH3 = css`
	font-size: 13px;
	font-weight: 600;
	margin: 8px 0 4px;
	line-height: 1.3;
`;

const markdownList = css`
	margin: 4px 0;
	padding-left: 18px;
	line-height: 1.55;

	li {
		margin-bottom: 2px;
	}
`;

const markdownLink = css`
	color: ${ colors.primary };
	text-decoration: none;
	font-weight: 500;

	&:hover {
		text-decoration: underline;
	}
`;

const codeBlockWrap = css`
	position: relative;
	margin: 6px 0;
	border-radius: ${ radii.sm };
	overflow: hidden;
	border: 1px solid ${ colors.borderLight };
`;

const codeBlockHeader = css`
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 4px ${ spacing.md };
	background: ${ colors.borderLight };
	border-bottom: 1px solid ${ colors.borderLight };
`;

const codeBlockLang = css`
	font-size: 10px;
	font-weight: 600;
	text-transform: uppercase;
	color: ${ colors.textSecondary };
	letter-spacing: 0.5px;
`;

const codeBlockCopyBtn = css`
	${ focusRing };
	display: inline-flex;
	align-items: center;
	gap: 4px;
	background: none;
	border: none;
	padding: 2px 6px;
	font-size: 10px;
	font-weight: 500;
	color: ${ colors.textMuted };
	cursor: pointer;
	border-radius: ${ radii.sm };
	transition: color 0.15s ease;

	&:hover {
		color: ${ colors.text };
	}
`;

const codeBlockBody = css`
	display: block;
	padding: ${ spacing.sm } ${ spacing.md };
	font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', monospace;
	font-size: 0.85em;
	line-height: 1.5;
	white-space: pre-wrap;
	background: ${ colors.bgSubtle };
	overflow-x: auto;
	margin: 0;
`;

const markdownHr = css`
	border: none;
	border-top: 1px solid ${ colors.borderLight };
	margin: 8px 0;
`;

/* ── Helpers ────────────────────────────────────────────────────── */

/**
 * Parse inline markdown (bold, code, links) within a single line.
 *
 * @param {string} text      Raw text with inline markdown.
 * @param {string} keyPrefix Prefix for React keys.
 * @return {Array} Array of React elements and strings.
 */
const parseInline = ( text, keyPrefix = '' ) => {
	const parts = [];
	let remaining = text;
	let idx = 0;

	while ( remaining.length > 0 ) {
		// Match: **bold**, `code`, or [link](url).
		const match = remaining.match(
			/(\*\*(.+?)\*\*|`([^`]+)`|\[([^\]]+)\]\(([^)]+)\))/
		);
		if ( ! match ) {
			parts.push( remaining );
			break;
		}

		const before = remaining.slice( 0, match.index );
		if ( before ) {
			parts.push( before );
		}

		if ( match[ 2 ] ) {
			// **bold**
			parts.push( <strong key={ `${ keyPrefix }b${ idx }` }>{ match[ 2 ] }</strong> );
		} else if ( match[ 3 ] ) {
			// `code`
			parts.push( <code key={ `${ keyPrefix }c${ idx }` }>{ match[ 3 ] }</code> );
		} else if ( match[ 4 ] && match[ 5 ] ) {
			// [text](url)
			parts.push(
				<a
					key={ `${ keyPrefix }a${ idx }` }
					href={ match[ 5 ] }
					target="_blank"
					rel="noopener noreferrer"
					className={ markdownLink }
				>
					{ match[ 4 ] }
				</a>
			);
		}

		remaining = remaining.slice( match.index + match[ 0 ].length );
		idx++;
	}

	return parts;
};

/* ── CodeBlock with copy ───────────────────────────────────────── */

const CodeBlock = ( { language, code } ) => {
	const [ copied, setCopied ] = useState( false );
	const timerRef = useRef( null );

	useEffect( () => {
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [] );

	const handleCopy = useCallback( () => {
		if ( ! navigator?.clipboard?.writeText ) {
			return;
		}
		navigator.clipboard.writeText( code ).then( () => {
			setCopied( true );
			timerRef.current = setTimeout( () => setCopied( false ), 2000 );
		} ).catch( () => {} );
	}, [ code ] );

	return (
		<div className={ codeBlockWrap }>
			<div className={ codeBlockHeader }>
				<span className={ codeBlockLang }>{ language || 'code' }</span>
				<button
					type="button"
					onClick={ handleCopy }
					className={ codeBlockCopyBtn }
				>
					{ copied ? <Check size={ 10 } /> : <Copy size={ 10 } /> }
					{ copied ? 'Copied' : 'Copy' }
				</button>
			</div>
			<pre className={ codeBlockBody }>
				<code>{ code }</code>
			</pre>
		</div>
	);
};

/**
 * Parse lightweight markdown into React elements.
 * @param text
 */
const parseMarkdown = ( text ) => {
	if ( ! text ) {
		return null;
	}

	const lines = text.split( '\n' );
	const elements = [];
	let i = 0;

	while ( i < lines.length ) {
		const line = lines[ i ];

		// Fenced code block (``` ... ```).
		if ( /^```/.test( line ) ) {
			const lang = line.replace( /^```/, '' ).trim();
			const codeLines = [];
			i++; // skip opening fence
			while ( i < lines.length && ! /^```/.test( lines[ i ] ) ) {
				codeLines.push( lines[ i ] );
				i++;
			}
			if ( i < lines.length ) {
				i++; // skip closing fence
			}
			elements.push(
				<CodeBlock
					key={ `code${ i }` }
					language={ lang }
					code={ codeLines.join( '\n' ) }
				/>
			);
			continue;
		}

		// Horizontal rule (--- or ***).
		if ( /^(-{3,}|\*{3,})$/.test( line.trim() ) ) {
			elements.push( <hr key={ `hr${ i }` } className={ markdownHr } /> );
			i++;
			continue;
		}

		// Heading (## or ###).
		const headingMatch = line.match( /^(#{2,3})\s+(.+)$/ );
		if ( headingMatch ) {
			const level = headingMatch[ 1 ].length;
			const HeadingTag = level === 2 ? 'h4' : 'h5';
			elements.push(
				<HeadingTag
					key={ i }
					className={ level === 2 ? markdownH2 : markdownH3 }
				>
					{ parseInline( headingMatch[ 2 ], `h${ i }-` ) }
				</HeadingTag>
			);
			i++;
			continue;
		}

		// Bullet list (- item or * item): collect consecutive lines.
		if ( /^[\-\*]\s+/.test( line ) ) {
			const items = [];
			while ( i < lines.length && /^[\-\*]\s+/.test( lines[ i ] ) ) {
				items.push( lines[ i ].replace( /^[\-\*]\s+/, '' ) );
				i++;
			}
			elements.push(
				<ul key={ `ul${ i }` } className={ markdownList }>
					{ items.map( ( item, j ) => (
						<li key={ j }>{ parseInline( item, `ul${ i }-${ j }-` ) }</li>
					) ) }
				</ul>
			);
			continue;
		}

		// Numbered list (1. item): collect consecutive lines.
		if ( /^\d+\.\s+/.test( line ) ) {
			const items = [];
			while ( i < lines.length && /^\d+\.\s+/.test( lines[ i ] ) ) {
				items.push( lines[ i ].replace( /^\d+\.\s+/, '' ) );
				i++;
			}
			elements.push(
				<ol key={ `ol${ i }` } className={ markdownList }>
					{ items.map( ( item, j ) => (
						<li key={ j }>{ parseInline( item, `ol${ i }-${ j }-` ) }</li>
					) ) }
				</ol>
			);
			continue;
		}

		// Empty line — paragraph break.
		if ( line.trim() === '' ) {
			elements.push( <br key={ `br${ i }` } /> );
			i++;
			continue;
		}

		// Regular line with inline formatting.
		elements.push(
			<span key={ i } style={ { display: 'inline' } }>
				{ parseInline( line, `l${ i }-` ) }
				{ i < lines.length - 1 ? '\n' : '' }
			</span>
		);
		i++;
	}

	return elements;
};

/* ── Relative Timestamp ────────────────────────────────────────── */

function formatRelativeTime( ts ) {
	if ( ! ts ) {
		return '';
	}
	const now = Date.now();
	const then = typeof ts === 'number' ? ts : new Date( ts ).getTime();
	if ( isNaN( then ) ) {
		return '';
	}
	const seconds = Math.floor( ( now - then ) / 1000 );
	if ( seconds < 10 ) {
		return 'just now';
	}
	if ( seconds < 60 ) {
		return `${ seconds }s ago`;
	}
	const minutes = Math.floor( seconds / 60 );
	if ( minutes < 60 ) {
		return `${ minutes }m ago`;
	}
	const hours = Math.floor( minutes / 60 );
	if ( hours < 24 ) {
		return `${ hours }h ago`;
	}
	return new Date( then ).toLocaleTimeString( undefined, {
		hour: '2-digit',
		minute: '2-digit',
	} );
}

/* ── Component ──────────────────────────────────────────────────── */

const MessageBubble = ( { role, content, timestamp } ) => {
	const [ copied, setCopied ] = useState( false );
	const timerRef = useRef( null );
	const isUser = role === 'user';

	// Clear copy timer on unmount.
	useEffect( () => {
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [] );

	const handleCopy = useCallback( () => {
		if ( ! navigator?.clipboard?.writeText ) {
			return;
		}
		navigator.clipboard.writeText( content ).then( () => {
			setCopied( true );
			timerRef.current = setTimeout( () => setCopied( false ), 2000 );
		} ).catch( () => {
			// Clipboard write failed — silently degrade.
		} );
	}, [ content ] );

	const timeLabel = formatRelativeTime( timestamp );

	return (
		<div>
			<div className={ isUser ? rowUser : rowAssistant }>
				<div className={ isUser ? avatarUser : avatarAssistant }>
					{ isUser ? <User size={ 14 } /> : <Bot size={ 14 } /> }
				</div>
				<div className={ isUser ? bubbleUser : bubbleAssistant }>
					{ isUser ? content : parseMarkdown( content ) }
					{ ! isUser && content && (
						<div className={ copyWrap }>
							<button
								type="button"
								onClick={ handleCopy }
								className={ cx( copyBtn, copied && copiedBtn ) }
								aria-label="Copy message"
							>
								{ copied ? (
									<Check size={ 12 } />
								) : (
									<Copy size={ 12 } />
								) }
							</button>
						</div>
					) }
				</div>
			</div>
			{ timeLabel && (
				<div className={ isUser ? timestampUser : timestampAssistant }>
					{ timeLabel }
				</div>
			) }
		</div>
	);
};

export default MessageBubble;
