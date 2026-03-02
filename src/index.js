import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import History from './pages/History';
import Schedules from './pages/Schedules';
import Capabilities from './pages/Capabilities';
import Help from './pages/Help';
import AppChatDrawer from './components/AppChatDrawer';
import './store';
import './style.css';

const pages = [
	{ id: 'wp-agent-dashboard', Component: Dashboard },
	{ id: 'wp-agent-settings', Component: Settings },
	{ id: 'wp-agent-history', Component: History },
	{ id: 'wp-agent-schedules', Component: Schedules },
	{ id: 'wp-agent-capabilities', Component: Capabilities },
	{ id: 'wp-agent-help', Component: Help },
];

domReady( () => {
	for ( const { id, Component } of pages ) {
		const container = document.getElementById( id );
		if ( container ) {
			createRoot( container ).render( <Component /> );
			break;
		}
	}

	// Mount the JARVIS chat drawer on all WP Agent admin pages.
	const drawerRoot = document.createElement( 'div' );
	drawerRoot.id = 'wp-agent-app-drawer';
	document.body.appendChild( drawerRoot );
	createRoot( drawerRoot ).render( <AppChatDrawer /> );
} );
