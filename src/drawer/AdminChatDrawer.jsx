/**
 * Root wrapper for the admin chat drawer.
 *
 * Manages open/close state and renders the FAB + panel.
 *
 * @package
 * @since 1.0.0
 */

import { useState, useCallback } from '@wordpress/element';
import DrawerButton from './DrawerButton';
import DrawerPanel from './DrawerPanel';

export default function AdminChatDrawer() {
	const [ isOpen, setIsOpen ] = useState( false );

	const toggle = useCallback( () => setIsOpen( ( prev ) => ! prev ), [] );
	const close = useCallback( () => setIsOpen( false ), [] );

	return (
		<>
			{ ! isOpen && <DrawerButton onClick={ toggle } /> }
			{ isOpen && <DrawerPanel onClose={ close } /> }
		</>
	);
}
