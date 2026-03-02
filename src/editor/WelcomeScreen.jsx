/**
 * Welcome screen — shown when no messages exist.
 *
 * Three states:
 * 1. No API key     -> setup message + settings link.
 * 2. Has key (blank) -> build/design prompts based on post type.
 * 3. Has key (content) -> improve/extend prompts.
 *
 * Prompts are categorized for better visual hierarchy.
 *
 * @package
 * @since 1.0.0
 */

import { useMemo } from '@wordpress/element';
import { css } from '@emotion/css';
import {
	Bot,
	Settings,
	FileText,
	Pencil,
	LayoutGrid,
	Paintbrush,
	Globe,
	Search,
	Image,
	Sparkles,
	PlusCircle,
	Rocket,
	Type,
	Wrench,
} from 'lucide-react';
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
	margin-bottom: ${ spacing.lg };
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

const categoryLabel = css`
	font-size: 10px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: ${ colors.textMuted };
	margin-bottom: ${ spacing.xs };
	text-align: left;
	width: 100%;
`;

const promptList = css`
	width: 100%;
	display: flex;
	flex-direction: column;
	gap: ${ spacing.sm };
	margin-bottom: ${ spacing.md };
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

/* ── Prompt Sets (Categorized) ─────────────────────────────────── */

const PROMPTS_BLANK_PAGE = [
	{
		category: 'Build',
		prompts: [
			{
				icon: Rocket,
				label: 'Build a landing page',
				message: 'Build a professional landing page with a hero section, features grid, testimonials, and a call-to-action',
			},
			{
				icon: LayoutGrid,
				label: 'Add a hero section',
				message: 'Add a hero section with a heading, paragraph, and call-to-action button',
			},
		],
	},
	{
		category: 'Design',
		prompts: [
			{
				icon: Paintbrush,
				label: 'Design from a reference',
				message: 'I want to build a page inspired by a reference site. Let me share the URL.',
			},
			{
				icon: FileText,
				label: 'Draft a blog post',
				message: 'Help me draft a blog post about',
			},
		],
	},
];

const PROMPTS_BLANK_POST = [
	{
		category: 'Write',
		prompts: [
			{
				icon: FileText,
				label: 'Draft a blog post',
				message: 'Help me draft a blog post about',
			},
			{
				icon: Image,
				label: 'Post with featured image',
				message: 'Create a blog post and set a relevant featured image',
			},
		],
	},
	{
		category: 'Optimize',
		prompts: [
			{
				icon: Search,
				label: 'SEO-optimized article',
				message: 'Write an SEO-optimized blog post about',
			},
			{
				icon: LayoutGrid,
				label: 'Add content blocks',
				message: 'Add a hero section with a heading, paragraph, and call-to-action button',
			},
		],
	},
];

const PROMPTS_HAS_CONTENT = [
	{
		category: 'Improve',
		prompts: [
			{
				icon: Sparkles,
				label: 'Improve this content',
				message: 'Review and improve the current content — make it more engaging and polished',
			},
			{
				icon: Pencil,
				label: 'Rewrite in a different tone',
				message: 'Rewrite the current content in a more professional and engaging tone',
			},
		],
	},
	{
		category: 'Extend',
		prompts: [
			{
				icon: PlusCircle,
				label: 'Add a new section',
				message: 'Add a new section to this page. What would work well with the existing content?',
			},
			{
				icon: Search,
				label: 'Optimize for SEO',
				message: 'Analyze and optimize this content for search engines',
			},
		],
	},
];

const PROMPTS_PUBLISHED = [
	{
		category: 'Refresh',
		prompts: [
			{
				icon: Sparkles,
				label: 'Refresh this content',
				message: 'This is a published post — review it and suggest updates to keep it fresh and relevant',
			},
			{
				icon: PlusCircle,
				label: 'Extend with new sections',
				message: 'Add new sections to expand this published content',
			},
		],
	},
	{
		category: 'Optimize',
		prompts: [
			{
				icon: Type,
				label: 'Improve readability',
				message: 'Improve the readability and flow of this content while keeping the key message',
			},
			{
				icon: Globe,
				label: 'Optimize for SEO',
				message: 'Audit this published content for SEO and make improvements',
			},
		],
	},
];

function getPromptsForContext( context ) {
	if ( context.type === 'published' ) {
		return PROMPTS_PUBLISHED;
	}
	if ( context.type === 'has-content' ) {
		return PROMPTS_HAS_CONTENT;
	}
	if ( context.postType === 'page' ) {
		return PROMPTS_BLANK_PAGE;
	}
	return PROMPTS_BLANK_POST;
}

function getSubtitle( context ) {
	if ( context.type === 'published' ) {
		return 'I can help refresh and improve your published content.';
	}
	if ( context.type === 'has-content' ) {
		return 'I see you have content started. Want me to help improve it?';
	}
	if ( context.postType === 'page' ) {
		return 'Let me help you build a beautiful page.';
	}
	return 'Ask me to create content, edit posts, or manage your site.';
}

/* ── Helpers ────────────────────────────────────────────────────── */

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
		const base = parsed.href.endsWith( '/' ) ? parsed.href : parsed.href + '/';
		return `${ base }admin.php?page=wp-agent-settings`;
	} catch {
		return fallback;
	}
};

/* ── Component ──────────────────────────────────────────────────── */

const WelcomeScreen = ( { hasApiKey, onSendMessage, editorContext } ) => {
	const categories = useMemo(
		() => getPromptsForContext( editorContext ),
		[ editorContext.type, editorContext.postType ]
	);

	const subtitleText = useMemo(
		() => getSubtitle( editorContext ),
		[ editorContext.type, editorContext.postType ]
	);

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
			<p className={ subtitle }>{ subtitleText }</p>
			{ categories.map( ( cat ) => (
				<div key={ cat.category } style={ { width: '100%' } }>
					<p className={ categoryLabel }>{ cat.category }</p>
					<div className={ promptList }>
						{ cat.prompts.map( ( prompt ) => (
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
			) ) }
		</div>
	);
};

export default WelcomeScreen;
