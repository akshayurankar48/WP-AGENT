import { Badge, Button } from '@bsf/force-ui';
import {
	ExternalLink,
	Github,
	BookOpen,
	Sparkles,
	Zap,
	Key,
	Cpu,
	MessageSquareText,
	Lightbulb,
	ArrowRight,
	HelpCircle,
	Rocket,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const { adminUrl } = window.wpAgentData || {};

const STEPS = [
	{
		number: '1',
		icon: Zap,
		title: 'Install the plugin',
		description: 'You\'re here, so this step is already done. WP Agent is installed and active.',
		color: { bg: 'bg-violet-50', text: 'text-violet-600', ring: 'ring-violet-200' },
		done: true,
	},
	{
		number: '2',
		icon: Key,
		title: 'Configure your API key',
		description: 'Go to Settings and enter your OpenRouter API key. This connects JARVIS to AI models.',
		color: { bg: 'bg-blue-50', text: 'text-blue-600', ring: 'ring-blue-200' },
		href: `${ adminUrl }admin.php?page=wp-agent-settings`,
	},
	{
		number: '3',
		icon: Cpu,
		title: 'Choose a model',
		description: 'Select your preferred AI model in Settings. Start with GPT-4o Mini for a good balance of speed and quality.',
		color: { bg: 'bg-amber-50', text: 'text-amber-600', ring: 'ring-amber-200' },
		href: `${ adminUrl }admin.php?page=wp-agent-settings`,
	},
	{
		number: '4',
		icon: MessageSquareText,
		title: 'Open the editor and chat',
		description: 'Create or edit any post. The JARVIS sidebar will appear — type a command and watch it work.',
		color: { bg: 'bg-emerald-50', text: 'text-emerald-600', ring: 'ring-emerald-200' },
		href: `${ adminUrl }post-new.php`,
	},
];

const RESOURCES = [
	{
		icon: Github,
		label: 'GitHub Repository',
		description: 'Source code, issues, and releases',
		href: 'https://github.com/akshayurankar48/WP-AGENT',
		color: { bg: 'bg-slate-50', text: 'text-slate-600' },
	},
	{
		icon: BookOpen,
		label: 'Documentation',
		description: 'Guides, API reference, and examples',
		href: 'https://github.com/akshayurankar48/WP-AGENT#readme',
		color: { bg: 'bg-blue-50', text: 'text-blue-600' },
	},
	{
		icon: Sparkles,
		label: 'Capabilities',
		description: 'Browse all 69+ actions JARVIS can perform',
		href: `${ adminUrl }admin.php?page=wp-agent-capabilities`,
		isInternal: true,
		color: { bg: 'bg-violet-50', text: 'text-violet-600' },
	},
];

const EXAMPLE_PROMPTS = [
	'Build a landing page for my SaaS product',
	'Create a blog post about AI in healthcare',
	'Set up my site with logo, colors, and navigation',
	'Audit my homepage for accessibility issues',
	'Install and configure an SEO plugin',
	'Generate product images for my store',
];

export default function Help() {
	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center gap-3 mb-6">
				<div className="flex items-center justify-center size-9 rounded-xl bg-amber-50">
					<HelpCircle className="size-4.5 text-amber-600" />
				</div>
				<div>
					<h1 className="text-xl font-bold text-text-primary">
						Help
					</h1>
					<p className="text-xs text-text-tertiary mt-0.5">
						Get started with JARVIS and find resources
					</p>
				</div>
			</div>

			{ /* Getting Started — Hero Card */ }
			<div className="relative overflow-hidden bg-gradient-to-br from-background-primary to-background-secondary border border-solid border-border-subtle rounded-2xl shadow-sm p-8 mb-6">
				<div className="absolute top-0 right-0 w-64 h-64 bg-amber-100 rounded-full -translate-y-1/2 translate-x-1/3 opacity-30 blur-3xl pointer-events-none" />
				<div className="relative">
					<div className="flex items-center gap-2 mb-4">
						<Rocket className="size-5 text-amber-600" />
						<h2 className="text-base font-bold text-text-primary">
							Getting Started
						</h2>
						<Badge label="4 steps" variant="neutral" size="xs" />
					</div>
					<div className="flex flex-col gap-4">
						{ STEPS.map( ( step ) => {
							const Icon = step.icon;
							return (
								<div
									key={ step.number }
									className="flex items-start gap-4 group"
								>
									<div className={ `flex items-center justify-center size-10 rounded-xl ${ step.color.bg } ring-1 ${ step.color.ring } shrink-0 transition-transform duration-200 group-hover:scale-105` }>
										<Icon className={ `size-4.5 ${ step.color.text }` } />
									</div>
									<div className="flex-1 pt-1">
										<div className="flex items-center gap-2">
											<h3 className="text-sm font-semibold text-text-primary">
												{ step.title }
											</h3>
											{ step.done && (
												<Badge label="Done" variant="green" size="xs" />
											) }
										</div>
										<p className="text-sm text-text-secondary mt-0.5 leading-relaxed">
											{ step.description }
										</p>
									</div>
									{ step.href && (
										<a
											href={ step.href }
											className="shrink-0 mt-1 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
										>
											<ArrowRight className="size-4 text-icon-secondary" />
										</a>
									) }
								</div>
							);
						} ) }
					</div>
				</div>
			</div>

			{ /* Three-column grid */ }
			<div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
				{ /* Resources */ }
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
					<h2 className="text-base font-bold text-text-primary mb-4">
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
									className="group flex items-center gap-3 rounded-xl border border-solid border-border-subtle p-3.5 no-underline hover:shadow-sm hover:border-border-interactive transition-all duration-200"
								>
									<div className={ `flex items-center justify-center size-9 rounded-lg ${ resource.color.bg } shrink-0` }>
										<Icon className={ `size-4 ${ resource.color.text }` } />
									</div>
									<div className="flex-1 min-w-0">
										<p className="text-sm font-semibold text-text-primary">
											{ resource.label }
										</p>
										<p className="text-xs text-text-secondary">
											{ resource.description }
										</p>
									</div>
									{ ! resource.isInternal && (
										<ExternalLink className="size-3.5 text-icon-secondary opacity-0 group-hover:opacity-100 transition-opacity duration-200 shrink-0" />
									) }
								</a>
							);
						} ) }
					</div>
				</div>

				{ /* Example Prompts */ }
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
					<div className="flex items-center gap-2 mb-4">
						<Lightbulb className="size-4 text-amber-500" />
						<h2 className="text-base font-bold text-text-primary">
							Try Saying
						</h2>
					</div>
					<div className="flex flex-col gap-2">
						{ EXAMPLE_PROMPTS.map( ( prompt ) => (
							<div
								key={ prompt }
								className="flex items-start gap-2.5 rounded-lg bg-background-secondary/60 border border-solid border-border-subtle px-3.5 py-2.5"
							>
								<MessageSquareText className="size-3.5 text-violet-500 shrink-0 mt-0.5" />
								<p className="text-xs text-text-secondary leading-relaxed italic">
									"{ prompt }"
								</p>
							</div>
						) ) }
					</div>
				</div>

				{ /* Need Help? */ }
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6 hover:shadow-md transition-shadow duration-200">
					<h2 className="text-base font-bold text-text-primary mb-2">
						Need Help?
					</h2>
					<p className="text-sm text-text-secondary mb-5 leading-relaxed">
						Found a bug or have a feature request? Open an issue on
						GitHub and we'll get back to you.
					</p>
					<Button
						variant="outline"
						size="md"
						icon={ <Github className="size-4" /> }
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
					<div className="mt-5 pt-5 border-t border-solid border-border-subtle">
						<h3 className="text-sm font-semibold text-text-primary mb-2">
							Quick Tips
						</h3>
						<ul className="flex flex-col gap-1.5 text-xs text-text-secondary leading-relaxed">
							<li className="flex items-start gap-2">
								<span className="text-emerald-500 mt-px">*</span>
								Be specific in your prompts for best results
							</li>
							<li className="flex items-start gap-2">
								<span className="text-emerald-500 mt-px">*</span>
								JARVIS can undo any action it takes
							</li>
							<li className="flex items-start gap-2">
								<span className="text-emerald-500 mt-px">*</span>
								Use web search for research-driven content
							</li>
							<li className="flex items-start gap-2">
								<span className="text-emerald-500 mt-px">*</span>
								Set brand colors for consistent designs
							</li>
						</ul>
					</div>
				</div>
			</div>
		</PageLayout>
	);
}
