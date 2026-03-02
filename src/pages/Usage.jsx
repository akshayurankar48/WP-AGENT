import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Container, Title, Badge } from '@bsf/force-ui';
import { BarChart3, RefreshCw, Loader2, Zap, DollarSign, Activity } from 'lucide-react';
import PageLayout from '../components/PageLayout';

function StatCard( { label, value, icon: Icon, color } ) {
	return (
		<div className="rounded-xl border border-border-subtle bg-background-primary p-5 shadow-sm">
			<div className="flex items-center gap-3 mb-3">
				<div className={ `flex items-center justify-center size-9 rounded-lg ${ color }` }>
					<Icon className="size-4" />
				</div>
				<p className="text-sm text-text-secondary">{ label }</p>
			</div>
			<p className="text-2xl font-semibold text-text-primary">
				{ value }
			</p>
		</div>
	);
}

function formatTokens( n ) {
	if ( n >= 1_000_000 ) {
		return ( n / 1_000_000 ).toFixed( 2 ) + 'M';
	}
	if ( n >= 1_000 ) {
		return ( n / 1_000 ).toFixed( 1 ) + 'K';
	}
	return n.toLocaleString();
}

function estimateCost( tokens ) {
	// Rough average: ~$3 per 1M tokens (blended input/output across providers).
	const costPerMillion = 3;
	return '$' + ( ( tokens / 1_000_000 ) * costPerMillion ).toFixed( 4 );
}

export default function Usage() {
	const [ stats, setStats ] = useState( null );
	const [ history, setHistory ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	const fetchData = useCallback( async () => {
		try {
			setLoading( true );
			const [ statsRes, historyRes ] = await Promise.all( [
				apiFetch( { path: '/wp-agent/v1/stats' } ),
				apiFetch( { path: '/wp-agent/v1/history?per_page=100' } ),
			] );
			setStats( statsRes );
			setHistory( historyRes );
		} catch {
			// Silently handle.
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchData();
	}, [ fetchData ] );

	const totalTokens = stats?.total_tokens ?? 0;
	const requestsToday = stats?.requests_today ?? 0;
	const conversations = history?.conversations ?? [];

	// Build per-model breakdown from history.
	const modelBreakdown = {};
	conversations.forEach( ( conv ) => {
		const model = conv.model || 'unknown';
		if ( ! modelBreakdown[ model ] ) {
			modelBreakdown[ model ] = { tokens: 0, count: 0 };
		}
		modelBreakdown[ model ].tokens += conv.tokens_used || 0;
		modelBreakdown[ model ].count += 1;
	} );

	const sortedModels = Object.entries( modelBreakdown )
		.sort( ( a, b ) => b[ 1 ].tokens - a[ 1 ].tokens );

	const maxTokens = sortedModels.length > 0 ? sortedModels[ 0 ][ 1 ].tokens : 1;

	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center justify-between mb-6">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-9 rounded-xl bg-amber-50">
						<BarChart3 className="size-4.5 text-amber-600" />
					</div>
					<div>
						<h1 className="text-xl font-bold text-text-primary">Usage</h1>
						<p className="text-xs text-text-tertiary mt-0.5">
							Token consumption and cost tracking
						</p>
					</div>
				</div>
				<button
					type="button"
					onClick={ fetchData }
					disabled={ loading }
					className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-solid border-border-subtle bg-background-primary text-xs font-medium text-text-secondary hover:bg-background-secondary transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-800"
				>
					<RefreshCw size={ 13 } className={ loading ? 'animate-spin' : '' } />
					Refresh
				</button>
			</div>

			{ loading ? (
				<div className="flex flex-col items-center justify-center py-20">
					<Loader2 size={ 28 } className="animate-spin text-brand-800 mb-3" />
					<p className="text-sm text-text-secondary">Loading usage data...</p>
				</div>
			) : (
				<>
					{ /* Stat cards */ }
					<div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
						<StatCard
							label="Total Tokens Used"
							value={ formatTokens( totalTokens ) }
							icon={ Zap }
							color="bg-violet-50 text-violet-600"
						/>
						<StatCard
							label="Estimated Cost"
							value={ estimateCost( totalTokens ) }
							icon={ DollarSign }
							color="bg-emerald-50 text-emerald-600"
						/>
						<StatCard
							label="Requests Today"
							value={ requestsToday.toLocaleString() }
							icon={ Activity }
							color="bg-blue-50 text-blue-600"
						/>
					</div>

					{ /* Model breakdown */ }
					{ sortedModels.length > 0 ? (
						<div className="rounded-xl border border-border-subtle bg-background-primary p-6 shadow-sm">
							<h2 className="text-sm font-semibold text-text-primary mb-4">
								Usage by Model
							</h2>
							<div className="flex flex-col gap-3">
								{ sortedModels.map( ( [ model, data ] ) => {
									const pct = Math.max( 4, ( data.tokens / maxTokens ) * 100 );
									return (
										<div key={ model }>
											<div className="flex items-center justify-between mb-1.5">
												<span className="text-xs font-medium text-text-primary">{ model }</span>
												<span className="text-xs text-text-tertiary">
													{ formatTokens( data.tokens ) } tokens &middot; { data.count } conversation{ data.count !== 1 ? 's' : '' }
												</span>
											</div>
											<div className="w-full h-2 bg-background-secondary rounded-full overflow-hidden">
												<div
													className="h-full bg-brand-800 rounded-full transition-all duration-500"
													style={ { width: `${ pct }%` } }
												/>
											</div>
										</div>
									);
								} ) }
							</div>
						</div>
					) : (
						<div className="flex flex-col items-center justify-center rounded-xl border border-border-subtle bg-background-primary p-12 shadow-sm text-center">
							<div className="flex items-center justify-center w-16 h-16 rounded-full bg-background-secondary mb-4">
								<BarChart3 size={ 28 } className="text-icon-secondary" />
							</div>
							<h2 className="text-lg font-semibold text-text-primary mb-2">
								No usage data yet
							</h2>
							<p className="text-sm text-text-secondary max-w-md">
								Start chatting with JARVIS and your token usage will appear here.
							</p>
						</div>
					) }
				</>
			) }
		</PageLayout>
	);
}
