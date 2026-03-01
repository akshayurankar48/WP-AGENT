/**
 * WP Agent — Scroll Animation Observer.
 *
 * Watches elements with wpa- animation classes and adds .wpa-visible
 * when they enter the viewport. Fires once per element.
 *
 * @package
 * @since   1.0.0
 */
( function () {
	if ( typeof IntersectionObserver === 'undefined' ) {
		return;
	}

	const selector = '.wpa-fade-up,.wpa-fade-down,.wpa-slide-left,.wpa-slide-right,.wpa-zoom-in,.wpa-stagger-children';

	var observer = new IntersectionObserver(
		function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'wpa-visible' );
					observer.unobserve( entry.target );
				}
			} );
		},
		{ threshold: 0.15 }
	);

	function observe() {
		document.querySelectorAll( selector ).forEach( function ( el ) {
			if ( ! el.classList.contains( 'wpa-visible' ) ) {
				observer.observe( el );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', observe );
	} else {
		observe();
	}
}() );
