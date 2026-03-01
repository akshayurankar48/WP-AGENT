/**
 * Welcome screen — shown when no messages exist.
 *
 * Two states:
 * 1. No API key  -> setup message + settings link.
 * 2. Has key     -> greeting + suggested prompts.
 *
 * @package
 * @since 1.0.0
 */

import { css } from '@emotion/css';
import { Bot, Settings, FileText, Pencil, LayoutGrid } from 'lucide-react';
import { colors, radii, spacing, fontSizes, fadeIn, focusRing } from './styles';

/* ── Styles ─────────────────────────────────────────────────────── */

const container = css`
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 0 ${ spacing.xxl };
	text-align: center;
	animation: ${ fadeIn } 0.3s ease-out;
`;

const iconWrapBase = css`
	width: 56px;
	height: 56px;
	border-radius: ${ radii.full };
	display: flex;
	align-items: center;
	justify-content: center;
	margin-bottom: ${ spacing.lg };
`;

const iconWrapActive = css`
	${ iconWrapBase };
	background: ${ colors.primaryLight };
	color: ${ colors.primary };
`;

const iconWrapInactive = css`
	${ iconWrapBase };
	background: ${ colors.bgSubtle };
	color: ${ colors.textMuted };
`;

const title = css`
	font-size: ${ fontSizes.base };
	font-weight: 600;
	color: ${ colors.text };
	margin-bottom: 4px;
`;

const subtitle = css`
	font-size: ${ fontSizes.xs };
	color: ${ colors.textSecondary };
	line-height: 1.5;
	margin-bottom: ${ spacing.xxl };
`;

const settingsLink = css`
	${ focusRing };
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: ${ colors.primary };
	text-decoration: none;
	transition: color 0.15s ease;

	&:hover {
		color: ${ colors.primaryHover };
	}
`;

const promptList = css`
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: ${ spacing.sm };
`;

const promptCard = css`
	${ focusRing };
	width: 100%;
	display: flex;
	align-items: center;
	gap: ${ spacing.md };
	padding: ${ spacing.md } ${ spacing.lg };
	background: ${ colors.bg };
	border: 1px solid ${ colors.border };
	border-radius: ${ radii.md };
	cursor: pointer;
	text-align: left;
	transition: all 0.2s ease;

	&:hover {
		border-color: ${ colors.primaryLighter };
		background: ${ colors.primaryLight };
		box-shadow: 0 2px 8px ${ colors.shadow };
		transform: translateY(-1px);
	}

	&:hover svg {
		color: ${ colors.primary };
	}
`;

const promptIcon = css`
	flex-shrink: 0;
	color: ${ colors.textMuted };
	transition: color 0.2s ease;
`;

const promptLabel = css`
	font-size: ${ fontSizes.sm };
	color: ${ colors.textSecondary };
	font-weight: 450;
`;

/* ── Data ───────────────────────────────────────────────────────── */

const SUGGESTED_PROMPTS = [
	{
		icon: FileText,
		label: 'Draft a blog post',
		message: 'Help me draft a blog post about',
	},
	{
		icon: Pencil,
		label: 'Edit this content',
		message: 'Review and improve the current post content',
	},
	{
		icon: LayoutGrid,
		label: 'Add content blocks',
		message:
			'Add a hero section with a heading, paragraph, and call-to-action button',
	},
];

/* ── Helpers ────────────────────────────────────────────────────── */

/**
 * Build a safe settings URL. Validates that adminUrl from
 * wpAgentData is same-origin; falls back to a relative path.
 */
const getSafeSettingsUrl = () => {
	const { adminUrl } = window.wpAgentData || {};
	const fallback = '/wp-admin/admin.php?page=wp-agent-settings';

	if ( ! adminUrl ) {
		return fallback;
	}

	try {
		const parsed = new URL( adminUrl, window.location.origin );
		if ( parsed.origin !== window.location.origin ) {
			return fallback;
		}
		return `${ parsed.href }admin.php?page=wp-agent-settings`;
	} catch {
		return fallback;
	}
};

/* ── Component ──────────────────────────────────────────────────── */

const WelcomeScreen = ( { hasApiKey, onSendMessage } ) => {
	if ( ! hasApiKey ) {
		return (
			<div className={ container }>
				<div className={ iconWrapInactive }>
					<Bot size={ 28 } />
				</div>
				<h3 className={ title }>Set up JARVIS</h3>
				<p className={ subtitle }>
					Configure your API key to start using JARVIS.
				</p>
				<a
					href={ getSafeSettingsUrl() }
					className={ settingsLink }
					target="_blank"
					rel="noopener noreferrer"
				>
					<Settings size={ 14 } />
					Go to Settings
				</a>
			</div>
		);
	}

	return (
		<div className={ container }>
			<div className={ iconWrapActive }>
				<Bot size={ 28 } />
			</div>
			<h3 className={ title }>Hi! I&apos;m JARVIS.</h3>
			<p className={ subtitle }>
				Ask me to create content, edit posts, or manage your site.
			</p>
			<div className={ promptList }>
				{ SUGGESTED_PROMPTS.map( ( prompt ) => (
					<button
						key={ prompt.label }
						type="button"
						onClick={ () => onSendMessage( prompt.message ) }
						className={ promptCard }
					>
						<prompt.icon size={ 16 } className={ promptIcon } />
						<span className={ promptLabel }>{ prompt.label }</span>
					</button>
				) ) }
			</div>
		</div>
	);
};

export default WelcomeScreen;
