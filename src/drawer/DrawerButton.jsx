/**
 * Floating action button to toggle the JARVIS drawer.
 *
 * @package
 * @since 1.0.0
 */

import { css, keyframes } from '@emotion/css';
import { Bot } from 'lucide-react';

const pulseRing = keyframes`
	0%   { transform: scale(1);   opacity: 0.5; }
	70%  { transform: scale(1.3); opacity: 0; }
	100% { transform: scale(1.3); opacity: 0; }
`;

const button = css`
	position: fixed;
	bottom: 24px;
	right: 24px;
	z-index: 99999;
	width: 48px;
	height: 48px;
	border-radius: 50%;
	border: none;
	background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
	color: #ffffff;
	cursor: pointer;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
	transition: transform 0.2s ease, box-shadow 0.2s ease;

	&::before {
		content: '';
		position: absolute;
		inset: 0;
		border-radius: 50%;
		background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
		animation: ${ pulseRing } 2s ease-out infinite;
		z-index: -1;
	}

	&:hover {
		transform: scale(1.08);
		box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
	}

	&:hover::before {
		animation: none;
	}

	&:active {
		transform: scale(0.96);
	}

	&:focus-visible {
		outline: 2px solid #4f46e5;
		outline-offset: 3px;
	}
`;

export default function DrawerButton( { onClick } ) {
	return (
		<button
			type="button"
			className={ button }
			onClick={ onClick }
			aria-label="Open JARVIS assistant"
		>
			<Bot size={ 22 } />
		</button>
	);
}
