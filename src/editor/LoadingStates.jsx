/**
 * Loading state indicators for the chat sidebar.
 *
 * - ThinkingIndicator  — pulsing dots while AI is processing, with elapsed time.
 * - ActionIndicator    — shown during tool-call execution (e.g. "Inserting blocks...").
 * - StepperIndicator   — multi-step progress with completed steps.
 * - SkeletonMessages   — placeholder shapes while conversation history loads.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { css } from '@emotion/css';
import { Bot, Blocks, Check, AlertCircle, Brain } from 'lucide-react';
import { colors, radii, spacing, fontSizes, fadeIn, pulse, shimmer } from './styles';

/* ── Elapsed Time Hook ──────────────────────────────────────────── */

function useElapsedTime() {
	const [ elapsed, setElapsed ] = useState( 0 );
	const startRef = useRef( Date.now() );

	useEffect( () => {
		startRef.current = Date.now();
		setElapsed( 0 );
		const interval = setInterval( () => {
			setElapsed( Math.floor( ( Date.now() - startRef.current ) / 1000 ) );
		}, 1000 );
		return () => clearInterval( interval );
	}, [] );

	if ( elapsed < 3 ) {
		return '';
	}
	if ( elapsed < 60 ) {
		return `${ elapsed }s`;
	}
	const m = Math.floor( elapsed / 60 );
	const s = elapsed % 60;
	return `${ m }:${ String( s ).padStart( 2, '0' ) }`;
}

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

const elapsedLabel = css`
	font-size: 10px;
	color: ${ colors.textMuted };
	font-weight: 400;
	font-variant-numeric: tabular-nums;
`;

const brainPulse = css`
	color: ${ colors.primary };
	animation: ${ pulse } 1.4s ease-in-out infinite;
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

export const ThinkingIndicator = () => {
	const time = useElapsedTime();

	return (
		<div className={ thinkingWrap }>
			<div className={ thinkingAvatar }>
				<Brain size={ 14 } className={ brainPulse } />
			</div>
			<div className={ thinkingBubble }>
				<span className={ thinkingLabel }>Thinking</span>
				<div className={ dotRow }>
					<span className={ thinkingDot0 } />
					<span className={ thinkingDot1 } />
					<span className={ thinkingDot2 } />
				</div>
				{ time && <span className={ elapsedLabel }>{ time }</span> }
			</div>
		</div>
	);
};

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

export const ActionIndicator = ( { label = 'Working on it...' } ) => {
	const time = useElapsedTime();

	return (
		<div className={ actionWrap }>
			<div className={ actionAvatar }>
				<Bot size={ 14 } />
			</div>
			<div className={ actionBubble }>
				<Blocks size={ 14 } className={ spinnerIcon } />
				<span className={ actionLabel }>{ label }</span>
				{ time && <span className={ elapsedLabel }>{ time }</span> }
			</div>
		</div>
	);
};

/* ── StepperIndicator ──────────────────────────────────────────── */

const stepperWrap = css`
	display: flex;
	align-items: flex-start;
	gap: ${ spacing.sm };
	margin-bottom: ${ spacing.md };
	animation: ${ fadeIn } 0.25s ease-out;
`;

const stepperAvatar = css`
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

const stepperBody = css`
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: ${ spacing.sm } ${ spacing.md };
	border-radius: ${ radii.lg };
	border-top-left-radius: 4px;
	background: ${ colors.primaryLight };
	border: 1px solid ${ colors.primaryLighter };
	min-width: 180px;
`;

const stepRow = css`
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: ${ fontSizes.xs };
	line-height: 1.4;
`;

const stepDone = css`
	color: #16a34a;
	flex-shrink: 0;
`;

const stepError = css`
	color: #dc2626;
	flex-shrink: 0;
`;

const stepLabel = css`
	color: ${ colors.textSecondary };
	font-weight: 500;
`;

const stepCurrent = css`
	color: ${ colors.primary };
	font-weight: 600;
`;

export const StepperIndicator = ( { completedSteps = [], currentLabel = 'Working on it...' } ) => {
	const time = useElapsedTime();

	return (
		<div className={ stepperWrap }>
			<div className={ stepperAvatar }>
				<Bot size={ 14 } />
			</div>
			<div className={ stepperBody }>
				{ completedSteps.map( ( step, i ) => (
					<div key={ i } className={ stepRow }>
						{ step.success
							? <Check size={ 13 } className={ stepDone } />
							: <AlertCircle size={ 13 } className={ stepError } />
						}
						<span className={ stepLabel }>{ step.label }</span>
					</div>
				) ) }
				<div className={ stepRow }>
					<Blocks size={ 13 } className={ spinnerIcon } />
					<span className={ stepCurrent }>{ currentLabel }</span>
					{ time && <span className={ elapsedLabel }>{ time }</span> }
				</div>
			</div>
		</div>
	);
};

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
