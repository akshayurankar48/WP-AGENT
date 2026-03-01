import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import Dashboard from './pages/Dashboard';
import Settings from './pages/Settings';
import Capabilities from './pages/Capabilities';
import Help from './pages/Help';
import './style.css';

const pages = [
	{ id: 'wp-agent-dashboard', Component: Dashboard },
	{ id: 'wp-agent-settings', Component: Settings },
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
} );
