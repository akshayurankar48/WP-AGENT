import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Badge } from '@bsf/force-ui';
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
	CheckCircle2,
	XCircle,
	Clock,
	Cpu,
	Activity,
	Rocket,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';
import { STORE_NAME } from '../store/constants';

const { hasApiKey, adminUrl } = window.wpAgentData || {};

/* ── Animated Counter ──────────────────────────────────────────── */

function useCountUp( target, duration = 800 ) {
	const [ value, setValue ] = useState( 0 );
	const rafRef = useRef( null );

	useEffect( () => {
		if ( target === null || target === undefined ) {
			return;
		}

		const num = typeof target === 'number' ? target : parseInt( target, 10 );
		if ( isNaN( num ) || num === 0 ) {
			setValue( num || 0 );
			return;
		}

		const start = performance.now();
		const animate = ( now ) => {
			const elapsed = now - start;
			const progress = Math.min( elapsed / duration, 1 );
			// Ease-out cubic.
			const eased = 1 - Math.pow( 1 - progress, 3 );
			setValue( Math.round( eased * num ) );
			if ( progress < 1 ) {
				rafRef.current = requestAnimationFrame( animate );
			}
		};
		rafRef.current = requestAnimationFrame( animate );

		return () => {
			if ( rafRef.current ) {
				cancelAnimationFrame( rafRef.current );
			}
		};
	}, [ target, duration ] );

	return value;
}

/* ── Stat Card Colors ──────────────────────────────────────────── */

const STAT_STYLES = [
	{ bg: 'bg-violet-50', text: 'text-violet-600', border: 'border-l-violet-500' },
	{ bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-l-blue-500' },
	{ bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-l-amber-500' },
	{ bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-l-emerald-500' },
	{ bg: 'bg-rose-50', text: 'text-rose-600', border: 'border-l-rose-500' },
	{ bg: 'bg-cyan-50', text: 'text-cyan-600', border: 'border-l-cyan-500' },
];

function StatCard( { icon: Icon, label, value, variant = 'neutral', colorIndex = 0, href } ) {
	const style = STAT_STYLES[ colorIndex % STAT_STYLES.length ];
	const numericValue = typeof value === 'number' ? value : parseInt( value, 10 );
	const animatedValue = useCountUp(
		! isNaN( numericValue ) ? numericValue : null
	);
	const displayValue = ! isNaN( numericValue ) ? String( animatedValue ) : value;

	const content = (
		<div className={ `bg-background-primary border border-solid border-border-subtle border-l-4 ${ style.border } rounded-xl p-4 hover:shadow-md hover:border-border-interactive transition-all duration-200 ${ href ? 'cursor-pointer' : 'cursor-default' }` }>
			<div className="flex items-center gap-3">
				<div className={ `flex items-center justify-center size-10 rounded-lg ${ style.bg }` }>
					<Icon className={ `size-5 ${ style.text }` } />
				</div>
				<div className="min-w-0">
					<p className="text-xs font-medium text-text-tertiary uppercase tracking-wide">{ label }</p>
					<div className="flex items-center gap-2 mt-0.5">
						<span className="text-lg font-bold text-text-primary tabular-nums">
							{ displayValue }
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
			{ href && (
				<div className="flex items-center gap-1 mt-2 text-xs text-text-tertiary">
					<span>View details</span>
					<ArrowRight className="size-3" />
				</div>
			) }
		</div>
	);

	if ( href ) {
		return (
			<a href={ href } className="no-underline block">
				{ content }
			</a>
		);
	}
	return content;
}

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

/* ── Command Palette Cards ─────────────────────────────────────── */

const COMMANDS = [
	{
		icon: FileText,
		label: 'Create Post',
		description: 'Draft a new blog post with AI',
		prompt: 'Help me create a new blog post',
		color: { bg: 'bg-indigo-50', text: 'text-indigo-600', border: 'border-l-indigo-500' },
	},
	{
		icon: Rocket,
		label: 'Build Landing Page',
		description: 'Design a conversion-focused page',
		prompt: 'Build a professional landing page with hero, features, and CTA',
		color: { bg: 'bg-violet-50', text: 'text-violet-600', border: 'border-l-violet-500' },
	},
	{
		icon: Puzzle,
		label: 'Manage Plugins',
		description: 'Install, update, or configure plugins',
		prompt: 'List all installed plugins and their status',
		color: { bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-l-amber-500' },
	},
	{
		icon: HeartPulse,
		label: 'Site Health',
		description: 'Run diagnostics and health checks',
		prompt: 'Run a site health check and show me the results',
		color: { bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-l-emerald-500' },
	},
	{
		icon: Search,
		label: 'Configure SEO',
		description: 'Optimize your site for search engines',
		prompt: 'Analyze my site SEO and suggest improvements',
		color: { bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-l-blue-500' },
	},
	{
		icon: Sparkles,
		label: 'Chat with JARVIS',
		description: 'Ask anything about your site',
		prompt: '',
		color: { bg: 'bg-rose-50', text: 'text-rose-600', border: 'border-l-rose-500' },
	},
];

function CommandCard( { icon: Icon, label, description, prompt, color, onDispatch } ) {
	return (
		<button
			type="button"
			onClick={ () => onDispatch( prompt ) }
			className={ `group flex items-start gap-3 rounded-xl border border-solid border-border-subtle border-l-4 ${ color.border } p-4 no-underline hover:shadow-md hover:border-border-interactive transition-all duration-200 cursor-pointer bg-background-primary text-left w-full` }
		>
			<div className={ `flex items-center justify-center size-10 rounded-lg ${ color.bg } shrink-0 group-hover:scale-105 transition-transform duration-200` }>
				<Icon className={ `size-4.5 ${ color.text }` } />
			</div>
			<div className="flex-1 min-w-0">
				<p className="text-sm font-semibold text-text-primary">
					{ label }
				</p>
				<p className="text-xs text-text-secondary mt-0.5 leading-relaxed">{ description }</p>
			</div>
			<ArrowRight className="size-4 text-text-tertiary opacity-0 group-hover:opacity-100 transition-opacity duration-200 shrink-0 mt-1" />
		</button>
	);
}

/* ── Activity Feed Item ────────────────────────────────────────── */

function ActivityItem( { activity, onResume } ) {
	const isSuccess = activity.status === 'success';
	const actionName = ( activity.action || '' ).replace( /_/g, ' ' );

	return (
		<button
			type="button"
			onClick={ () => onResume( activity.conversation_id ) }
			className="group flex items-center gap-3 py-2.5 w-full text-left bg-transparent border-0 cursor-pointer rounded-lg hover:bg-background-secondary/60 px-2 -mx-2 transition-colors duration-150"
		>
			<div className={ `flex items-center justify-center size-7 rounded-full shrink-0 ${ isSuccess ? 'bg-emerald-50' : 'bg-red-50' }` }>
				{ isSuccess
					? <CheckCircle2 className="size-3.5 text-emerald-500" />
					: <XCircle className="size-3.5 text-red-500" />
				}
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

/* ── Main Component ────────────────────────────────────────────── */

export default function Dashboard() {
	const [ stats, setStats ] = useState( null );
	const [ quickInput, setQuickInput ] = useState( '' );
	const { loadConversation } = useDispatch( STORE_NAME );

	useEffect( () => {
		apiFetch( { path: '/wp-agent/v1/stats' } )
			.then( setStats )
			.catch( () => {} );
	}, [] );

	const editorUrl = `${ adminUrl }admin.php?page=wp-agent`;
	const settingsUrl = `${ adminUrl }admin.php?page=wp-agent-settings`;
	const historyUrl = `${ adminUrl }admin.php?page=wp-agent-history`;
	const schedulesUrl = `${ adminUrl }admin.php?page=wp-agent-schedules`;
	const usageUrl = `${ adminUrl }admin.php?page=wp-agent-history`;

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

	return (
		<PageLayout>
			{ /* Welcome Hero */ }
			<div className="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 rounded-2xl shadow-lg p-8 mb-6">
				<div className="absolute top-0 right-0 w-64 h-64 bg-white/20 rounded-full -translate-y-1/2 translate-x-1/3 opacity-30 blur-3xl pointer-events-none" />
				<div className="absolute bottom-0 left-0 w-48 h-48 bg-white/10 rounded-full translate-y-1/2 -translate-x-1/4 blur-2xl pointer-events-none" />
				<div className="relative">
					<div className="flex items-center gap-2 mb-2">
						<div className="flex items-center justify-center size-8 rounded-lg bg-white/20">
							<Zap className="size-4 text-white" />
						</div>
						<span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">AI-Powered</span>
					</div>
					<h1 className="text-2xl font-bold text-white mb-1.5">
						Welcome to <span className="bg-gradient-to-r from-yellow-300 to-orange-300 bg-clip-text text-transparent">JARVIS</span>
					</h1>
					<p className="text-sm text-white/80 mb-5 max-w-lg leading-relaxed">
						Your autonomous WordPress assistant with { stats ? stats.total_actions : '69+' } actions.
						Build pages, manage content, configure settings — all from natural language.
					</p>
					<div className="flex gap-3 mb-5">
						<button
							type="button"
							className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white text-indigo-700 font-semibold text-sm shadow-md hover:bg-white/90 transition-colors duration-200 border-none cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-600"
							onClick={ () => {
								window.location.href = `${ adminUrl }post-new.php`;
							} }
						>
							<ExternalLink className="size-4" />
							Open Editor
						</button>
						<button
							type="button"
							className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/15 text-white font-semibold text-sm hover:bg-white/25 transition-colors duration-200 border border-solid border-white/30 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-600"
							onClick={ () => {
								window.location.href = `${ adminUrl }post-new.php?wp-agent-demo=saas-landing`;
							} }
						>
							<Play className="size-4" />
							Try a Demo
						</button>
					</div>

					{ /* Quick Chat Input */ }
					<div className="flex items-center gap-2 max-w-lg">
						<div className="flex-1 flex items-center gap-2 bg-white/15 border border-solid border-white/30 rounded-lg px-3 py-2 focus-within:bg-white/20 focus-within:border-white/50 transition-all duration-200">
							<MessageSquare className="size-4 text-white/60 shrink-0" />
							<input
								type="text"
								placeholder="Ask JARVIS anything..."
								value={ quickInput }
								onChange={ ( e ) => setQuickInput( e.target.value ) }
								onKeyDown={ handleQuickKeyDown }
								className="flex-1 bg-transparent border-none outline-none text-sm text-white placeholder:text-white/50 font-medium"
							/>
						</div>
						<button
							type="button"
							onClick={ handleQuickSend }
							disabled={ ! quickInput.trim() }
							className="flex items-center justify-center size-9 rounded-lg bg-white text-indigo-700 border-none cursor-pointer hover:bg-white/90 transition-colors duration-200 disabled:opacity-40 disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
						>
							<Send className="size-4" />
						</button>
					</div>
				</div>
			</div>

			{ /* Stats Row */ }
			<div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
				<StatCard
					icon={ Zap }
					label="Actions"
					value={ stats ? stats.total_actions : '-' }
					colorIndex={ 0 }
				/>
				<StatCard
					icon={ MessageSquare }
					label="Chats"
					value={ stats ? stats.conversations : '-' }
					colorIndex={ 1 }
					href={ historyUrl }
				/>
				<StatCard
					icon={ History }
					label="Executed"
					value={ stats ? stats.actions_executed : '-' }
					colorIndex={ 2 }
					href={ usageUrl }
				/>
				<StatCard
					icon={ CalendarClock }
					label="Schedules"
					value={ stats ? stats.schedules_active : '-' }
					colorIndex={ 3 }
					href={ schedulesUrl }
				/>
				<StatCard
					icon={ Brain }
					label="Memories"
					value={ stats ? stats.memory_entries : '-' }
					colorIndex={ 4 }
				/>
				<StatCard
					icon={ Wifi }
					label="API"
					value={ hasApiKey ? 'Connected' : 'Not set' }
					variant={ hasApiKey ? 'green' : 'yellow' }
					colorIndex={ 5 }
					href={ settingsUrl }
				/>
			</div>

			{ /* Two-column grid: Activity Feed + Command Palette */ }
			<div className="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
				{ /* Recent Activity */ }
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6">
					<div className="flex items-center justify-between mb-4">
						<div className="flex items-center gap-2">
							<Activity className="size-4 text-text-secondary" />
							<h2 className="text-base font-bold text-text-primary">
								Recent Activity
							</h2>
						</div>
						{ recentActivity.length > 0 && (
							<a
								href={ historyUrl }
								className="text-xs font-medium text-brand-800 no-underline hover:underline"
							>
								View all
							</a>
						) }
					</div>
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
							<div className="flex items-center justify-center size-12 rounded-full bg-background-secondary mb-3">
								<Activity className="size-5 text-text-tertiary" />
							</div>
							<p className="text-sm text-text-secondary font-medium mb-1">No activity yet</p>
							<p className="text-xs text-text-tertiary">Start chatting with JARVIS to see your activity here</p>
						</div>
					) }
				</div>

				{ /* Command Palette */ }
				<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6">
					<div className="flex items-center gap-2 mb-4">
						<Sparkles className="size-4 text-text-secondary" />
						<h2 className="text-base font-bold text-text-primary">
							Quick Actions
						</h2>
					</div>
					<div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
						{ COMMANDS.map( ( cmd ) => (
							<CommandCard
								key={ cmd.label }
								{ ...cmd }
								onDispatch={ openDrawerWithPrompt }
							/>
						) ) }
					</div>
				</div>
			</div>

			{ /* System Status */ }
			<div className="bg-background-primary border border-solid border-border-subtle rounded-2xl shadow-sm p-6">
				<div className="flex items-center gap-2 mb-4">
					<Cpu className="size-4 text-text-secondary" />
					<h2 className="text-base font-bold text-text-primary">
						System Status
					</h2>
				</div>
				<div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
					<div className="flex items-center gap-3 p-3 rounded-lg bg-background-secondary/50">
						<div className={ `size-2.5 rounded-full ${ hasApiKey ? 'bg-emerald-500' : 'bg-amber-500' }` } />
						<div>
							<p className="text-xs font-medium text-text-primary">API Connection</p>
							<p className="text-xs text-text-tertiary">{ hasApiKey ? 'Connected and ready' : 'API key not configured' }</p>
						</div>
					</div>
					<div className="flex items-center gap-3 p-3 rounded-lg bg-background-secondary/50">
						<div className="size-2.5 rounded-full bg-emerald-500" />
						<div>
							<p className="text-xs font-medium text-text-primary">Actions Loaded</p>
							<p className="text-xs text-text-tertiary">{ stats ? `${ stats.total_actions } actions available` : 'Loading...' }</p>
						</div>
					</div>
					<div className="flex items-center gap-3 p-3 rounded-lg bg-background-secondary/50">
						<div className="size-2.5 rounded-full bg-emerald-500" />
						<div>
							<p className="text-xs font-medium text-text-primary">Requests Today</p>
							<p className="text-xs text-text-tertiary">{ stats ? `${ stats.requests_today } responses` : 'Loading...' }</p>
						</div>
					</div>
				</div>
			</div>
		</PageLayout>
	);
}
