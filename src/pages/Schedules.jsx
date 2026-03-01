import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Badge, Button, toast } from '@bsf/force-ui';
import {
	CalendarClock,
	Play,
	Pause,
	Trash2,
	RefreshCw,
	Loader2,
	Timer,
	CircleDot,
	Clock,
	Zap,
	AlarmClock,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const SCHEDULE_LABELS = {
	hourly: 'Every hour',
	twicedaily: 'Twice daily',
	daily: 'Daily',
	weekly: 'Weekly',
};

const SCHEDULE_COLORS = {
	hourly: { bg: 'bg-rose-50', text: 'text-rose-600' },
	twicedaily: { bg: 'bg-amber-50', text: 'text-amber-600' },
	daily: { bg: 'bg-blue-50', text: 'text-blue-600' },
	weekly: { bg: 'bg-emerald-50', text: 'text-emerald-600' },
};

function ScheduleRow( { task, onAction, loading, index } ) {
	const isActive = task.status === 'active';
	const isEven = index % 2 === 0;
	const freqColor = SCHEDULE_COLORS[ task.schedule ] || { bg: 'bg-background-secondary', text: 'text-text-secondary' };

	return (
		<tr className={ `border-b border-solid border-border-subtle last:border-b-0 hover:bg-blue-50/50 transition-colors duration-150 ${ isEven ? '' : 'bg-background-secondary/40' }` }>
			<td className="py-3.5 px-5">
				<div className="flex items-center gap-3">
					<div className={ `flex items-center justify-center size-8 rounded-lg ${ isActive ? 'bg-emerald-50' : 'bg-amber-50' } shrink-0` }>
						<Zap className={ `size-3.5 ${ isActive ? 'text-emerald-600' : 'text-amber-600' }` } />
					</div>
					<span className="text-sm font-medium text-text-primary">
						{ task.name }
					</span>
				</div>
			</td>
			<td className="py-3.5 px-5">
				<div className="flex flex-wrap gap-1">
					{ task.actions.map( ( action, i ) => (
						<Badge
							key={ i }
							label={ action.replace( /_/g, ' ' ) }
							variant="neutral"
							size="xs"
						/>
					) ) }
				</div>
			</td>
			<td className="py-3.5 px-5">
				<span className={ `inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md ${ freqColor.bg } ${ freqColor.text }` }>
					{ SCHEDULE_LABELS[ task.schedule ] || task.schedule }
				</span>
			</td>
			<td className="py-3.5 px-5">
				<Badge
					label={ task.status }
					variant={ isActive ? 'green' : 'yellow' }
					size="xs"
					className="capitalize"
				/>
			</td>
			<td className="py-3.5 px-5">
				<span className="text-xs text-text-tertiary">
					{ task.next_run || '-' }
				</span>
			</td>
			<td className="py-3.5 px-5">
				<span className="text-xs text-text-tertiary">
					{ task.last_run || 'Never' }
				</span>
			</td>
			<td className="py-3.5 px-5">
				<div className="flex items-center gap-1">
					<Button
						variant="ghost"
						size="xs"
						icon={ isActive ? <Pause size={ 14 } /> : <Play size={ 14 } /> }
						onClick={ () => onAction( task.id, isActive ? 'pause' : 'resume' ) }
						disabled={ loading }
						className={ isActive ? 'hover:text-amber-600' : 'hover:text-emerald-600' }
					/>
					<Button
						variant="ghost"
						size="xs"
						icon={ <Trash2 size={ 14 } /> }
						onClick={ () => onAction( task.id, 'delete' ) }
						disabled={ loading }
						className="text-text-tertiary hover:text-support-error"
					/>
				</div>
			</td>
		</tr>
	);
}

function TableHeader( { icon: Icon, label } ) {
	return (
		<th className="py-3 px-5 text-xs font-semibold text-text-secondary uppercase tracking-wider">
			<div className="flex items-center gap-1.5">
				{ Icon && <Icon className="size-3 text-icon-secondary" /> }
				{ label }
			</div>
		</th>
	);
}

export default function Schedules() {
	const [ tasks, setTasks ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ actionLoading, setActionLoading ] = useState( false );

	const fetchTasks = useCallback( async () => {
		try {
			setLoading( true );
			const data = await apiFetch( { path: '/wp-agent/v1/schedules' } );
			setTasks( data );
		} catch {
			toast.error( 'Failed to load scheduled tasks.' );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchTasks();
	}, [ fetchTasks ] );

	const handleAction = useCallback( async ( taskId, action ) => {
		if ( action === 'delete' && ! window.confirm( 'Delete this scheduled task?' ) ) {
			return;
		}

		try {
			setActionLoading( true );
			await apiFetch( {
				path: `/wp-agent/v1/schedules/${ taskId }/${ action }`,
				method: 'POST',
			} );
			toast.success(
				action === 'delete'
					? 'Task deleted.'
					: `Task ${ action }d.`
			);
			await fetchTasks();
		} catch ( err ) {
			toast.error( err?.message || `Failed to ${ action } task.` );
		} finally {
			setActionLoading( false );
		}
	}, [ fetchTasks ] );

	const activeCount = tasks.filter( ( t ) => t.status === 'active' ).length;

	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center justify-between mb-6">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-9 rounded-xl bg-emerald-50">
						<CalendarClock className="size-4.5 text-emerald-600" />
					</div>
					<div>
						<h1 className="text-xl font-bold text-text-primary">
							Schedules
						</h1>
						<p className="text-xs text-text-tertiary mt-0.5">
							{ tasks.length > 0
								? `${ activeCount } active of ${ tasks.length } scheduled task${ tasks.length !== 1 ? 's' : '' }`
								: 'Manage your automated tasks'
							}
						</p>
					</div>
				</div>
				<Button
					variant="outline"
					size="xs"
					icon={ <RefreshCw size={ 14 } className={ loading ? 'animate-spin' : '' } /> }
					onClick={ fetchTasks }
					disabled={ loading }
				>
					Refresh
				</Button>
			</div>

			{ loading ? (
				<div className="flex flex-col items-center justify-center py-20">
					<Loader2 size={ 28 } className="animate-spin text-brand-800 mb-3" />
					<p className="text-sm text-text-secondary">Loading tasks...</p>
				</div>
			) : tasks.length === 0 ? (
				<div className="relative overflow-hidden flex flex-col items-center justify-center rounded-2xl border border-solid border-border-subtle bg-background-primary p-16 shadow-sm text-center">
					<div className="absolute top-0 right-0 w-48 h-48 bg-emerald-100 rounded-full -translate-y-1/2 translate-x-1/3 opacity-40 blur-3xl pointer-events-none" />
					<div className="relative">
						<div className="flex items-center justify-center size-16 rounded-2xl bg-gradient-to-br from-emerald-50 to-cyan-50 mb-5 mx-auto">
							<CalendarClock size={ 28 } className="text-emerald-600" />
						</div>
						<h2 className="text-lg font-bold text-text-primary mb-2">
							No scheduled tasks yet
						</h2>
						<p className="text-sm text-text-secondary max-w-sm leading-relaxed">
							Ask JARVIS to create a scheduled task, like "Post a weekly
							SEO summary every Monday" or "Run site health checks daily".
						</p>
					</div>
				</div>
			) : (
				<div className="rounded-2xl border border-solid border-border-subtle bg-background-primary shadow-sm overflow-hidden">
					<div className="overflow-x-auto">
						<table className="w-full text-left border-collapse">
							<thead>
								<tr className="border-b border-solid border-border-subtle bg-gradient-to-r from-background-secondary to-background-primary">
									<TableHeader icon={ Zap } label="Task" />
									<TableHeader icon={ null } label="Actions" />
									<TableHeader icon={ Timer } label="Frequency" />
									<TableHeader icon={ CircleDot } label="Status" />
									<TableHeader icon={ AlarmClock } label="Next Run" />
									<TableHeader icon={ Clock } label="Last Run" />
									<TableHeader icon={ null } label="" />
								</tr>
							</thead>
							<tbody>
								{ tasks.map( ( task, i ) => (
									<ScheduleRow
										key={ task.id }
										task={ task }
										onAction={ handleAction }
										loading={ actionLoading }
										index={ i }
									/>
								) ) }
							</tbody>
						</table>
					</div>
				</div>
			) }
		</PageLayout>
	);
}
