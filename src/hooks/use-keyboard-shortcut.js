/**
 * Keyboard shortcut hook for the admin drawer.
 *
 * Handles Cmd+J / Ctrl+J to toggle and Escape to close.
 *
 * @package
 * @since 1.1.0
 */

import { useEffect } from '@wordpress/element';

export default function useKeyboardShortcut( { onToggle, onClose, isOpen } ) {
	useEffect( () => {
		function handler( e ) {
			// Skip if modifier combos other than Ctrl/Cmd.
			if ( e.shiftKey || e.altKey ) {
				return;
			}

			// Cmd+J / Ctrl+J — toggle drawer.
			if ( ( e.metaKey || e.ctrlKey ) && e.key === 'j' ) {
				e.preventDefault();
				e.stopPropagation();
				onToggle();
				return;
			}

			// Escape — close drawer when open.
			if ( e.key === 'Escape' && isOpen ) {
				e.preventDefault();
				e.stopPropagation();
				onClose();
			}
		}

		document.addEventListener( 'keydown', handler, true );
		return () => document.removeEventListener( 'keydown', handler, true );
	}, [ onToggle, onClose, isOpen ] );
}
