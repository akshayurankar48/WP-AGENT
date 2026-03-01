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
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const SCHEDULE_LABELS = {
	hourly: 'Hourly',
	twicedaily: 'Twice Daily',
	daily: 'Daily',
	weekly: 'Weekly',
};

const STATUS_VARIANTS = {
	active: 'green',
	paused: 'yellow',
};

function ScheduleRow( { task, onAction, loading } ) {
	const isActive = task.status === 'active';

	return (
		<tr className="border-b border-solid border-border-subtle last:border-b-0 hover:bg-background-secondary transition-colors">
			<td className="py-3 px-4">
				<span className="text-sm font-medium text-text-primary">
					{ task.name }
				</span>
			</td>
			<td className="py-3 px-4">
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
			<td className="py-3 px-4">
				<span className="text-sm text-text-secondary">
					{ SCHEDULE_LABELS[ task.schedule ] || task.schedule }
				</span>
			</td>
			<td className="py-3 px-4">
				<Badge
					label={ task.status }
					variant={ STATUS_VARIANTS[ task.status ] || 'neutral' }
					size="xs"
				/>
			</td>
			<td className="py-3 px-4">
				<span className="text-xs text-text-tertiary">
					{ task.next_run || '-' }
				</span>
			</td>
			<td className="py-3 px-4">
				<span className="text-xs text-text-tertiary">
					{ task.last_run || 'Never' }
				</span>
			</td>
			<td className="py-3 px-4">
				<div className="flex items-center gap-1">
					<Button
						variant="ghost"
						size="xs"
						icon={ isActive ? <Pause size={ 14 } /> : <Play size={ 14 } /> }
						onClick={ () => onAction( task.id, isActive ? 'pause' : 'resume' ) }
						disabled={ loading }
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
					<h1 className="text-xl font-semibold text-text-primary">
						Schedules
					</h1>
					{ tasks.length > 0 && (
						<Badge
							label={ `${ activeCount } active / ${ tasks.length } total` }
							variant="neutral"
							size="xs"
						/>
					) }
				</div>
				<Button
					variant="ghost"
					size="xs"
					icon={ <RefreshCw size={ 14 } /> }
					onClick={ fetchTasks }
					disabled={ loading }
				>
					Refresh
				</Button>
			</div>

			{ loading ? (
				<div className="flex items-center justify-center py-16">
					<Loader2 size={ 24 } className="animate-spin text-icon-secondary" />
				</div>
			) : tasks.length === 0 ? (
				<div className="flex flex-col items-center justify-center rounded-xl border border-solid border-border-subtle bg-background-primary p-12 shadow-sm text-center">
					<div className="flex items-center justify-center w-16 h-16 rounded-full bg-background-secondary mb-4">
						<CalendarClock size={ 28 } className="text-icon-secondary" />
					</div>
					<h2 className="text-lg font-semibold text-text-primary mb-2">
						No scheduled tasks yet
					</h2>
					<p className="text-sm text-text-secondary max-w-md">
						Ask JARVIS to create a scheduled task, like "Post a weekly
						SEO summary every Monday" or "Run site health checks daily".
						Tasks will appear here for management.
					</p>
				</div>
			) : (
				<div className="rounded-xl border border-solid border-border-subtle bg-background-primary shadow-sm overflow-x-auto">
					<table className="w-full text-left border-collapse">
						<thead>
							<tr className="border-b border-solid border-border-subtle bg-background-secondary">
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									Name
								</th>
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									Actions
								</th>
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									Frequency
								</th>
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									Status
								</th>
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									Next Run
								</th>
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									Last Run
								</th>
								<th className="py-2.5 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wide">
									{ '' }
								</th>
							</tr>
						</thead>
						<tbody>
							{ tasks.map( ( task ) => (
								<ScheduleRow
									key={ task.id }
									task={ task }
									onAction={ handleAction }
									loading={ actionLoading }
								/>
							) ) }
						</tbody>
					</table>
				</div>
			) }
		</PageLayout>
	);
}
