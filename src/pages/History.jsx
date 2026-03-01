import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Badge, Button, Tooltip } from '@bsf/force-ui';
import {
	Clock,
	RefreshCw,
	Loader2,
	MessageSquare,
	ChevronLeft,
	ChevronRight,
	Hash,
	Cpu,
	Calendar,
	CircleDot,
	Sparkles,
} from 'lucide-react';
import PageLayout from '../components/PageLayout';

const TOKEN_TIERS = [
	{ max: 500, label: 'Light', bg: 'bg-emerald-50', text: 'text-emerald-600' },
	{ max: 2000, label: 'Normal', bg: 'bg-blue-50', text: 'text-blue-600' },
	{ max: 5000, label: 'Heavy', bg: 'bg-amber-50', text: 'text-amber-600' },
	{ max: Infinity, label: 'Ultra', bg: 'bg-rose-50', text: 'text-rose-600' },
];

function getTokenTier( tokens ) {
	return TOKEN_TIERS.find( ( t ) => tokens <= t.max ) || TOKEN_TIERS[ TOKEN_TIERS.length - 1 ];
}

function ConversationRow( { conversation, index } ) {
	const date = new Date( conversation.created_at );
	const formatted = date.toLocaleDateString( undefined, {
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );

	const tokens = conversation.tokens_used || 0;
	const tier = getTokenTier( tokens );
	const isEven = index % 2 === 0;

	return (
		<tr className={ `border-b border-solid border-border-subtle last:border-b-0 hover:bg-blue-50/50 transition-colors duration-150 ${ isEven ? '' : 'bg-background-secondary/40' }` }>
			<td className="py-3.5 px-5">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-8 rounded-lg bg-violet-50 shrink-0">
						<MessageSquare className="size-3.5 text-violet-600" />
					</div>
					<span className="text-sm font-medium text-text-primary truncate max-w-xs">
						{ conversation.title || 'Untitled conversation' }
					</span>
				</div>
			</td>
			<td className="py-3.5 px-5">
				<Badge
					label={ conversation.status }
					variant={ conversation.status === 'active' ? 'green' : 'neutral' }
					size="xs"
					className="capitalize"
				/>
			</td>
			<td className="py-3.5 px-5">
				<span className="text-xs font-medium text-text-secondary bg-background-secondary px-2 py-0.5 rounded-md">
					{ conversation.model || '-' }
				</span>
			</td>
			<td className="py-3.5 px-5">
				<span className={ `inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-md ${ tier.bg } ${ tier.text }` }>
					{ tokens.toLocaleString() }
				</span>
			</td>
			<td className="py-3.5 px-5">
				<span className="text-xs text-text-tertiary">{ formatted }</span>
			</td>
		</tr>
	);
}

function TableHeader( { icon: Icon, label } ) {
	return (
		<th className="py-3 px-5 text-xs font-semibold text-text-secondary uppercase tracking-wider">
			<div className="flex items-center gap-1.5">
				<Icon className="size-3 text-icon-secondary" />
				{ label }
			</div>
		</th>
	);
}

export default function History() {
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ page, setPage ] = useState( 1 );

	const fetchHistory = useCallback( async ( p ) => {
		try {
			setLoading( true );
			const result = await apiFetch( {
				path: `/wp-agent/v1/history?page=${ p }&per_page=20`,
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

	const conversations = data?.conversations || [];
	const totalPages = data?.total_pages || 1;

	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center justify-between mb-6">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-9 rounded-xl bg-blue-50">
						<Clock className="size-4.5 text-blue-600" />
					</div>
					<div>
						<h1 className="text-xl font-bold text-text-primary">
							History
						</h1>
						<p className="text-xs text-text-tertiary mt-0.5">
							{ data
								? `${ data.total } conversation${ data.total !== 1 ? 's' : '' } recorded`
								: 'Loading conversations...'
							}
						</p>
					</div>
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
			</div>

			{ loading ? (
				<div className="flex flex-col items-center justify-center py-20">
					<Loader2 size={ 28 } className="animate-spin text-brand-800 mb-3" />
					<p className="text-sm text-text-secondary">Loading conversations...</p>
				</div>
			) : conversations.length === 0 ? (
				<div className="relative overflow-hidden flex flex-col items-center justify-center rounded-2xl border border-solid border-border-subtle bg-background-primary p-16 shadow-sm text-center">
					<div className="absolute top-0 right-0 w-48 h-48 bg-blue-100 rounded-full -translate-y-1/2 translate-x-1/3 opacity-40 blur-3xl pointer-events-none" />
					<div className="relative">
						<div className="flex items-center justify-center size-16 rounded-2xl bg-gradient-to-br from-blue-50 to-violet-50 mb-5 mx-auto">
							<MessageSquare size={ 28 } className="text-blue-600" />
						</div>
						<h2 className="text-lg font-bold text-text-primary mb-2">
							No conversations yet
						</h2>
						<p className="text-sm text-text-secondary max-w-sm leading-relaxed">
							Open the editor and start chatting with JARVIS.
							Your conversation history will appear here.
						</p>
					</div>
				</div>
			) : (
				<>
					<div className="rounded-2xl border border-solid border-border-subtle bg-background-primary shadow-sm overflow-hidden">
						<div className="overflow-x-auto">
							<table className="w-full text-left border-collapse">
								<thead>
									<tr className="border-b border-solid border-border-subtle bg-gradient-to-r from-background-secondary to-background-primary">
										<TableHeader icon={ MessageSquare } label="Conversation" />
										<TableHeader icon={ CircleDot } label="Status" />
										<TableHeader icon={ Cpu } label="Model" />
										<TableHeader icon={ Hash } label="Tokens" />
										<TableHeader icon={ Calendar } label="Date" />
									</tr>
								</thead>
								<tbody>
									{ conversations.map( ( conv, i ) => (
										<ConversationRow key={ conv.id } conversation={ conv } index={ i } />
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
