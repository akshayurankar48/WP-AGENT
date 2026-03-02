import { useState } from '@wordpress/element';
import { Badge } from '@bsf/force-ui';
import {
	FileText,
	Paintbrush,
	Image,
	LayoutGrid,
	Navigation,
	Brain,
	Globe,
	MessageSquare,
	Settings,
	Puzzle,
	Users,
	HeartPulse,
	Wrench,
	Accessibility,
	Gauge,
	FlaskConical,
	CalendarClock,
	Undo2,
	ShoppingCart,
	Search,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const CATEGORIES = [
	{
		title: 'Content Management',
		icon: FileText,
		actions: [
			{ name: 'Create Post', slug: 'create_post', description: 'Create new posts and pages with titles, content, and metadata.' },
			{ name: 'Edit Post', slug: 'edit_post', description: 'Update existing post content, status, and properties.' },
			{ name: 'Delete Post', slug: 'delete_post', description: 'Move posts to trash or permanently delete them.' },
			{ name: 'Clone Post', slug: 'clone_post', description: 'Duplicate a post with all content, meta, taxonomies, and featured image.' },
			{ name: 'Read Blocks', slug: 'read_blocks', description: 'Read and parse the block structure of a post.' },
			{ name: 'Insert Blocks', slug: 'insert_blocks', description: 'Insert Gutenberg blocks into the editor with full block vocabulary.' },
			{ name: 'Search Posts', slug: 'search_posts', description: 'Search posts and pages by title, type, or status.' },
			{ name: 'Bulk Edit', slug: 'bulk_edit', description: 'Update multiple posts at once (status, category, author).' },
		],
	},
	{
		title: 'Template & Design',
		icon: Paintbrush,
		actions: [
			{ name: 'Get Page Templates', slug: 'get_page_templates', description: 'List available page templates from the active theme.' },
			{ name: 'Set Page Template', slug: 'set_page_template', description: 'Set the page template for a post or page.' },
			{ name: 'Edit Global Styles', slug: 'edit_global_styles', description: 'Update site-wide colors, typography, and spacing via theme.json.' },
			{ name: 'Add Custom CSS', slug: 'add_custom_css', description: 'Read, append, or replace the site\'s custom CSS.' },
			{ name: 'Screenshot Page', slug: 'screenshot_page', description: 'Capture a visual screenshot of any page on the site.' },
			{ name: 'Edit Template Parts', slug: 'edit_template_parts', description: 'Update header, footer, and sidebar template parts.' },
			{ name: 'Manage Theme', slug: 'manage_theme', description: 'Install, activate, or switch WordPress themes.' },
		],
	},
	{
		title: 'Media',
		icon: Image,
		actions: [
			{ name: 'Search Media', slug: 'search_media', description: 'Search the media library for images and files.' },
			{ name: 'Import Media', slug: 'import_media', description: 'Download and import images from external URLs.' },
			{ name: 'Generate Image', slug: 'generate_image', description: 'Generate AI images from text prompts.' },
			{ name: 'Set Featured Image', slug: 'set_featured_image', description: 'Set the featured image for a post or page.' },
		],
	},
	{
		title: 'Pattern Library',
		icon: LayoutGrid,
		actions: [
			{ name: 'List Patterns', slug: 'list_patterns', description: 'Browse 30+ curated section patterns across 15 categories.' },
			{ name: 'Get Pattern', slug: 'get_pattern', description: 'Get pattern blocks with customizable variable overrides.' },
			{ name: 'Create Pattern', slug: 'create_pattern', description: 'Save blocks as a reusable WordPress block pattern.' },
		],
	},
	{
		title: 'Navigation & Taxonomy',
		icon: Navigation,
		actions: [
			{ name: 'Manage Menus', slug: 'manage_menus', description: 'Create, update, and organize navigation menus.' },
			{ name: 'Manage Taxonomies', slug: 'manage_taxonomies', description: 'Manage categories, tags, and custom taxonomy terms.' },
		],
	},
	{
		title: 'Content Intelligence',
		icon: Brain,
		actions: [
			{ name: 'Read URL', slug: 'read_url', description: 'Fetch and extract content from external URLs.' },
			{ name: 'Web Search', slug: 'web_search', description: 'Search the web for research, competitors, and references.' },
			{ name: 'Generate Content', slug: 'generate_content', description: 'Generate long-form written content using AI.' },
			{ name: 'Analyze Reference Site', slug: 'analyze_reference_site', description: 'Extract design elements from a reference website.' },
			{ name: 'Manage SEO', slug: 'manage_seo', description: 'Get or update SEO metadata for posts and pages.' },
		],
	},
	{
		title: 'Site Building',
		icon: Globe,
		actions: [
			{ name: 'Generate Full Site', slug: 'generate_full_site', description: 'Generate a complete multi-page website from a description.' },
			{ name: 'Import Content', slug: 'import_content', description: 'Import content from CSV or JSON data.' },
			{ name: 'Export Site', slug: 'export_site', description: 'Export the entire site as a WXR XML file.' },
		],
	},
	{
		title: 'Comments',
		icon: MessageSquare,
		actions: [
			{ name: 'Manage Comments', slug: 'manage_comments', description: 'Approve, trash, spam, or reply to comments.' },
		],
	},
	{
		title: 'Settings & Configuration',
		icon: Settings,
		actions: [
			{ name: 'Update Settings', slug: 'update_settings', description: 'Modify site title, tagline, timezone, and more.' },
			{ name: 'Manage Permalinks', slug: 'manage_permalinks', description: 'Change permalink structure for better SEO.' },
			{ name: 'Manage Rewrite Rules', slug: 'manage_rewrite_rules', description: 'Manage WordPress rewrite rules and flush.' },
			{ name: 'Manage Options Bulk', slug: 'manage_options_bulk', description: 'Get, set, delete, or search WordPress options.' },
			{ name: 'Manage Redirects', slug: 'manage_redirects', description: 'Create and manage URL redirects.' },
		],
	},
	{
		title: 'Plugin Management',
		icon: Puzzle,
		actions: [
			{ name: 'Install Plugin', slug: 'install_plugin', description: 'Search and install plugins from the repository.' },
			{ name: 'Activate Plugin', slug: 'activate_plugin', description: 'Activate an installed plugin.' },
			{ name: 'Deactivate Plugin', slug: 'deactivate_plugin', description: 'Deactivate a plugin without uninstalling.' },
			{ name: 'List Plugins', slug: 'list_plugins', description: 'List all installed plugins with their status.' },
			{ name: 'Recommend Plugin', slug: 'recommend_plugin', description: 'Search and recommend plugins for specific needs.' },
		],
	},
	{
		title: 'User Management',
		icon: Users,
		actions: [
			{ name: 'Create User', slug: 'create_user', description: 'Add new WordPress users with roles and credentials.' },
			{ name: 'Manage Users', slug: 'manage_users', description: 'Update, delete, or change roles for existing users.' },
			{ name: 'List Users', slug: 'list_users', description: 'List WordPress users with optional role filter.' },
			{ name: 'Manage Roles', slug: 'manage_roles', description: 'Create, edit, or delete custom user roles.' },
		],
	},
	{
		title: 'System & Diagnostics',
		icon: HeartPulse,
		actions: [
			{ name: 'Site Health', slug: 'site_health', description: 'Run site health diagnostics and report status.' },
			{ name: 'Database Optimize', slug: 'database_optimize', description: 'Analyze and optimize the WordPress database.' },
			{ name: 'Manage Cron', slug: 'manage_cron', description: 'List, add, or remove WordPress cron events.' },
			{ name: 'Manage Transients', slug: 'manage_transients', description: 'View, delete, or flush cached transient data.' },
		],
	},
	{
		title: 'Utilities',
		icon: Wrench,
		actions: [
			{ name: 'Manage Widgets', slug: 'manage_widgets', description: 'Add, remove, or configure sidebar widgets.' },
			{ name: 'Bulk Find & Replace', slug: 'bulk_find_replace', description: 'Find and replace text across multiple posts.' },
			{ name: 'Generate Sitemap', slug: 'generate_sitemap', description: 'Generate sitemap XML and ping search engines.' },
			{ name: 'Manage Shortcodes', slug: 'manage_shortcodes', description: 'List shortcodes, preview output, or find usage.' },
		],
	},
	{
		title: 'Quality Assurance',
		icon: Accessibility,
		actions: [
			{ name: 'Audit Accessibility', slug: 'audit_accessibility', description: 'Check pages for WCAG accessibility issues.' },
			{ name: 'Optimize Performance', slug: 'optimize_performance', description: 'Analyze and optimize page performance.' },
		],
	},
	{
		title: 'Testing & Optimization',
		icon: FlaskConical,
		actions: [
			{ name: 'Manage A/B Test', slug: 'manage_ab_test', description: 'Create and manage content A/B tests with tracking.' },
		],
	},
	{
		title: 'Automation & Memory',
		icon: CalendarClock,
		actions: [
			{ name: 'Scheduled Tasks', slug: 'manage_scheduled_tasks', description: 'Create recurring action chains on a schedule.' },
			{ name: 'Manage Memory', slug: 'manage_memory', description: 'Store and recall persistent context across conversations.' },
		],
	},
	{
		title: 'Undo System',
		icon: Undo2,
		actions: [
			{ name: 'Undo Action', slug: 'undo_action', description: 'Revert any previous action by restoring its checkpoint.' },
		],
	},
	{
		title: 'WooCommerce',
		icon: ShoppingCart,
		badge: 'Conditional',
		actions: [
			{ name: 'Manage Products', slug: 'woo_manage_products', description: 'Create, update, and delete WooCommerce products.' },
			{ name: 'Manage Orders', slug: 'woo_manage_orders', description: 'View, update status, and manage orders.' },
			{ name: 'Manage Coupons', slug: 'woo_manage_coupons', description: 'Create and manage discount coupons.' },
			{ name: 'Manage Categories', slug: 'woo_manage_categories', description: 'Organize product categories and hierarchy.' },
			{ name: 'Manage Shipping', slug: 'woo_manage_shipping', description: 'Configure shipping zones and methods.' },
			{ name: 'Store Settings', slug: 'woo_manage_settings', description: 'Get or update WooCommerce store settings.' },
			{ name: 'Analytics', slug: 'woo_analytics', description: 'View sales, revenue, and order analytics.' },
			{ name: 'Manage Inventory', slug: 'woo_manage_inventory', description: 'Track and update product stock levels.' },
		],
	},
];

function ActionCard( { name, description, slug } ) {
	const handleTry = ( e ) => {
		e.stopPropagation();
		document.dispatchEvent( new CustomEvent( 'jarvis-open-drawer', {
			detail: { prompt: description },
		} ) );
	};

	return (
		<div className="group bg-background-primary border border-solid border-border-subtle rounded-xl p-4 flex flex-col gap-1.5 hover:shadow-md hover:border-border-interactive transition-all duration-200">
			<div className="flex items-center justify-between">
				<h3 className="text-sm font-semibold text-text-primary">
					{ name }
				</h3>
				<button
					type="button"
					onClick={ handleTry }
					className="opacity-0 group-hover:opacity-100 text-xs font-medium text-brand-800 bg-transparent border-0 cursor-pointer hover:underline transition-opacity px-0"
				>
					Try it
				</button>
			</div>
			<p className="text-xs text-text-secondary leading-relaxed">
				{ description }
			</p>
		</div>
	);
}

export default function Capabilities() {
	const [ filter, setFilter ] = useState( '' );
	const lowerFilter = filter.toLowerCase();

	const filtered = filter
		? CATEGORIES.map( ( cat ) => ( {
			...cat,
			actions: cat.actions.filter(
				( a ) =>
					a.name.toLowerCase().includes( lowerFilter ) ||
						a.slug.includes( lowerFilter ) ||
						a.description.toLowerCase().includes( lowerFilter )
			),
		  } ) ).filter( ( cat ) => cat.actions.length > 0 )
		: CATEGORIES;

	const totalActions = CATEGORIES.reduce(
		( sum, cat ) => sum + cat.actions.length,
		0
	);

	const visibleActions = filtered.reduce(
		( sum, cat ) => sum + cat.actions.length,
		0
	);

	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center justify-between mb-6">
				<div className="flex items-center gap-3">
					<h1 className="text-xl font-semibold text-text-primary">
						Capabilities
					</h1>
					<Badge
						label={ `${ totalActions } actions` }
						variant="neutral"
						size="xs"
					/>
				</div>
				<div className="relative">
					<Search size={ 14 } className="absolute left-3 top-1/2 -translate-y-1/2 text-icon-secondary pointer-events-none" />
					<input
						type="text"
						placeholder="Filter actions..."
						value={ filter }
						onChange={ ( e ) => setFilter( e.target.value ) }
						className="pl-8 pr-3 py-1.5 text-sm border border-solid border-border-subtle rounded-lg bg-background-primary text-text-primary placeholder:text-text-tertiary outline-none focus:border-border-interactive focus:ring-1 focus:ring-border-interactive w-56"
					/>
				</div>
			</div>

			{ filter && (
				<p className="text-xs text-text-tertiary mb-4">
					Showing { visibleActions } of { totalActions } actions
				</p>
			) }

			{ /* Category sections */ }
			<div className="flex flex-col gap-8">
				{ filtered.map( ( category ) => {
					const Icon = category.icon;
					return (
						<section key={ category.title }>
							<div className="flex items-center gap-2 mb-3">
								<Icon size={ 16 } className="text-icon-secondary" />
								<h2 className="text-base font-semibold text-text-primary">
									{ category.title }
								</h2>
								<Badge
									label={ String( category.actions.length ) }
									variant="neutral"
									size="xs"
								/>
								{ category.badge && (
									<Badge
										label={ category.badge }
										variant="yellow"
										size="xs"
									/>
								) }
							</div>
							<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
								{ category.actions.map( ( action ) => (
									<ActionCard
										key={ action.slug }
										name={ action.name }
										description={ action.description }
										slug={ action.slug }
									/>
								) ) }
							</div>
						</section>
					);
				} ) }
			</div>
		</PageLayout>
	);
}
