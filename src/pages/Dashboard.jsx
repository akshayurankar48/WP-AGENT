import { Button, Badge } from '@bsf/force-ui';
import {
	CheckCircle2,
	Circle,
	ExternalLink,
	Settings,
	Zap,
	MessageSquare,
	Wifi,
	FileText,
	Sliders,
	Sparkles,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const { hasApiKey, adminUrl } = window.wpAgentData || {};

function ChecklistItem( { done, label, action } ) {
	return (
		<div className="flex items-center justify-between py-2.5">
			<div className="flex items-center gap-3">
				{ done ? (
					<CheckCircle2 className="size-4 text-text-success shrink-0" />
				) : (
					<Circle className="size-4 text-icon-secondary shrink-0" />
				) }
				<span
					className={ `text-sm ${
						done
							? 'text-text-secondary line-through'
							: 'text-text-primary font-medium'
					}` }
				>
					{ label }
				</span>
			</div>
			{ ! done && action }
		</div>
	);
}

function StatCard( { icon: Icon, label, value, variant = 'neutral' } ) {
	return (
		<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-5">
			<div className="flex items-center gap-3">
				<div className="flex items-center justify-center size-10 rounded-lg bg-background-secondary">
					<Icon className="size-5 text-icon-secondary" />
				</div>
				<div>
					<p className="text-sm text-text-secondary">{ label }</p>
					<div className="flex items-center gap-2 mt-0.5">
						<span className="text-xl font-semibold text-text-primary">
							{ value }
						</span>
						{ variant !== 'neutral' && (
							<Badge
								label={ variant === 'green' ? 'Active' : 'Pending' }
								variant={ variant }
								size="xs"
							/>
						) }
					</div>
				</div>
			</div>
		</div>
	);
}

function QuickActionItem( { icon: Icon, label, description, href } ) {
	return (
		<a
			href={ href }
			className="flex items-center gap-3 rounded-lg border border-solid border-border-subtle p-3 no-underline hover:bg-background-secondary transition-colors"
		>
			<div className="flex items-center justify-center size-9 rounded-lg bg-background-secondary shrink-0">
				<Icon className="size-4 text-icon-secondary" />
			</div>
			<div className="flex-1 min-w-0">
				<p className="text-sm font-medium text-text-primary">
					{ label }
				</p>
				<p className="text-xs text-text-secondary">{ description }</p>
			</div>
		</a>
	);
}

export default function Dashboard() {
	const settingsUrl = `${ adminUrl }admin.php?page=wp-agent-settings`;
	const capabilitiesUrl = `${ adminUrl }admin.php?page=wp-agent-capabilities`;
	const editorUrl = `${ adminUrl }post-new.php`;

	return (
		<PageLayout>
			{ /* Welcome Hero */ }
			<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-6 mb-6">
				<h1 className="text-xl font-semibold text-text-primary mb-1">
					Welcome to JARVIS
				</h1>
				<p className="text-sm text-text-secondary mb-4">
					Your AI-powered WordPress assistant. Open the editor to
					start building with natural language.
				</p>
				<Button
					variant="primary"
					size="md"
					icon={ <ExternalLink className="size-4" /> }
					onClick={ () => {
						window.location.href = editorUrl;
					} }
				>
					Open Editor
				</Button>
			</div>

			{ /* Stats Row */ }
			<div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
				<StatCard
					icon={ Zap }
					label="Available Actions"
					value="12"
				/>
				<StatCard
					icon={ MessageSquare }
					label="Conversations"
					value="0"
				/>
				<StatCard
					icon={ Wifi }
					label="API Status"
					value={ hasApiKey ? 'Connected' : 'Not set' }
					variant={ hasApiKey ? 'green' : 'yellow' }
				/>
			</div>

			{ /* Two-column grid */ }
			<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
				{ /* Setup Checklist */ }
				<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-6">
					<div className="flex items-center justify-between mb-4">
						<h2 className="text-base font-semibold text-text-primary">
							Setup Checklist
						</h2>
						{ hasApiKey && (
							<Badge
								label="Complete"
								variant="green"
								size="xs"
							/>
						) }
					</div>
					<div className="divide-y divide-border-subtle">
						<ChecklistItem
							done={ true }
							label="Install WP Agent plugin"
						/>
						<ChecklistItem
							done={ hasApiKey }
							label="Configure API key"
							action={
								<a href={ settingsUrl }>
									<Button
										variant="ghost"
										size="xs"
										icon={
											<Settings className="size-3.5" />
										}
									>
										Settings
									</Button>
								</a>
							}
						/>
						<ChecklistItem
							done={ false }
							label="Select a preferred model"
							action={
								<a href={ settingsUrl }>
									<Button
										variant="ghost"
										size="xs"
										icon={
											<Settings className="size-3.5" />
										}
									>
										Settings
									</Button>
								</a>
							}
						/>
						<ChecklistItem
							done={ false }
							label="Send your first message"
							action={
								<Button
									variant="ghost"
									size="xs"
									icon={
										<ExternalLink className="size-3.5" />
									}
									onClick={ () => {
										window.location.href = editorUrl;
									} }
								>
									Open Editor
								</Button>
							}
						/>
					</div>
				</div>

				{ /* Quick Actions */ }
				<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm p-6">
					<h2 className="text-base font-semibold text-text-primary mb-4">
						Quick Actions
					</h2>
					<div className="flex flex-col gap-3">
						<QuickActionItem
							icon={ FileText }
							label="Create a post"
							description="Open the editor and start writing with AI"
							href={ editorUrl }
						/>
						<QuickActionItem
							icon={ Sliders }
							label="Configure settings"
							description="Set up your API key, model, and permissions"
							href={ settingsUrl }
						/>
						<QuickActionItem
							icon={ Sparkles }
							label="Explore capabilities"
							description="See all 12 actions JARVIS can perform"
							href={ capabilitiesUrl }
						/>
					</div>
				</div>
			</div>
		</PageLayout>
	);
}
