import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Badge, Button, Skeleton } from '@bsf/force-ui';
import {
	RefreshCw,
	MessageSquare,
	ChevronLeft,
	ChevronRight,
	Hash,
	Cpu,
	Calendar,
	CircleDot,
	ArrowRight,
	Pencil,
	Check,
	X,
	Trash2,
	Search,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';
import PageHeader from '../components/ui/PageHeader';
import EmptyState from '../components/ui/EmptyState';
import { STORE_NAME } from '../store/constants';

function ConversationRow( { conversation, onResume, onRename, onDelete } ) {
	const [ editing, setEditing ] = useState( false );
	const [ editTitle, setEditTitle ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const inputRef = useRef( null );

	const date = new Date( conversation.created_at );
	const formatted = date.toLocaleDateString( undefined, {
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );

	const tokens = conversation.tokens_used || 0;

	const startEditing = ( e ) => {
		e.stopPropagation();
		setEditTitle( conversation.title || '' );
		setEditing( true );
		setTimeout( () => inputRef.current?.focus(), 0 );
	};

	const cancelEditing = ( e ) => {
		e.stopPropagation();
		setEditing( false );
	};

	const saveTitle = async ( e ) => {
		e.stopPropagation();
		const trimmed = editTitle.trim();
		if ( ! trimmed || trimmed === conversation.title ) {
			setEditing( false );
			return;
		}
		setSaving( true );
		try {
			await onRename( conversation.id, trimmed );
		} finally {
			setSaving( false );
			setEditing( false );
		}
	};

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' ) {
			saveTitle( e );
		} else if ( e.key === 'Escape' ) {
			cancelEditing( e );
		}
	};

	return (
		<tr
			className="group border-b border-solid border-border-subtle last:border-b-0 hover:bg-background-secondary/40 transition-colors duration-150 cursor-pointer"
			onClick={ () => ! editing && onResume( conversation.id ) }
		>
			<td className="py-3 px-4">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-8 rounded-lg bg-background-secondary shrink-0">
						<MessageSquare className="size-3.5 text-icon-secondary" />
					</div>
					{ editing ? (
						<div className="flex items-center gap-1.5 flex-1 min-w-0" onClick={ ( e ) => e.stopPropagation() }>
							<input
								ref={ inputRef }
								type="text"
								value={ editTitle }
								onChange={ ( e ) => setEditTitle( e.target.value ) }
								onKeyDown={ handleKeyDown }
								className="text-sm font-medium text-text-primary border border-solid border-border-subtle rounded-md px-2 py-1 flex-1 min-w-0 bg-background-primary"
								maxLength={ 255 }
								disabled={ saving }
							/>
							<button
								onClick={ saveTitle }
								disabled={ saving }
								className="p-1 rounded hover:bg-background-secondary text-support-success shrink-0"
							>
								<Check className="size-3.5" />
							</button>
							<button
								onClick={ cancelEditing }
								disabled={ saving }
								className="p-1 rounded hover:bg-background-secondary text-support-error shrink-0"
							>
								<X className="size-3.5" />
							</button>
						</div>
					) : (
						<div className="flex items-center gap-2 min-w-0 group/title">
							<span className="text-sm font-medium text-text-primary truncate max-w-xs">
								{ conversation.title || 'Untitled conversation' }
							</span>
							<button
								onClick={ startEditing }
								className="p-1 rounded hover:bg-background-secondary text-text-tertiary opacity-0 group-hover/title:opacity-100 transition-opacity shrink-0"
							>
								<Pencil className="size-3" />
							</button>
						</div>
					) }
				</div>
			</td>
			<td className="py-3 px-4">
				<Badge
					label={ conversation.status }
					variant={ conversation.status === 'active' ? 'green' : 'neutral' }
					size="xs"
					className="capitalize"
				/>
			</td>
			<td className="py-3 px-4">
				<span className="text-xs font-medium text-text-secondary bg-background-secondary px-2 py-0.5 rounded-md">
					{ conversation.model || '-' }
				</span>
			</td>
			<td className="py-3 px-4">
				<span className="text-xs font-medium text-text-secondary tabular-nums">
					{ tokens.toLocaleString() }
				</span>
			</td>
			<td className="py-3 px-4">
				<span className="text-xs text-text-tertiary">{ formatted }</span>
			</td>
			<td className="py-3 px-4">
				<div className="flex items-center gap-1">
					<button
						onClick={ ( e ) => {
							e.stopPropagation();
							onDelete( conversation.id );
						} }
						className="p-1.5 rounded-md hover:bg-background-secondary text-text-tertiary hover:text-support-error transition-colors opacity-0 group-hover:opacity-100 shrink-0"
						title="Delete conversation"
					>
						<Trash2 className="size-3.5" />
					</button>
					<ArrowRight className="size-4 text-text-tertiary" />
				</div>
			</td>
		</tr>
	);
}

function TableHeader( { icon: Icon, label } ) {
	return (
		<th className="py-3 px-4 text-xs font-medium text-text-tertiary uppercase tracking-wider">
			<div className="flex items-center gap-1.5">
				<Icon className="size-3 text-icon-secondary" />
				{ label }
			</div>
		</th>
	);
}

function TableSkeleton() {
	return (
		<div className="rounded-lg border border-solid border-border-subtle bg-background-primary overflow-hidden">
			<div className="p-4 space-y-4">
				{ [ 1, 2, 3, 4, 5 ].map( ( i ) => (
					<div key={ i } className="flex items-center gap-4">
						<Skeleton className="h-8 w-8 rounded-lg" />
						<Skeleton className="h-4 flex-1 rounded" />
						<Skeleton className="h-4 w-16 rounded" />
						<Skeleton className="h-4 w-20 rounded" />
						<Skeleton className="h-4 w-12 rounded" />
					</div>
				) ) }
			</div>
		</div>
	);
}

export default function History() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const { loadConversation } = useDispatch( STORE_NAME );

	const handleResume = useCallback( ( id ) => {
		loadConversation( id );
		document.dispatchEvent( new CustomEvent( 'jarvis-open-drawer' ) );
	}, [ loadConversation ] );

	const handleDelete = useCallback( async ( id ) => {
		if ( ! window.confirm( 'Delete this conversation?' ) ) {
			return;
		}
		try {
			await apiFetch( {
				path: `/jarvis-ai/v1/history/${ id }`,
				method: 'DELETE',
			} );
			setData( ( prev ) => {
				if ( ! prev ) {
					return prev;
				}
				return {
					...prev,
					conversations: prev.conversations.filter( ( c ) => c.id !== id ),
					total: prev.total - 1,
				};
			} );
		} catch {
			// Silently fail.
		}
	}, [] );

	const handleRename = useCallback( async ( id, title ) => {
		try {
			await apiFetch( {
				path: `/jarvis-ai/v1/history/${ id }/rename`,
				method: 'POST',
				data: { title },
			} );
			setData( ( prev ) => {
				if ( ! prev ) {
					return prev;
				}
				return {
					...prev,
					conversations: prev.conversations.map( ( c ) =>
						c.id === id ? { ...c, title } : c
					),
				};
			} );
		} catch {
			// Silently fail — title remains unchanged in UI.
		}
	}, [] );

	const fetchHistory = useCallback( async ( p ) => {
		try {
			setLoading( true );
			const result = await apiFetch( {
				path: `/jarvis-ai/v1/history?page=${ p }&per_page=20`,
			} );
			setData( result );
		} catch {
			setData( null );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchHistory( page );
	}, [ fetchHistory, page ] );

	const allConversations = data?.conversations || [];
	const lowerSearch = search.toLowerCase();
	const conversations = search
		? allConversations.filter( ( c ) =>
			( c.title || '' ).toLowerCase().includes( lowerSearch ) ||
			( c.model || '' ).toLowerCase().includes( lowerSearch )
		)
		: allConversations;
	const totalPages = data?.total_pages || 1;

	return (
		<PageLayout>
			<PageHeader
				title="History"
				description={
					data
						? `${ data.total } conversation${ data.total !== 1 ? 's' : '' } recorded`
						: 'Loading conversations...'
				}
				actions={
					<>
						<div className="relative">
							<Search size={ 14 } className="absolute left-3 top-1/2 -translate-y-1/2 text-icon-secondary pointer-events-none" />
							<input
								type="text"
								placeholder="Search conversations..."
								value={ search }
								onChange={ ( e ) => setSearch( e.target.value ) }
								className="pl-8 pr-3 py-1.5 text-sm border border-solid border-border-subtle rounded-lg bg-background-primary text-text-primary placeholder:text-text-tertiary outline-none focus:border-border-interactive focus:ring-1 focus:ring-border-interactive w-52"
							/>
						</div>
						<Button
							variant="outline"
							size="xs"
							icon={ <RefreshCw size={ 14 } className={ loading ? 'animate-spin' : '' } /> }
							onClick={ () => fetchHistory( page ) }
							disabled={ loading }
						>
							Refresh
						</Button>
					</>
				}
			/>

			{ loading ? (
				<TableSkeleton />
			) : conversations.length === 0 ? (
				<div className="rounded-lg border border-solid border-border-subtle bg-background-primary">
					<EmptyState
						icon={ MessageSquare }
						title="No conversations yet"
						description="Open the editor and start chatting with JARVIS. Your conversation history will appear here."
					/>
				</div>
			) : (
				<>
					<div className="rounded-lg border border-solid border-border-subtle bg-background-primary overflow-hidden">
						<div className="overflow-x-auto">
							<table className="w-full text-left border-collapse">
								<thead>
									<tr className="border-b border-solid border-border-subtle bg-background-secondary">
										<TableHeader icon={ MessageSquare } label="Conversation" />
										<TableHeader icon={ CircleDot } label="Status" />
										<TableHeader icon={ Cpu } label="Model" />
										<TableHeader icon={ Hash } label="Tokens" />
										<TableHeader icon={ Calendar } label="Date" />
										<th className="py-3 px-4 w-10"></th>
									</tr>
								</thead>
								<tbody>
									{ conversations.map( ( conv ) => (
										<ConversationRow key={ conv.id } conversation={ conv } onResume={ handleResume } onRename={ handleRename } onDelete={ handleDelete } />
									) ) }
								</tbody>
							</table>
						</div>
					</div>

					{ /* Pagination */ }
					{ totalPages > 1 && (
						<div className="flex items-center justify-between mt-5 px-1">
							<p className="text-xs text-text-tertiary">
								Page { page } of { totalPages }
							</p>
							<div className="flex items-center gap-1.5">
								<Button
									variant="outline"
									size="xs"
									icon={ <ChevronLeft size={ 14 } /> }
									onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
									disabled={ page <= 1 }
								>
									Previous
								</Button>
								<Button
									variant="outline"
									size="xs"
									icon={ <ChevronRight size={ 14 } /> }
									iconPosition="right"
									onClick={ () => setPage( ( p ) => Math.min( totalPages, p + 1 ) ) }
									disabled={ page >= totalPages }
								>
									Next
								</Button>
							</div>
						</div>
					) }
				</>
			) }
		</PageLayout>
	);
}
