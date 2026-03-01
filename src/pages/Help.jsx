import { Button } from '@bsf/force-ui';
import {
	ExternalLink,
	Github,
	BookOpen,
	Sparkles,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const { adminUrl } = window.wpAgentData || {};

const STEPS = [
	{
		number: '1',
		title: 'Install the plugin',
		description:
			'You\'re here, so this step is already done. WP Agent is installed and active.',
	},
	{
		number: '2',
		title: 'Configure your API key',
		description:
			'Go to Settings and enter your OpenRouter API key. This connects JARVIS to AI models.',
	},
	{
		number: '3',
		title: 'Choose a model',
		description:
			'Select your preferred AI model in Settings. Start with GPT-4o Mini for a good balance of speed and quality.',
	},
	{
		number: '4',
		title: 'Open the editor and chat',
		description:
			'Create or edit any post. The JARVIS sidebar will appear — type a command and watch it work.',
	},
];

const RESOURCES = [
	{
		icon: Github,
		label: 'GitHub Repository',
		description: 'Source code, issues, and releases',
		href: 'https://github.com/akshayurankar48/WP-AGENT',
	},
	{
		icon: BookOpen,
		label: 'Documentation',
		description: 'Guides, API reference, and examples',
		href: 'https://github.com/akshayurankar48/WP-AGENT#readme',
	},
	{
		icon: Sparkles,
		label: 'Capabilities',
		description: 'See all 12 actions JARVIS can perform',
		href: `${ adminUrl }admin.php?page=wp-agent-capabilities`,
		isInternal: true,
	},
];

export default function Help() {
	return (
		<PageLayout>
			{ /* Header */ }
			<div className="mb-6">
				<h1 className="text-xl font-semibold text-text-primary">
					Help
				</h1>
				<p className="text-sm text-text-secondary mt-0.5">
					Get started with JARVIS and find resources
				</p>
			</div>

			{ /* Getting Started */ }
			<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-6 mb-6">
				<h2 className="text-base font-semibold text-text-primary mb-5">
					Getting Started
				</h2>
				<div className="flex flex-col gap-5">
					{ STEPS.map( ( step ) => (
						<div
							key={ step.number }
							className="flex items-start gap-4"
						>
							<div className="flex items-center justify-center size-8 rounded-full bg-brand-800 text-white text-sm font-semibold shrink-0">
								{ step.number }
							</div>
							<div className="pt-0.5">
								<h3 className="text-sm font-semibold text-text-primary">
									{ step.title }
								</h3>
								<p className="text-sm text-text-secondary mt-0.5 leading-relaxed">
									{ step.description }
								</p>
							</div>
						</div>
					) ) }
				</div>
			</div>

			{ /* Two-column grid */ }
			<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
				{ /* Resources */ }
				<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-6">
					<h2 className="text-base font-semibold text-text-primary mb-4">
						Resources
					</h2>
					<div className="flex flex-col gap-3">
						{ RESOURCES.map( ( resource ) => {
							const Icon = resource.icon;
							return (
								<a
									key={ resource.label }
									href={ resource.href }
									{ ...( resource.isInternal
										? {}
										: {
											target: '_blank',
											rel: 'noopener noreferrer',
										  } ) }
									className="flex items-center gap-3 rounded-lg border border-solid border-border-subtle p-3 no-underline hover:bg-background-secondary transition-colors"
								>
									<div className="flex items-center justify-center size-9 rounded-lg bg-background-secondary shrink-0">
										<Icon className="size-4 text-icon-secondary" />
									</div>
									<div className="flex-1 min-w-0">
										<p className="text-sm font-medium text-text-primary">
											{ resource.label }
										</p>
										<p className="text-xs text-text-secondary">
											{ resource.description }
										</p>
									</div>
									{ ! resource.isInternal && (
										<ExternalLink className="size-4 text-icon-secondary shrink-0" />
									) }
								</a>
							);
						} ) }
					</div>
				</div>

				{ /* Need Help? */ }
				<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-6">
					<h2 className="text-base font-semibold text-text-primary mb-2">
						Need Help?
					</h2>
					<p className="text-sm text-text-secondary mb-4 leading-relaxed">
						Found a bug or have a feature request? Open an issue on
						GitHub and we'll get back to you.
					</p>
					<Button
						variant="outline"
						size="md"
						icon={ <ExternalLink className="size-4" /> }
						onClick={ () => {
							window.open(
								'https://github.com/akshayurankar48/WP-AGENT/issues',
								'_blank',
								'noopener,noreferrer'
							);
						} }
					>
						Open an Issue
					</Button>
				</div>
			</div>
		</PageLayout>
	);
}
