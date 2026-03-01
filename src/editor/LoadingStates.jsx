/**
 * Loading state indicators for the chat sidebar.
 *
 * - ThinkingIndicator  — pulsing dots while AI is processing.
 * - ActionIndicator    — shown during tool-call execution (e.g. "Inserting blocks...").
 * - SkeletonMessages   — placeholder shapes while conversation history loads.
 *
 * @package
 * @since 1.0.0
 */

import { css } from '@emotion/css';
import { Bot, Blocks } from 'lucide-react';
import { colors, radii, spacing, fontSizes, fadeIn, pulse, shimmer } from './styles';

/* ── ThinkingIndicator ──────────────────────────────────────────── */

const thinkingWrap = css`
	display: flex;
	align-items: flex-start;
	gap: ${ spacing.sm };
	margin-bottom: ${ spacing.md };
	animation: ${ fadeIn } 0.3s ease-out;
`;

const thinkingAvatar = css`
	flex-shrink: 0;
	width: 28px;
	height: 28px;
	border-radius: ${ radii.full };
	background: ${ colors.primaryLight };
	display: flex;
	align-items: center;
	justify-content: center;
	color: ${ colors.primary };
`;

const thinkingBubble = css`
	display: flex;
	align-items: center;
	gap: 6px;
	padding: ${ spacing.sm } ${ spacing.md };
	border-radius: ${ radii.lg };
	border-top-left-radius: 4px;
	background: ${ colors.bg };
	border: 1px solid ${ colors.border };
	box-shadow: 0 1px 2px ${ colors.shadow };
`;

const thinkingLabel = css`
	font-size: ${ fontSizes.xs };
	color: ${ colors.textSecondary };
	font-weight: 500;
`;

const dotRow = css`
	display: flex;
	align-items: center;
	gap: 3px;
`;

// Pre-compute all dot variants at module scope.
const thinkingDotBase = ( delay ) => css`
	width: 5px;
	height: 5px;
	border-radius: ${ radii.full };
	background: ${ colors.primary };
	animation: ${ pulse } 1.4s ease-in-out ${ delay }s infinite;
`;

const thinkingDot0 = thinkingDotBase( 0 );
const thinkingDot1 = thinkingDotBase( 0.2 );
const thinkingDot2 = thinkingDotBase( 0.4 );

export const ThinkingIndicator = () => (
	<div className={ thinkingWrap }>
		<div className={ thinkingAvatar }>
			<Bot size={ 14 } />
		</div>
		<div className={ thinkingBubble }>
			<span className={ thinkingLabel }>Thinking</span>
			<div className={ dotRow }>
				<span className={ thinkingDot0 } />
				<span className={ thinkingDot1 } />
				<span className={ thinkingDot2 } />
			</div>
		</div>
	</div>
);

/* ── ActionIndicator ────────────────────────────────────────────── */

const actionWrap = css`
	display: flex;
	align-items: flex-start;
	gap: ${ spacing.sm };
	margin-bottom: ${ spacing.md };
	animation: ${ fadeIn } 0.25s ease-out;
`;

const actionAvatar = css`
	flex-shrink: 0;
	width: 28px;
	height: 28px;
	border-radius: ${ radii.full };
	background: ${ colors.primaryLight };
	display: flex;
	align-items: center;
	justify-content: center;
	color: ${ colors.primary };
`;

const actionBubble = css`
	display: flex;
	align-items: center;
	gap: ${ spacing.sm };
	padding: ${ spacing.sm } ${ spacing.md };
	border-radius: ${ radii.lg };
	border-top-left-radius: 4px;
	background: ${ colors.primaryLight };
	border: 1px solid ${ colors.primaryLighter };
`;

const actionLabel = css`
	font-size: ${ fontSizes.xs };
	color: ${ colors.primary };
	font-weight: 500;
`;

const spinnerIcon = css`
	color: ${ colors.primary };
	animation: spin 1.2s linear infinite;

	@keyframes spin {
		to { transform: rotate(360deg); }
	}
`;

export const ActionIndicator = ( { label = 'Working on it...' } ) => (
	<div className={ actionWrap }>
		<div className={ actionAvatar }>
			<Bot size={ 14 } />
		</div>
		<div className={ actionBubble }>
			<Blocks size={ 14 } className={ spinnerIcon } />
			<span className={ actionLabel }>{ label }</span>
		</div>
	</div>
);

/* ── SkeletonMessages ───────────────────────────────────────────── */

const skeletonWrap = css`
	display: flex;
	flex-direction: column;
	gap: ${ spacing.lg };
	padding: ${ spacing.md };
`;

// Pre-compute skeleton row variants at module scope.
const skeletonRowBase = css`
	display: flex;
	align-items: flex-start;
	gap: ${ spacing.sm };
`;

const skeletonRowReverse = css`
	${ skeletonRowBase };
	flex-direction: row-reverse;
`;

const skeletonRowNormal = skeletonRowBase;

const skeletonCircle = css`
	flex-shrink: 0;
	width: 28px;
	height: 28px;
	border-radius: ${ radii.full };
	background: linear-gradient(
		90deg,
		${ colors.borderLight } 25%,
		${ colors.border } 50%,
		${ colors.borderLight } 75%
	);
	background-size: 200% 100%;
	animation: ${ shimmer } 1.5s ease-in-out infinite;
`;

// Pre-compute skeleton bubble widths at module scope.
const skeletonBubbleBase = ( width ) => css`
	height: 36px;
	width: ${ width };
	border-radius: ${ radii.lg };
	background: linear-gradient(
		90deg,
		${ colors.borderLight } 25%,
		${ colors.border } 50%,
		${ colors.borderLight } 75%
	);
	background-size: 200% 100%;
	animation: ${ shimmer } 1.5s ease-in-out infinite;
`;

const skeletonBubble60 = skeletonBubbleBase( '60%' );
const skeletonBubble75 = skeletonBubbleBase( '75%' );
const skeletonBubble45 = skeletonBubbleBase( '45%' );

export const SkeletonMessages = () => (
	<div className={ skeletonWrap }>
		<div className={ skeletonRowReverse }>
			<div className={ skeletonCircle } />
			<div className={ skeletonBubble60 } />
		</div>
		<div className={ skeletonRowNormal }>
			<div className={ skeletonCircle } />
			<div className={ skeletonBubble75 } />
		</div>
		<div className={ skeletonRowReverse }>
			<div className={ skeletonCircle } />
			<div className={ skeletonBubble45 } />
		</div>
	</div>
);
