/**
 * Admin drawer entry point.
 *
 * Renders the floating JARVIS chat button + drawer on non-editor admin pages.
 *
 * @package
 * @since 1.0.0
 */

import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import AdminChatDrawer from './drawer/AdminChatDrawer';
import './store';

domReady( () => {
	const root = document.createElement( 'div' );
	root.id = 'wp-agent-drawer-root';
	document.body.appendChild( root );
	createRoot( root ).render( <AdminChatDrawer /> );
} );
