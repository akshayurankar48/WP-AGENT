import { useState, useEffect, useRef } from '@wordpress/element';
import { Badge } from '@bsf/force-ui';
import { ArrowRight } from 'lucide-react';

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

export default function StatCard( { icon: Icon, label, value, variant = 'neutral', href, accent = 'indigo' } ) {
	const ACCENT = {
		indigo: { bg: 'bg-indigo-50', text: 'text-indigo-600' },
		blue: { bg: 'bg-blue-50', text: 'text-blue-600' },
		amber: { bg: 'bg-amber-50', text: 'text-amber-600' },
		emerald: { bg: 'bg-emerald-50', text: 'text-emerald-600' },
		violet: { bg: 'bg-violet-50', text: 'text-violet-600' },
		cyan: { bg: 'bg-cyan-50', text: 'text-cyan-600' },
	};
	const color = ACCENT[ accent ] || ACCENT.indigo;
	const numericValue = typeof value === 'number' ? value : parseInt( value, 10 );
	const animatedValue = useCountUp(
		! isNaN( numericValue ) ? numericValue : null
	);
	const displayValue = ! isNaN( numericValue ) ? String( animatedValue ) : value;

	const content = (
		<div className="rounded-lg border border-solid border-border-subtle bg-background-primary p-4 hover:shadow-sm transition-shadow duration-200">
			<div className="flex items-center gap-3">
				<div className={ `flex items-center justify-center size-9 rounded-lg ${ color.bg } shrink-0` }>
					<Icon className={ `size-4 ${ color.text }` } />
				</div>
				<div className="min-w-0">
					<p className="text-xs font-medium text-text-tertiary uppercase tracking-wide">{ label }</p>
					<div className="flex items-center gap-2 mt-0.5">
						<span className="text-lg font-semibold text-text-primary tabular-nums">
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
				<div className="flex items-center gap-1 mt-2.5 text-xs text-text-tertiary">
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
