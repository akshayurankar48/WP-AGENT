import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Badge, Button, Skeleton, toast } from '@bsf/force-ui';
import {
	CalendarClock,
	Play,
	Pause,
	Trash2,
	RefreshCw,
	Timer,
	CircleDot,
	Clock,
	Zap,
	AlarmClock,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';
import PageHeader from '../components/ui/PageHeader';
import EmptyState from '../components/ui/EmptyState';

const SCHEDULE_LABELS = {
	hourly: 'Every hour',
	twicedaily: 'Twice daily',
	daily: 'Daily',
	weekly: 'Weekly',
};

function ScheduleRow( { task, onAction, loading } ) {
	const isActive = task.status === 'active';

	return (
		<tr className="border-b border-solid border-border-subtle last:border-b-0 hover:bg-background-secondary/40 transition-colors duration-150">
			<td className="py-3 px-4">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-8 rounded-lg bg-background-secondary shrink-0">
						<Zap className="size-3.5 text-icon-secondary" />
					</div>
					<span className="text-sm font-medium text-text-primary">
						{ task.name }
					</span>
				</div>
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
				<span className="text-xs font-medium text-text-secondary">
					{ SCHEDULE_LABELS[ task.schedule ] || task.schedule }
				</span>
			</td>
			<td className="py-3 px-4">
				<Badge
					label={ task.status }
					variant={ isActive ? 'green' : 'yellow' }
					size="xs"
					className="capitalize"
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

function TableHeader( { icon: Icon, label } ) {
	return (
		<th className="py-3 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wider">
			<div className="flex items-center gap-1.5">
				{ Icon && <Icon className="size-3 text-icon-secondary" /> }
				{ label }
			</div>
		</th>
	);
}

function TableSkeleton() {
	return (
		<div className="rounded-lg border border-solid border-border-subtle bg-background-primary overflow-hidden">
			<div className="p-4 space-y-4">
				{ [ 1, 2, 3 ].map( ( i ) => (
					<div key={ i } className="flex items-center gap-4">
						<Skeleton className="h-8 w-8 rounded-lg" />
						<Skeleton className="h-4 flex-1 rounded" />
						<Skeleton className="h-4 w-20 rounded" />
						<Skeleton className="h-4 w-16 rounded" />
						<Skeleton className="h-4 w-24 rounded" />
					</div>
				) ) }
			</div>
		</div>
	);
}

export default function Schedules() {
	const [ tasks, setTasks ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ actionLoading, setActionLoading ] = useState( false );

	const fetchTasks = useCallback( async () => {
		try {
			setLoading( true );
			const data = await apiFetch( { path: '/jarvis-ai/v1/schedules' } );
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
				path: `/jarvis-ai/v1/schedules/${ taskId }/${ action }`,
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
			<PageHeader
				title="Schedules"
				description={
					tasks.length > 0
						? `${ activeCount } active of ${ tasks.length } scheduled task${ tasks.length !== 1 ? 's' : '' }`
						: 'Manage your automated tasks'
				}
				actions={
					<Button
						variant="outline"
						size="xs"
						icon={ <RefreshCw size={ 14 } className={ loading ? 'animate-spin' : '' } /> }
						onClick={ fetchTasks }
						disabled={ loading }
					>
						Refresh
					</Button>
				}
			/>

			{ loading ? (
				<TableSkeleton />
			) : tasks.length === 0 ? (
				<div className="rounded-lg border border-solid border-border-subtle bg-background-primary">
					<EmptyState
						icon={ CalendarClock }
						title="No scheduled tasks yet"
						description='Ask JARVIS to create a scheduled task, like "Post a weekly SEO summary every Monday" or "Run site health checks daily".'
					/>
				</div>
			) : (
				<div className="rounded-lg border border-solid border-border-subtle bg-background-primary overflow-hidden">
					<div className="overflow-x-auto">
						<table className="w-full text-left border-collapse">
							<thead>
								<tr className="border-b border-solid border-border-subtle bg-background-secondary">
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
				</div>
			) }
		</PageLayout>
	);
}
