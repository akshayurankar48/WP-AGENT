import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import History from './pages/History';
import Usage from './pages/Usage';
import './style.css';

const pages = [
	{ id: 'wp-agent-dashboard', Component: Dashboard },
	{ id: 'wp-agent-settings', Component: Settings },
	{ id: 'wp-agent-history', Component: History },
	{ id: 'wp-agent-usage', Component: Usage },
];

domReady( () => {
	for ( const { id, Component } of pages ) {
		const container = document.getElementById( id );
		if ( container ) {
			createRoot( container ).render( <Component /> );
			break;
		}
	}
} );
