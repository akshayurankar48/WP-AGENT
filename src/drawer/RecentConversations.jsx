/**
 * Recent conversations dropdown for the admin drawer header.
 *
 * Shows last 5 conversations from the history API.
 * All Emotion CSS (no Tailwind — body portal).
 *
 * @package
 * @since 1.1.0
 */

import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { css, keyframes } from '@emotion/css';
import { Clock, Loader2, MessageSquare, ExternalLink } from 'lucide-react';
import { STORE_NAME } from '../store/constants';
import {
	colors,
	radii,
	fontSizes,
	focusRing,
} from '../editor/styles';

const dropIn = keyframes`
	from { opacity: 0; transform: translateY(-8px); }
	to   { opacity: 1; transform: translateY(0); }
`;

const btnStyle = css`
	${ focusRing };
	display: flex;
	align-items: center;
	gap: 5px;
	padding: 5px 8px;
	border: none;
	border-radius: ${ radii.sm };
	background: rgba(255, 255, 255, 0.15);
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: rgba(255, 255, 255, 0.9);
	cursor: pointer;
	transition: background 0.15s ease;
	position: relative;

	&:hover {
		background: rgba(255, 255, 255, 0.25);
	}
`;

const dropdownStyle = css`
	position: absolute;
	top: calc(100% + 8px);
	right: 0;
	width: 280px;
	background: #ffffff;
	border: 1px solid ${ colors.border };
	border-radius: ${ radii.lg };
	box-shadow: 0 8px 30px rgba(0, 0, 0, 0.16);
	z-index: 100001;
	animation: ${ dropIn } 0.15s ease-out;
	overflow: hidden;
`;

const dropdownHeader = css`
	padding: 10px 14px;
	border-bottom: 1px solid ${ colors.border };
	font-size: 11px;
	font-weight: 600;
	color: ${ colors.textSecondary };
	text-transform: uppercase;
	letter-spacing: 0.05em;
`;

const itemStyle = css`
	display: flex;
	align-items: center;
	gap: 10px;
	width: 100%;
	padding: 10px 14px;
	border: none;
	background: none;
	cursor: pointer;
	text-align: left;
	transition: background 0.1s ease;

	&:hover {
		background: ${ colors.primaryLight };
	}

	& + & {
		border-top: 1px solid ${ colors.border };
	}
`;

const itemIcon = css`
	flex-shrink: 0;
	width: 28px;
	height: 28px;
	border-radius: 6px;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #f0ecff;
	color: ${ colors.primary };
`;

const itemTitle = css`
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: ${ colors.text };
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 160px;
`;

const itemTime = css`
	font-size: 10px;
	color: ${ colors.textMuted };
	margin-top: 2px;
`;

const viewAllStyle = css`
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: 100%;
	padding: 10px 14px;
	border: none;
	border-top: 1px solid ${ colors.border };
	background: ${ colors.bgSubtle };
	font-size: ${ fontSizes.xs };
	font-weight: 500;
	color: ${ colors.primary };
	cursor: pointer;
	transition: background 0.1s ease;

	&:hover {
		background: ${ colors.primaryLight };
	}
`;

const loadingWrap = css`
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 20px;
	color: ${ colors.textMuted };
`;

const emptyWrap = css`
	padding: 20px;
	text-align: center;
	font-size: ${ fontSizes.xs };
	color: ${ colors.textMuted };
`;

function relativeTime( dateStr ) {
	const diff = Date.now() - new Date( dateStr ).getTime();
	const mins = Math.floor( diff / 60000 );
	if ( mins < 1 ) {
		return 'Just now';
	}
	if ( mins < 60 ) {
		return `${ mins }m ago`;
	}
	const hours = Math.floor( mins / 60 );
	if ( hours < 24 ) {
		return `${ hours }h ago`;
	}
	const days = Math.floor( hours / 24 );
	if ( days < 7 ) {
		return `${ days }d ago`;
	}
	return new Date( dateStr ).toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
}

export default function RecentConversations() {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ items, setItems ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const ref = useRef( null );
	const { loadConversation } = useDispatch( STORE_NAME );

	const toggle = useCallback( () => {
		setIsOpen( ( prev ) => ! prev );
	}, [] );

	// Fetch when dropdown opens.
	useEffect( () => {
		if ( ! isOpen ) {
			return;
		}

		const { restUrl, nonce } = window.wpAgentData || {};
		if ( ! restUrl || ! nonce ) {
			return;
		}

		setLoading( true );
		fetch( `${ restUrl }history?per_page=5`, {
			headers: { 'X-WP-Nonce': nonce },
		} )
			.then( ( r ) => r.json() )
			.then( ( data ) => {
				setItems( data.conversations || [] );
			} )
			.catch( () => setItems( [] ) )
			.finally( () => setLoading( false ) );
	}, [ isOpen ] );

	// Click outside to close.
	useEffect( () => {
		if ( ! isOpen ) {
			return;
		}

		function handleClick( e ) {
			if ( ref.current && ! ref.current.contains( e.target ) ) {
				setIsOpen( false );
			}
		}
		function handleKey( e ) {
			if ( e.key === 'Escape' ) {
				e.stopPropagation();
				setIsOpen( false );
			}
		}

		document.addEventListener( 'mousedown', handleClick );
		document.addEventListener( 'keydown', handleKey, true );
		return () => {
			document.removeEventListener( 'mousedown', handleClick );
			document.removeEventListener( 'keydown', handleKey, true );
		};
	}, [ isOpen ] );

	const handleSelect = ( id ) => {
		loadConversation( id );
		setIsOpen( false );
	};

	return (
		<div ref={ ref } style={ { position: 'relative' } }>
			<button
				type="button"
				className={ btnStyle }
				onClick={ toggle }
				aria-label="Recent conversations"
				aria-expanded={ isOpen }
			>
				<Clock size={ 12 } />
			</button>

			{ isOpen && (
				<div className={ dropdownStyle }>
					<div className={ dropdownHeader }>Recent conversations</div>

					{ loading ? (
						<div className={ loadingWrap }>
							<Loader2 size={ 16 } className="animate-spin" style={ { animation: 'spin 1s linear infinite' } } />
						</div>
					) : ! items || items.length === 0 ? (
						<div className={ emptyWrap }>No conversations yet</div>
					) : (
						items.map( ( conv ) => (
							<button
								key={ conv.id }
								type="button"
								className={ itemStyle }
								onClick={ () => handleSelect( conv.id ) }
							>
								<div className={ itemIcon }>
									<MessageSquare size={ 12 } />
								</div>
								<div style={ { flex: 1, minWidth: 0 } }>
									<div className={ itemTitle }>
										{ conv.title || 'Untitled conversation' }
									</div>
									<div className={ itemTime }>
										{ relativeTime( conv.updated_at || conv.created_at ) }
									</div>
								</div>
							</button>
						) )
					) }

					<button
						type="button"
						className={ viewAllStyle }
						onClick={ () => {
							setIsOpen( false );
							window.location.href = 'admin.php?page=wp-agent-history';
						} }
					>
						View all
						<ExternalLink size={ 11 } />
					</button>
				</div>
			) }
		</div>
	);
}
