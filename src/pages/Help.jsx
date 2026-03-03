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
import PageHeader from '../components/ui/PageHeader';
import CardShell from '../components/ui/CardShell';

const { adminUrl } = window.jarvisAiData || {};

const STEPS = [
	{
		icon: Zap,
		title: 'Install the plugin',
		description: 'You\'re here, so this step is already done. JARVIS AI is installed and active.',
		done: true,
	},
	{
		icon: Key,
		title: 'Configure your API key',
		description: 'Go to Settings and enter your OpenRouter API key. This connects JARVIS to AI models.',
		href: `${ adminUrl }admin.php?page=jarvis-ai-settings`,
	},
	{
		icon: Cpu,
		title: 'Choose a model',
		description: 'Select your preferred AI model in Settings. Start with GPT-4o Mini for a good balance of speed and quality.',
		href: `${ adminUrl }admin.php?page=jarvis-ai-settings`,
	},
	{
		icon: MessageSquareText,
		title: 'Open the editor and chat',
		description: 'Create or edit any post. The JARVIS sidebar will appear — type a command and watch it work.',
		href: `${ adminUrl }post-new.php`,
	},
];

const RESOURCES = [
	{
		icon: Github,
		label: 'GitHub Repository',
		description: 'Source code, issues, and releases',
		href: 'https://github.com/akshayurankar48/JARVIS-AI',
		color: { bg: 'bg-slate-50', text: 'text-slate-600' },
	},
	{
		icon: BookOpen,
		label: 'Documentation',
		description: 'Guides, API reference, and examples',
		href: 'https://github.com/akshayurankar48/JARVIS-AI#readme',
		color: { bg: 'bg-blue-50', text: 'text-blue-600' },
	},
	{
		icon: Sparkles,
		label: 'Capabilities',
		description: 'Browse all 69+ actions JARVIS can perform',
		href: `${ adminUrl }admin.php?page=jarvis-ai-capabilities`,
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
			<PageHeader
				title="Help"
				description="Get started with JARVIS and find resources"
			/>

			{ /* Getting Started */ }
			<CardShell className="p-6 mb-6" hover={ false }>
				<div>
					<div className="flex items-center gap-2 mb-4">
						<Rocket className="size-4 text-icon-secondary" />
						<h2 className="text-sm font-medium uppercase tracking-wider text-text-tertiary">
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
									<div className="flex items-center justify-center size-9 rounded-lg bg-background-secondary shrink-0">
										<Icon className="size-4 text-icon-secondary" />
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
			</CardShell>

			{ /* Three-column grid */ }
			<div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
				{ /* Resources */ }
				<CardShell className="p-5">
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
									className="group flex items-center gap-3 rounded-lg border border-solid border-border-subtle p-3 no-underline hover:shadow-sm hover:border-border-interactive transition-all duration-200"
								>
									<div className="flex items-center justify-center size-9 rounded-lg bg-background-secondary shrink-0">
										<Icon className="size-4 text-icon-secondary" />
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
				</CardShell>

				{ /* Example Prompts */ }
				<CardShell className="p-5">
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
				</CardShell>

				{ /* Need Help? */ }
				<CardShell className="p-5">
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
								'https://github.com/akshayurankar48/JARVIS-AI/issues',
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
				</CardShell>
			</div>
		</PageLayout>
	);
}
