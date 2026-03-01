import { Badge } from '@bsf/force-ui';
import {
	FileText,
	FilePenLine,
	Trash2,
	LayoutGrid,
	BookOpen,
	Settings,
	Link,
	Download,
	Power,
	PowerOff,
	UserPlus,
	HeartPulse,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const CATEGORIES = [
	{
		title: 'Content Management',
		actions: [
			{
				icon: FileText,
				name: 'Create Post',
				slug: 'create_post',
				description:
					'Create new posts and pages with titles, content, and metadata.',
			},
			{
				icon: FilePenLine,
				name: 'Edit Post',
				slug: 'edit_post',
				description:
					'Update existing post content, status, and properties.',
			},
			{
				icon: Trash2,
				name: 'Delete Post',
				slug: 'delete_post',
				description:
					'Move posts to trash or permanently delete them.',
			},
			{
				icon: LayoutGrid,
				name: 'Insert Blocks',
				slug: 'insert_blocks',
				description:
					'Add Gutenberg blocks to posts with full block vocabulary.',
			},
			{
				icon: BookOpen,
				name: 'Read Blocks',
				slug: 'read_blocks',
				description:
					'Read and analyze existing block content from any post.',
			},
		],
	},
	{
		title: 'Settings & Configuration',
		actions: [
			{
				icon: Settings,
				name: 'Update Settings',
				slug: 'update_settings',
				description:
					'Modify WordPress options like site title, tagline, and timezone.',
			},
			{
				icon: Link,
				name: 'Manage Permalinks',
				slug: 'manage_permalinks',
				description:
					'Change permalink structure for better SEO and readability.',
			},
		],
	},
	{
		title: 'Plugin Management',
		actions: [
			{
				icon: Download,
				name: 'Install Plugin',
				slug: 'install_plugin',
				description:
					'Search and install plugins from the WordPress repository.',
			},
			{
				icon: Power,
				name: 'Activate Plugin',
				slug: 'activate_plugin',
				description:
					'Activate an installed plugin to enable its features.',
			},
			{
				icon: PowerOff,
				name: 'Deactivate Plugin',
				slug: 'deactivate_plugin',
				description:
					'Deactivate a plugin without uninstalling it.',
			},
		],
	},
	{
		title: 'Users & System',
		actions: [
			{
				icon: UserPlus,
				name: 'Create User',
				slug: 'create_user',
				description:
					'Add new WordPress users with specified roles and credentials.',
			},
			{
				icon: HeartPulse,
				name: 'Site Health',
				slug: 'site_health',
				description:
					'Run site health checks and report on system status.',
			},
		],
	},
];

function ActionCard( { icon: Icon, name, description } ) {
	return (
		<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-5 flex flex-col gap-3">
			<div className="flex items-center justify-center size-10 rounded-lg bg-background-secondary">
				<Icon className="size-5 text-icon-secondary" />
			</div>
			<div>
				<h3 className="text-sm font-semibold text-text-primary">
					{ name }
				</h3>
				<p className="text-xs text-text-secondary mt-1 leading-relaxed">
					{ description }
				</p>
			</div>
		</div>
	);
}

export default function Capabilities() {
	const totalActions = CATEGORIES.reduce(
		( sum, cat ) => sum + cat.actions.length,
		0
	);

	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center gap-3 mb-6">
				<h1 className="text-xl font-semibold text-text-primary">
					Capabilities
				</h1>
				<Badge
					label={ `${ totalActions } actions` }
					variant="neutral"
					size="xs"
				/>
			</div>

			{ /* Category sections */ }
			<div className="flex flex-col gap-8">
				{ CATEGORIES.map( ( category ) => (
					<section key={ category.title }>
						<div className="flex items-center gap-2 mb-4">
							<h2 className="text-base font-semibold text-text-primary">
								{ category.title }
							</h2>
							<Badge
								label={ String( category.actions.length ) }
								variant="neutral"
								size="xs"
							/>
						</div>
						<div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
							{ category.actions.map( ( action ) => (
								<ActionCard
									key={ action.slug }
									icon={ action.icon }
									name={ action.name }
									description={ action.description }
								/>
							) ) }
						</div>
					</section>
				) ) }
			</div>
		</PageLayout>
	);
}
