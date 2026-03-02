import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
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
	CalendarClock,
	Brain,
	History,
	ArrowRight,
	Play,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const { hasApiKey, adminUrl } = window.wpAgentData || {};

function ChecklistItem( { done, label, action } ) {
	return (
		<div className="flex items-center justify-between py-3 group">
			<div className="flex items-center gap-3">
				{ done ? (
					<div className="flex items-center justify-center size-5 rounded-full bg-green-100">
						<CheckCircle2 className="size-3.5 text-green-600 shrink-0" />
					</div>
				) : (
					<div className="flex items-center justify-center size-5 rounded-full border border-solid border-border-subtle">
						<Circle className="size-3 text-icon-secondary shrink-0" />
					</div>
				) }
				<span
					className={ `text-sm ${
						done
							? 'text-text-tertiary line-through'
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

const STAT_STYLES = [
	{ bg: 'bg-violet-50', text: 'text-violet-600' },
	{ bg: 'bg-blue-50', text: 'text-blue-600' },
	{ bg: 'bg-amber-50', text: 'text-amber-600' },
	{ bg: 'bg-emerald-50', text: 'text-emerald-600' },
	{ bg: 'bg-rose-50', text: 'text-rose-600' },
	{ bg: 'bg-cyan-50', text: 'text-cyan-600' },
];

function StatCard( { icon: Icon, label, value, variant = 'neutral', colorIndex = 0 } ) {
	const style = STAT_STYLES[ colorIndex % STAT_STYLES.length ];
	return (
		<div className="bg-background-primary border border-solid border-border-subtle rounded-xl p-4 hover:shadow-md hover:border-border-interactive transition-all duration-200 cursor-default">
			<div className="flex items-center gap-3">
				<div className={ `flex items-center justify-center size-10 rounded-lg ${ style.bg }` }>
					<Icon className={ `size-5 ${ style.text }` } />
				</div>
				<div className="min-w-0">
					<p className="text-xs font-medium text-text-tertiary uppercase tracking-wide">{ label }</p>
					<div className="flex items-center gap-2 mt-0.5">
						<span className="text-lg font-bold text-text-primary tabular-nums">
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
			className="group flex items-center gap-3 rounded-xl border border-solid border-border-subtle p-4 no-underline hover:shadow-md hover:border-border-interactive transition-all duration-200"
		>
			<div className="flex items-center justify-center size-10 rounded-lg bg-background-secondary shrink-0 group-hover:bg-brand-primary-80 transition-colors duration-200">
				<Icon className="size-4.5 text-icon-secondary group-hover:text-brand-800 transition-colors duration-200" />
			</div>
			<div className="flex-1 min-w-0">
				<p className="text-sm font-semibold text-text-primary">
					{ label }
				</p>
				<p className="text-xs text-text-secondary mt-0.5 leading-relaxed">{ description }</p>
			</div>
			<ArrowRight className="size-4 text-icon-secondary opacity-0 group-hover:opacity-100 transition-opacity duration-200 shrink-0" />
		</a>
	);
}

export default function Dashboard() {
	const [ stats, setStats ] = useState( null );

	useEffect( () => {
		apiFetch( { path: '/wp-agent/v1/stats' } )
			.then( setStats )
			.catch( () => {} );
	}, [] );

	const settingsUrl = `${ adminUrl }admin.php?page=wp-agent-settings`;
	const capabilitiesUrl = `${ adminUrl }admin.php?page=wp-agent-capabilities`;
	const editorUrl = `${ adminUrl }post-new.php`;

	return (
		<PageLayout>
			{ /* Welcome Hero */ }
			<div className="relative overflow-hidden bg-gradient-to-br from-background-primary to-background-secondary border border-solid border-border-subtle rounded-2xl shadow-sm p-8 mb-6">
				<div className="absolute top-0 right-0 w-64 h-64 bg-brand-primary-80 rounded-full -translate-y-1/2 translate-x-1/3 opacity-30 blur-3xl pointer-events-none" />
				<div className="relative">
					<div className="flex items-center gap-2 mb-2">
						<div className="flex items-center justify-center size-8 rounded-lg bg-brand-primary-80">
							<Zap className="size-4 text-brand-800" />
						</div>
						<Badge label="AI-Powered" variant="neutral" size="xs" />
					</div>
					<h1 className="text-2xl font-bold text-text-primary mb-1.5">
						Welcome to JARVIS
					</h1>
					<p className="text-sm text-text-secondary mb-5 max-w-lg leading-relaxed">
						Your autonomous WordPress assistant with { stats ? stats.total_actions : '69+' } actions.
						Build pages, manage content, configure settings — all from natural language.
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
					<Button
						variant="outline"
						size="md"
						icon={ <Play className="size-4" /> }
						onClick={ () => {
							window.location.href = editorUrl + '?wp-agent-demo=saas-landing';
						} }
					>
						Try a Demo
					</Button>
				</div>
			</div>

			{ /* Stats Row */ }
			<div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
				<StatCard icon={ Zap } label="Actions" value={ stats ? String( stats.total_actions ) : '-' } colorIndex={ 0 } />
				<StatCard icon={ MessageSquare } label="Chats" value={ stats ? String( stats.conversations ) : '-' } colorIndex={ 1 } />
				<StatCard icon={ History } label="Executed" value={ stats ? String( stats.actions_executed ) : '-' } colorIndex={ 2 } />
				<StatCard icon={ CalendarClock } label="Schedules" value={ stats ? String( stats.schedules_active ) : '-' } colorIndex={ 3 } />
				<StatCard icon={ Brain } label="Memories" value={ stats ? String( stats.memory_entries ) : '-' } colorIndex={ 4 } />
				<StatCard icon={ Wifi } label="API" value={ hasApiKey ? 'Connected' : 'Not set' } variant={ hasApiKey ? 'green' : 'yellow' } colorIndex={ 5 } />
			</div>

			{ /* Two-column grid */ }
			<div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
				{ /* Setup Checklist */ }
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6">
					<div className="flex items-center justify-between mb-4">
						<h2 className="text-base font-bold text-text-primary">
							Setup Checklist
						</h2>
						{ hasApiKey && (
							<Badge label="Complete" variant="green" size="xs" />
						) }
					</div>
					<div className="divide-y divide-border-subtle">
						<ChecklistItem done={ true } label="Install WP Agent plugin" />
						<ChecklistItem
							done={ hasApiKey }
							label="Configure API key"
							action={
								<a href={ settingsUrl }>
									<Button variant="ghost" size="xs" icon={ <Settings className="size-3.5" /> }>
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
									<Button variant="ghost" size="xs" icon={ <Settings className="size-3.5" /> }>
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
									icon={ <ExternalLink className="size-3.5" /> }
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
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6">
					<h2 className="text-base font-bold text-text-primary mb-4">
						Quick Actions
					</h2>
					<div className="flex flex-col gap-3">
						<QuickActionItem
							icon={ FileText }
							label="Create a post"
							description="Open the editor and start building with AI"
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
							description={ `Browse all ${ stats ? stats.total_actions : '69+' } actions JARVIS can perform` }
							href={ capabilitiesUrl }
						/>
					</div>
				</div>
			</div>
		</PageLayout>
	);
}
