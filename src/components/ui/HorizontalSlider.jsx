import { useRef, useState, useEffect, useCallback } from '@wordpress/element';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '../../utils/cn';

export default function HorizontalSlider( { children, className } ) {
	const scrollRef = useRef( null );
	const [ canScrollLeft, setCanScrollLeft ] = useState( false );
	const [ canScrollRight, setCanScrollRight ] = useState( false );

	const checkScroll = useCallback( () => {
		const el = scrollRef.current;
		if ( ! el ) {
			return;
		}
		setCanScrollLeft( el.scrollLeft > 1 );
		setCanScrollRight( el.scrollLeft + el.clientWidth < el.scrollWidth - 1 );
	}, [] );

	useEffect( () => {
		checkScroll();
		const el = scrollRef.current;
		if ( ! el ) {
			return;
		}
		el.addEventListener( 'scroll', checkScroll, { passive: true } );
		const ro = new ResizeObserver( checkScroll );
		ro.observe( el );
		return () => {
			el.removeEventListener( 'scroll', checkScroll );
			ro.disconnect();
		};
	}, [ checkScroll ] );

	const scroll = ( direction ) => {
		const el = scrollRef.current;
		if ( ! el ) {
			return;
		}
		el.scrollBy( {
			left: direction * el.clientWidth * 0.8,
			behavior: 'smooth',
		} );
	};

	return (
		<div className={ cn( 'group/slider relative', className ) }>
			{ /* Left arrow */ }
			{ canScrollLeft && (
				<button
					type="button"
					onClick={ () => scroll( -1 ) }
					className="absolute left-0 top-1/2 -translate-y-1/2 z-10 flex items-center justify-center size-8 rounded-full bg-background-primary border border-solid border-border-subtle shadow-md opacity-0 group-hover/slider:opacity-100 transition-opacity duration-200 cursor-pointer -ml-3 hover:bg-background-secondary"
					aria-label="Scroll left"
				>
					<ChevronLeft className="size-4 text-text-secondary" />
				</button>
			) }

			{ /* Scroll container */ }
			<div
				ref={ scrollRef }
				className="flex gap-4 overflow-x-auto scrollbar-none scroll-snap-x"
			>
				{ children }
			</div>

			{ /* Right arrow */ }
			{ canScrollRight && (
				<button
					type="button"
					onClick={ () => scroll( 1 ) }
					className="absolute right-0 top-1/2 -translate-y-1/2 z-10 flex items-center justify-center size-8 rounded-full bg-background-primary border border-solid border-border-subtle shadow-md opacity-0 group-hover/slider:opacity-100 transition-opacity duration-200 cursor-pointer -mr-3 hover:bg-background-secondary"
					aria-label="Scroll right"
				>
					<ChevronRight className="size-4 text-text-secondary" />
				</button>
			) }
		</div>
	);
}
