/**
 * Root wrapper for the admin chat drawer.
 *
 * Manages open/close state, keyboard shortcuts, and renders the FAB + panel.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback, useEffect } from '@wordpress/element';
import DrawerButton from './DrawerButton';
import DrawerPanel from './DrawerPanel';
import useKeyboardShortcut from '../hooks/use-keyboard-shortcut';

export default function AdminChatDrawer() {
	const [ isOpen, setIsOpen ] = useState( false );

	const toggle = useCallback( () => setIsOpen( ( prev ) => ! prev ), [] );
	const close = useCallback( () => setIsOpen( false ), [] );
	const open = useCallback( () => setIsOpen( true ), [] );

	// Cmd+J / Ctrl+J toggle, Escape close.
	useKeyboardShortcut( { onToggle: toggle, onClose: close, isOpen } );

	// Listen for custom event from History page to open drawer.
	useEffect( () => {
		function handleOpen() {
			open();
		}
		document.addEventListener( 'jarvis-open-drawer', handleOpen );
		return () => document.removeEventListener( 'jarvis-open-drawer', handleOpen );
	}, [ open ] );

	return (
		<>
			{ ! isOpen && <DrawerButton onClick={ toggle } /> }
			{ isOpen && <DrawerPanel onClose={ close } /> }
		</>
	);
}
