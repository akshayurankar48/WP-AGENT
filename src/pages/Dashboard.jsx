import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import {
	ExternalLink,
	Zap,
	MessageSquare,
	Wifi,
	CalendarClock,
	Brain,
	History,
	Play,
	FileText,
	Puzzle,
	HeartPulse,
	Search,
	Sparkles,
	ArrowRight,
	Send,
	Activity,
	Rocket,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';
import AIPulse from '../components/AIPulse';
import PromptGallery from '../components/PromptGallery';
import CardShell from '../components/ui/CardShell';
import SectionHeader from '../components/ui/SectionHeader';
import StatCard from '../components/ui/StatCard';
import { STORE_NAME } from '../store/constants';

const { hasApiKey, adminUrl } = window.jarvisAiData || {};

/* ── Relative time helper ──────────────────────────────────────── */

function relativeTime( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}
	const now = Date.now();
	const then = new Date( dateStr ).getTime();
	const seconds = Math.floor( ( now - then ) / 1000 );

	if ( seconds < 60 ) {
		return 'just now';
	}
	const minutes = Math.floor( seconds / 60 );
	if ( minutes < 60 ) {
		return `${ minutes }m ago`;
	}
	const hours = Math.floor( minutes / 60 );
	if ( hours < 24 ) {
		return `${ hours }h ago`;
	}
	const days = Math.floor( hours / 24 );
	if ( days < 7 ) {
		return `${ days }d ago`;
	}
	return new Date( dateStr ).toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
}

/* ── Quick Action Cards ───────────────────────────────────────── */

const COMMANDS = [
	{
		icon: ExternalLink,
		label: 'Open Editor',
		description: 'Create a new post with JARVIS',
		href: `${ adminUrl }post-new.php`,
	},
	{
		icon: Play,
		label: 'Try a Demo',
		description: 'See JARVIS build a SaaS page',
		href: `${ adminUrl }post-new.php?jarvis-ai-demo=saas-landing`,
	},
	{
		icon: FileText,
		label: 'Create Post',
		description: 'Draft a new blog post with AI',
		prompt: 'Help me create a new blog post',
	},
	{
		icon: Rocket,
		label: 'Build Landing Page',
		description: 'Design a conversion-focused page',
		prompt: 'Build a professional landing page with hero, features, and CTA',
	},
	{
		icon: Puzzle,
		label: 'Manage Plugins',
		description: 'Install, update, or configure plugins',
		prompt: 'List all installed plugins and their status',
	},
	{
		icon: HeartPulse,
		label: 'Site Health',
		description: 'Run diagnostics and health checks',
		prompt: 'Run a site health check and show me the results',
	},
];

function CommandCard( { icon: Icon, label, description, prompt, href, onDispatch } ) {
	const handleClick = () => {
		if ( href ) {
			window.location.href = href;
		} else {
			onDispatch( prompt );
		}
	};

	return (
		<button
			type="button"
			onClick={ handleClick }
			className="group flex items-start gap-3 rounded-lg border border-solid border-border-subtle p-3 no-underline hover:shadow-sm hover:border-border-interactive transition-all duration-200 cursor-pointer bg-background-primary text-left w-full"
		>
			<div className="flex items-center justify-center size-9 rounded-lg bg-indigo-50 shrink-0">
				<Icon className="size-4 text-indigo-600" />
			</div>
			<div className="flex-1 min-w-0">
				<p className="text-sm font-medium text-text-primary">
					{ label }
				</p>
				<p className="text-xs text-text-tertiary mt-0.5">{ description }</p>
			</div>
			<ArrowRight className="size-3.5 text-text-tertiary opacity-0 group-hover:opacity-100 transition-opacity duration-200 shrink-0 mt-1" />
		</button>
	);
}

/* ── Activity Feed Item ────────────────────────────────────────── */

function ActivityItem( { activity, onResume } ) {
	const actionName = ( activity.action || '' ).replace( /_/g, ' ' );

	return (
		<button
			type="button"
			onClick={ () => onResume( activity.conversation_id ) }
			className="group flex items-center gap-3 py-2.5 w-full text-left bg-transparent border-0 cursor-pointer rounded-lg hover:bg-background-secondary/60 px-2 -mx-2 transition-colors duration-150"
		>
			<div className="flex items-center justify-center size-7 rounded-lg bg-background-secondary shrink-0">
				<Activity className="size-3 text-icon-secondary" />
			</div>
			<div className="flex-1 min-w-0">
				<p className="text-sm font-medium text-text-primary truncate capitalize">
					{ actionName }
				</p>
				{ activity.conversation_title && (
					<p className="text-xs text-text-tertiary truncate">
						{ activity.conversation_title }
					</p>
				) }
			</div>
			<span className="text-xs text-text-tertiary shrink-0">
				{ relativeTime( activity.created_at ) }
			</span>
		</button>
	);
}

/* ── Greeting helper ───────────────────────────────────────────── */

function getGreeting() {
	const hour = new Date().getHours();
	if ( hour < 12 ) {
		return 'Good morning';
	}
	if ( hour < 17 ) {
		return 'Good afternoon';
	}
	return 'Good evening';
}

/* ── Main Component ────────────────────────────────────────────── */

export default function Dashboard() {
	const [ stats, setStats ] = useState( null );
	const [ quickInput, setQuickInput ] = useState( '' );
	const { loadConversation } = useDispatch( STORE_NAME );

	useEffect( () => {
		apiFetch( { path: '/jarvis-ai/v1/stats' } )
			.then( setStats )
			.catch( () => {} );
	}, [] );

	const settingsUrl = `${ adminUrl }admin.php?page=jarvis-ai-settings`;
	const historyUrl = `${ adminUrl }admin.php?page=jarvis-ai-history`;
	const schedulesUrl = `${ adminUrl }admin.php?page=jarvis-ai-schedules`;
	const usageUrl = `${ adminUrl }admin.php?page=jarvis-ai-history`;

	const openDrawerWithPrompt = useCallback( ( prompt ) => {
		document.dispatchEvent( new CustomEvent( 'jarvis-open-drawer', {
			detail: { prompt: prompt || '' },
		} ) );
	}, [] );

	const handleQuickSend = useCallback( () => {
		const trimmed = quickInput.trim();
		if ( ! trimmed ) {
			return;
		}
		openDrawerWithPrompt( trimmed );
		setQuickInput( '' );
	}, [ quickInput, openDrawerWithPrompt ] );

	const handleQuickKeyDown = useCallback( ( e ) => {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			handleQuickSend();
		}
	}, [ handleQuickSend ] );

	const handleResume = useCallback( ( id ) => {
		loadConversation( id );
		document.dispatchEvent( new CustomEvent( 'jarvis-open-drawer' ) );
	}, [ loadConversation ] );

	const recentActivity = stats?.recent_activity || [];
	const userName = window.jarvisAiData?.userName || '';

	return (
		<PageLayout>
			{ /* Welcome Bar */ }
			<CardShell className="p-5 mb-6" hover={ false }>
				<div className="flex items-center justify-between gap-4 flex-wrap">
					<div>
						<h1 className="text-xl font-semibold text-text-primary">
							{ getGreeting() }{ userName ? `, ${ userName }` : '' }
						</h1>
						<p className="text-sm text-text-tertiary mt-0.5">
							{ stats ? `${ stats.total_actions } actions ready` : 'Loading...' }
						</p>
					</div>
					<div className="flex items-center gap-2 w-full sm:w-auto sm:min-w-[320px]">
						<div className="flex-1 flex items-center gap-2 border border-solid border-border-subtle rounded-lg px-3 py-2 bg-background-primary focus-within:border-border-interactive focus-within:ring-1 focus-within:ring-border-interactive transition-all duration-200">
							<MessageSquare className="size-4 text-icon-secondary shrink-0" />
							<input
								type="text"
								placeholder="Ask JARVIS anything..."
								value={ quickInput }
								onChange={ ( e ) => setQuickInput( e.target.value ) }
								onKeyDown={ handleQuickKeyDown }
								className="flex-1 bg-transparent border-none outline-none text-sm text-text-primary placeholder:text-text-tertiary"
							/>
						</div>
						<button
							type="button"
							onClick={ handleQuickSend }
							disabled={ ! quickInput.trim() }
							className="flex items-center justify-center size-9 rounded-lg bg-indigo-600 text-white border-none cursor-pointer hover:bg-indigo-700 transition-colors duration-200 disabled:opacity-30 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 shrink-0"
						>
							<Send className="size-4" />
						</button>
					</div>
				</div>
			</CardShell>

			{ /* Stats Row */ }
			<div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6 overflow-hidden">
				<StatCard
					icon={ Zap }
					label="Actions"
					value={ stats ? stats.total_actions : '-' }
					accent="indigo"
				/>
				<StatCard
					icon={ MessageSquare }
					label="Chats"
					value={ stats ? stats.conversations : '-' }
					href={ historyUrl }
					accent="blue"
				/>
				<StatCard
					icon={ History }
					label="Executed"
					value={ stats ? stats.actions_executed : '-' }
					href={ usageUrl }
					accent="amber"
				/>
				<StatCard
					icon={ CalendarClock }
					label="Schedules"
					value={ stats ? stats.schedules_active : '-' }
					href={ schedulesUrl }
					accent="emerald"
				/>
				<StatCard
					icon={ Brain }
					label="Memories"
					value={ stats ? stats.memory_entries : '-' }
					accent="violet"
				/>
				<StatCard
					icon={ Wifi }
					label="API"
					value={ hasApiKey ? 'Connected' : 'Not set' }
					variant={ hasApiKey ? 'green' : 'yellow' }
					href={ settingsUrl }
					accent="cyan"
				/>
			</div>

			{ /* Two-column grid: Quick Actions + Recent Activity */ }
			<div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
				{ /* Quick Actions */ }
				<CardShell className="p-5" hover={ false }>
					<SectionHeader
						icon={ Sparkles }
						title="Quick Actions"
					/>
					<div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
						{ COMMANDS.map( ( cmd ) => (
							<CommandCard
								key={ cmd.label }
								{ ...cmd }
								onDispatch={ openDrawerWithPrompt }
							/>
						) ) }
					</div>
				</CardShell>

				{ /* Recent Activity */ }
				<CardShell className="p-5" hover={ false }>
					<SectionHeader
						icon={ Activity }
						title="Recent Activity"
						actions={
							recentActivity.length > 0
								? <a href={ historyUrl } className="text-xs font-medium text-text-tertiary no-underline hover:text-text-primary transition-colors">View all &rarr;</a>
								: null
						}
					/>
					{ recentActivity.length > 0 ? (
						<div className="flex flex-col divide-y divide-border-subtle">
							{ recentActivity.slice( 0, 7 ).map( ( act, i ) => (
								<ActivityItem
									key={ i }
									activity={ act }
									onResume={ handleResume }
								/>
							) ) }
						</div>
					) : (
						<div className="flex flex-col items-center justify-center py-8 text-center">
							<div className="flex items-center justify-center size-10 rounded-lg bg-background-secondary mb-3">
								<Activity className="size-4 text-icon-secondary" />
							</div>
							<p className="text-sm text-text-secondary font-medium mb-1">No activity yet</p>
							<p className="text-xs text-text-tertiary">Start chatting with JARVIS to see your activity here</p>
						</div>
					) }
				</CardShell>
			</div>

			{ /* AI Pulse News Feed */ }
			<div className="mb-6">
				<AIPulse />
			</div>

			{ /* Prompt Templates Gallery */ }
			<div className="mb-6">
				<PromptGallery
					onSelect={ openDrawerWithPrompt }
					limit={ 6 }
				/>
			</div>
		</PageLayout>
	);
}
