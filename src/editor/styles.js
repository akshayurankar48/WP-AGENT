/**
 * Shared Emotion styles — design tokens, keyframes, and reusable style fragments.
 *
 * @package
 * @since 1.0.0
 */

import { css, keyframes } from '@emotion/css';

/* ── Design Tokens ──────────────────────────────────────────────── */

export const colors = {
	primary: '#4f46e5',
	primaryHover: '#4338ca',
	primaryLight: '#eef2ff',
	primaryLighter: '#e0e7ff',

	userBubble: '#4f46e5',
	userBubbleGradient: 'linear-gradient(135deg, #4f46e5 0%, #6366f1 50%, #818cf8 100%)',
	assistantBg: '#ffffff',
	assistantBorder: '#e5e7eb',

	bg: '#ffffff',
	bgSubtle: '#f9fafb',
	border: '#e5e7eb',
	borderLight: '#f3f4f6',

	text: '#111827',
	textSecondary: '#6b7280',
	textMuted: '#9ca3af',
	textInverse: '#ffffff',

	success: '#10b981',
	successBg: '#ecfdf5',

	error: '#ef4444',
	errorHover: '#dc2626',
	errorBg: '#fef2f2',
	errorBorder: '#fecaca',
	errorText: '#b91c1c',

	shadow: 'rgba(0, 0, 0, 0.04)',
	shadowMd: 'rgba(0, 0, 0, 0.06)',
	shadowLg: 'rgba(0, 0, 0, 0.08)',

	headerGradient: 'linear-gradient(135deg, #ffffff 0%, #f9fafb 100%)',
};

export const spacing = {
	xs: '4px',
	sm: '8px',
	md: '12px',
	lg: '16px',
	xl: '20px',
	xxl: '24px',
};

export const radii = {
	sm: '6px',
	md: '10px',
	lg: '14px',
	xl: '18px',
	full: '9999px',
};

export const fontSizes = {
	xs: '11px',
	sm: '13px',
	base: '14px',
	md: '15px',
};

/* ── Keyframes ──────────────────────────────────────────────────── */

export const fadeIn = keyframes`
	from { opacity: 0; transform: translateY(4px); }
	to   { opacity: 1; transform: translateY(0); }
`;

export const pulse = keyframes`
	0%, 100% { opacity: 0.4; }
	50%      { opacity: 1; }
`;

export const bounce = keyframes`
	0%, 80%, 100% { transform: translateY(0); }
	40%           { transform: translateY(-5px); }
`;

export const shimmer = keyframes`
	0%   { background-position: -200% 0; }
	100% { background-position: 200% 0; }
`;

/* ── Shared Styles ──────────────────────────────────────────────── */

export const baseFont = css`
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
		Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
`;

export const resetBox = css`
	*,
	*::before,
	*::after {
		box-sizing: border-box;
	}

	h2,
	h3,
	h4,
	p {
		margin: 0;
		padding: 0;
	}

	textarea {
		font-family: inherit;
	}
`;

export const scrollbar = css`
	&::-webkit-scrollbar {
		width: 4px;
	}
	&::-webkit-scrollbar-track {
		background: transparent;
	}
	&::-webkit-scrollbar-thumb {
		background: ${ colors.borderLight };
		border-radius: ${ radii.full };
	}
	&::-webkit-scrollbar-thumb:hover {
		background: ${ colors.textMuted };
	}
`;

export const focusRing = css`
	&:focus-visible {
		outline: 2px solid ${ colors.primary };
		outline-offset: 2px;
	}
`;
