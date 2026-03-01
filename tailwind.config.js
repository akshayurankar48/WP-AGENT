const withTW = require( '@bsf/force-ui/withTW' );

module.exports = withTW( {
	content: [ './src/**/*.{js,jsx}' ],
	corePlugins: {
		preflight: false,
	},
	important:
		':is(#wp-agent-dashboard, #wp-agent-settings, #wp-agent-history, #wp-agent-schedules, #wp-agent-capabilities, #wp-agent-help, #wp-agent-sidebar, [data-floating-ui-portal])',
} );
