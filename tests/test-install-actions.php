<?php
/**
 * Test script for install_plugin, install_theme, search_theme, manage_theme actions.
 *
 * Run via WP-CLI:
 *   php -d "mysqli.default_socket=..." wp-cli.phar --path=... eval-file tests/test-install-actions.php
 *
 * Tests cover:
 *   1.  recommend_plugin search returns results
 *   2.  install_plugin by exact slug
 *   3.  install_plugin detects already-installed
 *   4.  install_plugin by name (search fallback)
 *   5.  install_plugin empty slug returns error
 *   6.  install_plugin non-existent returns error
 *   7.  search_theme returns results
 *   8.  install_theme by exact slug
 *   9.  install_theme detects already-installed
 *   10. install_theme by name (search fallback)
 *   11. install_theme non-existent returns error
 *   12. manage_theme list includes installed themes
 *   13. manage_theme switch to new theme
 *   14. manage_theme get_active confirms switch
 *   15. manage_theme switch back to original
 *   16. activate_plugin works on installed plugin
 *
 * @package WPAgent\Tests
 */

// Ensure we're running inside WordPress.
if ( ! defined( 'ABSPATH' ) ) {
	echo "ERROR: Must be run inside WordPress (wp eval-file).\n";
	exit( 1 );
}

// Force admin context so capabilities check passes.
wp_set_current_user( 1 );

// Load action registry — use fully qualified name.
if ( ! class_exists( 'WPAgent\Actions\Action_Registry' ) ) {
	echo "ERROR: WPAgent\\Actions\\Action_Registry class not found. Is the plugin active?\n";
	exit( 1 );
}
$registry = \WPAgent\Actions\Action_Registry::get_instance();
if ( ! $registry ) {
	echo "ERROR: Could not get Action_Registry instance.\n";
	exit( 1 );
}

$pass  = 0;
$fail  = 0;
$total = 0;
$original_theme = get_stylesheet();

/**
 * Run a single test.
 */
function run_test( $name, $action_name, $params, $expect_success, $expect_contains = '' ) {
	global $pass, $fail, $total;
	$total++;

	$registry = \WPAgent\Actions\Action_Registry::get_instance();
	$result   = $registry->dispatch( $action_name, $params );
	if ( is_wp_error( $result ) ) {
		$result = [
			'success' => false,
			'data'    => null,
			'message' => $result->get_error_message(),
		];
	}

	$success = isset( $result['success'] ) ? $result['success'] : false;
	$message = isset( $result['message'] ) ? $result['message'] : '';
	$passed  = true;

	if ( $expect_success && ! $success ) {
		$passed = false;
	}
	if ( ! $expect_success && $success ) {
		$passed = false;
	}
	if ( $expect_contains && false === stripos( $message . wp_json_encode( $result ), $expect_contains ) ) {
		$passed = false;
	}

	if ( $passed ) {
		echo "  PASS  | {$name}\n";
		$pass++;
	} else {
		echo "  FAIL  | {$name}\n";
		echo "         Expected success=" . ( $expect_success ? 'true' : 'false' ) . ", got=" . ( $success ? 'true' : 'false' ) . "\n";
		echo "         Message: {$message}\n";
		$fail++;
	}

	return $result;
}

echo "\n";
echo "============================================\n";
echo " WP Agent — Install Actions Test Suite\n";
echo "============================================\n\n";

// ---- Plugin Tests ----
echo "--- Plugin Tests ---\n\n";

// Test 1: Search plugins.
run_test(
	'recommend_plugin search returns results',
	'recommend_plugin',
	[ 'operation' => 'search', 'query' => 'elementor addons', 'per_page' => 3 ],
	true,
	'plugin'
);

// Test 2: Install plugin by exact slug.
// First remove hello-dolly if installed.
$installed = get_plugins();
foreach ( $installed as $file => $data ) {
	if ( 0 === strpos( $file, 'hello-dolly/' ) ) {
		deactivate_plugins( $file );
		delete_plugins( [ $file ] );
		break;
	}
}

run_test(
	'install_plugin by exact slug (hello-dolly)',
	'install_plugin',
	[ 'slug' => 'hello-dolly' ],
	true,
	'install'
);

// Test 3: Already installed detection.
run_test(
	'install_plugin detects already-installed (hello-dolly)',
	'install_plugin',
	[ 'slug' => 'hello-dolly' ],
	false,
	'already installed'
);

// Test 4: Install by name (search fallback) — "ultimate addons for elementor" → header-footer-elementor.
// First remove if installed.
$installed = get_plugins();
foreach ( $installed as $file => $data ) {
	if ( 0 === strpos( $file, 'header-footer-elementor/' ) ) {
		deactivate_plugins( $file );
		delete_plugins( [ $file ] );
		break;
	}
}

$result4 = run_test(
	'install_plugin by name "ultimate addons for elementor" (search fallback)',
	'install_plugin',
	[ 'slug' => 'ultimate addons for elementor' ],
	true,
	'install'
);

// Verify the resolved slug is correct.
$total++;
$resolved_slug = isset( $result4['data']['slug'] ) ? $result4['data']['slug'] : '';
if ( $resolved_slug === 'header-footer-elementor' ) {
	echo "  PASS  | Resolved slug is 'header-footer-elementor'\n";
	$pass++;
} else {
	echo "  FAIL  | Resolved slug expected 'header-footer-elementor', got '{$resolved_slug}'\n";
	$fail++;
}

// Test 5: Empty slug.
run_test(
	'install_plugin empty slug returns error',
	'install_plugin',
	[ 'slug' => '' ],
	false,
	'required'
);

// Test 6: Non-existent plugin.
run_test(
	'install_plugin non-existent slug returns error',
	'install_plugin',
	[ 'slug' => 'zzz-no-such-plugin-exists-99999' ],
	false,
	''
);

echo "\n--- Theme Tests ---\n\n";

// Test 7: Search themes.
run_test(
	'search_theme returns results for "elementor"',
	'search_theme',
	[ 'query' => 'elementor', 'per_page' => 3 ],
	true,
	'theme'
);

// Test 8: Install theme by exact slug.
// Remove flavor if installed.
$theme_dir = get_theme_root() . '/flavor';
if ( is_dir( $theme_dir ) ) {
	delete_theme( 'flavor' );
}

run_test(
	'install_theme by exact slug (flavor)',
	'install_theme',
	[ 'slug' => 'flavor' ],
	true,
	'install'
);

// Test 9: Already installed.
run_test(
	'install_theme detects already-installed (flavor)',
	'install_theme',
	[ 'slug' => 'flavor' ],
	false,
	'already installed'
);

// Test 10: Install theme by name (search fallback) — "hello elementor" → hello-elementor.
$theme_dir = get_theme_root() . '/hello-elementor';
if ( is_dir( $theme_dir ) ) {
	// Switch away first if active.
	if ( get_stylesheet() === 'hello-elementor' ) {
		switch_theme( $original_theme );
	}
	delete_theme( 'hello-elementor' );
}

$result10 = run_test(
	'install_theme by name "hello elementor" (search fallback)',
	'install_theme',
	[ 'slug' => 'hello elementor' ],
	true,
	'install'
);

// Verify resolved slug.
$total++;
$resolved_slug = isset( $result10['data']['slug'] ) ? $result10['data']['slug'] : '';
if ( $resolved_slug === 'hello-elementor' ) {
	echo "  PASS  | Resolved theme slug is 'hello-elementor'\n";
	$pass++;
} else {
	echo "  FAIL  | Resolved theme slug expected 'hello-elementor', got '{$resolved_slug}'\n";
	$fail++;
}

// Test 11: Non-existent theme.
run_test(
	'install_theme non-existent returns error',
	'install_theme',
	[ 'slug' => 'zzz-no-such-theme-exists-99999' ],
	false,
	''
);

echo "\n--- Theme Management Tests ---\n\n";

// Test 12: List themes.
$result12 = run_test(
	'manage_theme list shows installed themes',
	'manage_theme',
	[ 'operation' => 'list' ],
	true,
	'theme'
);

// Verify hello-elementor appears in list.
$total++;
$found = false;
if ( isset( $result12['data']['themes'] ) ) {
	foreach ( $result12['data']['themes'] as $t ) {
		if ( $t['slug'] === 'hello-elementor' ) {
			$found = true;
			break;
		}
	}
}
if ( $found ) {
	echo "  PASS  | hello-elementor appears in theme list\n";
	$pass++;
} else {
	echo "  FAIL  | hello-elementor not found in theme list\n";
	$fail++;
}

// Test 13: Switch to hello-elementor.
run_test(
	'manage_theme switch to hello-elementor',
	'manage_theme',
	[ 'operation' => 'switch', 'stylesheet' => 'hello-elementor' ],
	true,
	'switch'
);

// Test 14: Verify active theme.
$result14 = run_test(
	'manage_theme get_active shows Hello Elementor',
	'manage_theme',
	[ 'operation' => 'get_active' ],
	true,
	'hello'
);

// Test 15: Switch back.
run_test(
	'manage_theme switch back to ' . $original_theme,
	'manage_theme',
	[ 'operation' => 'switch', 'stylesheet' => $original_theme ],
	true,
	'switch'
);

echo "\n--- Plugin Activation Tests ---\n\n";

// Test 16: Activate installed plugin.
run_test(
	'activate_plugin activates hello-dolly',
	'activate_plugin',
	[ 'plugin' => 'hello-dolly/hello.php' ],
	true,
	''
);

// Test 17: Deactivate plugin.
run_test(
	'deactivate_plugin deactivates hello-dolly',
	'deactivate_plugin',
	[ 'plugin' => 'hello-dolly/hello.php' ],
	true,
	''
);

echo "\n============================================\n";
echo " Results: {$pass}/{$total} passed";
if ( $fail > 0 ) {
	echo " ({$fail} FAILED)";
}
echo "\n============================================\n\n";

exit( $fail > 0 ? 1 : 0 );
