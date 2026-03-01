/**
 * Gutenberg editor entry point.
 *
 * Registers the WP Agent PluginSidebar in the block editor.
 *
 * @package
 * @since 1.0.0
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar } from '@wordpress/edit-post';
import { Icon } from '@wordpress/components';
import ChatPanel from './editor/ChatPanel';
import './store';
import './style.css';

const WPAgentIcon = () => (
	<Icon
		icon={
			<svg
				xmlns="http://www.w3.org/2000/svg"
				viewBox="0 0 24 24"
				width="24"
				height="24"
				fill="none"
				stroke="currentColor"
				strokeWidth="2"
				strokeLinecap="round"
				strokeLinejoin="round"
			>
				<path d="M12 8V4H8" />
				<rect width="16" height="12" x="4" y="8" rx="2" />
				<path d="M2 14h2" />
				<path d="M20 14h2" />
				<path d="M15 13v2" />
				<path d="M9 13v2" />
			</svg>
		}
	/>
);

registerPlugin( 'wp-agent', {
	render: () => (
		<PluginSidebar
			name="wp-agent-sidebar"
			title="JARVIS"
			icon={ <WPAgentIcon /> }
		>
			<ChatPanel />
		</PluginSidebar>
	),
} );
