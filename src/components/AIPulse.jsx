/**
 * AI Pulse — AI News & Learning Hub.
 *
 * Fetches curated AI news from RSS feeds via the REST API
 * and displays them in a horizontal slider with source filters.
 *
 * @package
 * @since 1.2.0
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Badge, Skeleton } from '@bsf/force-ui';
import {
	Rss,
	ExternalLink,
	RefreshCw,
	Newspaper,
	BookOpen,
	Sparkles,
	Play,
} from 'lucide-react';
import CardShell from './ui/CardShell';
import SectionHeader from './ui/SectionHeader';
import HorizontalSlider from './ui/HorizontalSlider';
import EmptyState from './ui/EmptyState';

/* ── Source labels ─────────────────────────────────────────────── */

const SOURCE_LABELS = {
	openai: 'OpenAI',
	anthropic: 'Anthropic',
	verge: 'The Verge',
	techcrunch: 'TechCrunch',
	wordpress: 'WordPress',
	youtube: 'YouTube',
};

const TABS = [
	{ key: 'all', label: 'All', icon: Sparkles },
	{ key: 'blog', label: 'Blogs', icon: BookOpen },
	{ key: 'video', label: 'Videos', icon: Play },
	{ key: 'news', label: 'News', icon: Newspaper },
];

/* ── Relative time helper ──────────────────────────────────────── */

function timeAgo( dateStr ) {
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

/* ── Feed Item Card ────────────────────────────────────────────── */

function FeedCard( { item } ) {
	const sourceLabel = SOURCE_LABELS[ item.icon ] || item.icon;
	const isVideo = item.type === 'video';

	return (
		<a
			href={ item.link }
			target="_blank"
			rel="noopener noreferrer"
			className="group flex flex-col gap-2 p-4 rounded-lg border border-solid border-border-subtle bg-background-primary hover:shadow-sm hover:border-border-interactive transition-all duration-200 no-underline w-[280px] shrink-0 snap-start"
		>
			{ /* Video thumbnail */ }
			{ isVideo && item.thumbnail && (
				<div className="relative rounded-md overflow-hidden -mx-1 -mt-1 mb-0.5">
					<img
						src={ item.thumbnail }
						alt=""
						className="w-full h-20 object-cover"
						loading="lazy"
					/>
					<div className="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition-colors duration-200">
						<div className="flex items-center justify-center size-7 rounded-full bg-red-600 shadow-md">
							<Play className="size-3 text-white fill-white ml-px" />
						</div>
					</div>
				</div>
			) }
			<div className="flex items-center justify-between gap-2">
				<span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-background-secondary text-text-secondary">
					{ sourceLabel }
				</span>
				<span className="text-[10px] text-text-tertiary shrink-0">
					{ timeAgo( item.published ) }
				</span>
			</div>
			<h3 className="text-sm font-medium text-text-primary leading-snug line-clamp-2 group-hover:text-text-primary transition-colors duration-150">
				{ item.title }
			</h3>
			{ item.summary && ! isVideo && (
				<p className="text-xs text-text-tertiary leading-relaxed line-clamp-2">
					{ item.summary }
				</p>
			) }
			<div className="flex items-center gap-1 text-[10px] text-text-tertiary opacity-0 group-hover:opacity-100 transition-opacity duration-200 mt-auto">
				<ExternalLink className="size-3" />
				<span>{ isVideo ? 'Watch video' : 'Read article' }</span>
			</div>
		</a>
	);
}

/* ── Main Component ────────────────────────────────────────────── */

export default function AIPulse() {
	const [ feed, setFeed ] = useState( null );
	const [ activeTab, setActiveTab ] = useState( 'all' );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isRefreshing, setIsRefreshing ] = useState( false );

	const fetchFeed = useCallback( ( refresh = false ) => {
		if ( refresh ) {
			setIsRefreshing( true );
		}
		apiFetch( { path: `/jarvis-ai/v1/ai-pulse${ refresh ? '?refresh=true' : '' }` } )
			.then( ( data ) => {
				setFeed( data );
				setIsLoading( false );
				setIsRefreshing( false );
			} )
			.catch( () => {
				setIsLoading( false );
				setIsRefreshing( false );
			} );
	}, [] );

	useEffect( () => {
		fetchFeed();
	}, [ fetchFeed ] );

	const items = feed?.items || [];
	const filtered = activeTab === 'all'
		? items
		: items.filter( ( item ) => item.type === activeTab );

	return (
		<CardShell className="p-5" hover={ false }>
			<SectionHeader
				icon={ Rss }
				title="AI Pulse"
				badge="Live"
				actions={
					<button
						type="button"
						onClick={ () => fetchFeed( true ) }
						disabled={ isRefreshing }
						className="flex items-center gap-1 text-xs font-medium text-text-tertiary hover:text-text-primary transition-colors duration-200 bg-transparent border-none cursor-pointer disabled:opacity-40"
					>
						<RefreshCw className={ `size-3 ${ isRefreshing ? 'animate-spin' : '' }` } />
						Refresh
					</button>
				}
			/>

			{ /* Tab filters */ }
			<div className="flex items-center gap-1 mb-4 p-0.5 bg-background-secondary rounded-lg w-fit">
				{ TABS.map( ( tab ) => (
					<button
						key={ tab.key }
						type="button"
						onClick={ () => setActiveTab( tab.key ) }
						className={ `flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition-all duration-150 border-none cursor-pointer ${
							activeTab === tab.key
								? 'bg-background-primary text-text-primary shadow-sm'
								: 'bg-transparent text-text-tertiary hover:text-text-secondary'
						}` }
					>
						<tab.icon className="size-3" />
						{ tab.label }
					</button>
				) ) }
			</div>

			{ /* Content */ }
			{ isLoading ? (
				<div className="flex gap-4">
					{ [ 1, 2, 3, 4 ].map( ( i ) => (
						<div key={ i } className="w-[280px] shrink-0">
							<Skeleton className="h-32 rounded-lg" />
						</div>
					) ) }
				</div>
			) : filtered.length > 0 ? (
				<HorizontalSlider>
					{ filtered.map( ( item, i ) => (
						<FeedCard key={ i } item={ item } />
					) ) }
				</HorizontalSlider>
			) : (
				<EmptyState
					icon={ Rss }
					title="No articles found"
					description="Try refreshing or check back later"
					className="py-8"
				/>
			) }
		</CardShell>
	);
}
