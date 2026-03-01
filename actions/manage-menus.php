<?php
/**
 * Manage Menus Action.
 *
 * Creates, updates, and manages WordPress navigation menus.
 * Supports listing menus, creating a menu, adding items
 * (pages, posts, custom links, categories), removing items,
 * and assigning menus to theme locations.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manage_Menus
 *
 * @since 1.0.0
 */
class Manage_Menus implements Action_Interface {

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_menus';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage WordPress navigation menus. '
			. 'Supports listing menus, creating a menu, adding items (pages, posts, custom links, categories), '
			. 'removing items, and assigning menus to theme locations. '
			. 'Example: "Add my new landing page to the main menu."';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_parameters(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'operation' => [
					'type'        => 'string',
					'enum'        => [ 'list', 'create', 'add_item', 'remove_item', 'assign_location' ],
					'description' => 'The menu operation to perform: list all menus, create a menu, add items to a menu, remove an item, or assign a menu to a theme location.',
				],
				'menu_name' => [
					'type'        => 'string',
					'description' => 'Name for the new menu. Required for the "create" operation.',
				],
				'menu_id'   => [
					'type'        => 'integer',
					'description' => 'Target menu ID. Required for "add_item", "remove_item", and "assign_location" operations.',
				],
				'items'     => [
					'type'        => 'array',
					'description' => 'Array of items to add to the menu. Required for the "add_item" operation.',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'type'      => [
								'type'        => 'string',
								'enum'        => [ 'page', 'post', 'custom', 'category' ],
								'description' => 'Menu item type.',
							],
							'object_id' => [
								'type'        => 'integer',
								'description' => 'WordPress object ID. Required for page, post, and category types.',
							],
							'title'     => [
								'type'        => 'string',
								'description' => 'Custom title override for the menu item. Optional.',
							],
							'url'       => [
								'type'        => 'string',
								'description' => 'URL for custom link items. Required when type is "custom".',
							],
						],
						'required'   => [ 'type' ],
					],
				],
				'item_id'   => [
					'type'        => 'integer',
					'description' => 'Menu item post ID to remove. Required for the "remove_item" operation.',
				],
				'location'  => [
					'type'        => 'string',
					'description' => 'Theme location slug to assign the menu to. Required for the "assign_location" operation.',
				],
			],
			'required'   => [ 'operation' ],
		];
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'edit_theme_options';
	}

	/**
	 * Whether this action is reversible.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_reversible(): bool {
		return true;
	}

	/**
	 * Execute the action.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Validated parameters.
	 * @return array Execution result.
	 */
	public function execute( array $params ): array {
		$operation = sanitize_key( $params['operation'] ?? '' );

		switch ( $operation ) {
			case 'list':
				return $this->list_menus();

			case 'create':
				return $this->create_menu( $params );

			case 'add_item':
				return $this->add_item( $params );

			case 'remove_item':
				return $this->remove_item( $params );

			case 'assign_location':
				return $this->assign_location( $params );

			default:
				return [
					'success' => false,
					'data'    => null,
					'message' => __( 'Invalid operation. Must be one of: list, create, add_item, remove_item, assign_location.', 'wp-agent' ),
				];
		}
	}

	/**
	 * List all navigation menus with item counts and location assignments.
	 *
	 * @since 1.0.0
	 * @return array Execution result.
	 */
	private function list_menus(): array {
		$menus             = wp_get_nav_menus();
		$registered_locs   = get_registered_nav_menus();
		$assigned_locs     = get_nav_menu_locations();

		// Build a map of menu ID => assigned location slugs.
		$menu_locations = [];
		foreach ( $assigned_locs as $location_slug => $menu_id ) {
			if ( $menu_id ) {
				$menu_locations[ $menu_id ][] = $location_slug;
			}
		}

		$menu_list = [];
		foreach ( $menus as $menu ) {
			$items      = wp_get_nav_menu_items( $menu->term_id );
			$item_count = is_array( $items ) ? count( $items ) : 0;

			$menu_list[] = [
				'id'         => $menu->term_id,
				'name'       => sanitize_text_field( $menu->name ),
				'slug'       => $menu->slug,
				'count'      => $item_count,
				'locations'  => $menu_locations[ $menu->term_id ] ?? [],
			];
		}

		// Build available theme locations list.
		$locations_list = [];
		foreach ( $registered_locs as $slug => $label ) {
			$locations_list[] = [
				'slug'        => sanitize_key( $slug ),
				'label'       => sanitize_text_field( $label ),
				'assigned_id' => $assigned_locs[ $slug ] ?? 0,
			];
		}

		if ( empty( $menu_list ) ) {
			return [
				'success' => true,
				'data'    => [
					'total'     => 0,
					'menus'     => [],
					'locations' => $locations_list,
				],
				'message' => __( 'No navigation menus found.', 'wp-agent' ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'total'     => count( $menu_list ),
				'menus'     => $menu_list,
				'locations' => $locations_list,
			],
			'message' => sprintf(
				/* translators: %d: number of menus */
				__( 'Found %d navigation menu(s).', 'wp-agent' ),
				count( $menu_list )
			),
		];
	}

	/**
	 * Create a new navigation menu.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Action parameters.
	 * @return array Execution result.
	 */
	private function create_menu( array $params ): array {
		$menu_name = sanitize_text_field( $params['menu_name'] ?? '' );

		if ( empty( $menu_name ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'menu_name is required for the "create" operation.', 'wp-agent' ),
			];
		}

		$menu_id = wp_create_nav_menu( $menu_name );

		if ( is_wp_error( $menu_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => $menu_id->get_error_message(),
			];
		}

		$menu = wp_get_nav_menu_object( $menu_id );

		return [
			'success' => true,
			'data'    => [
				'menu_id'   => (int) $menu_id,
				'term_id'   => $menu ? (int) $menu->term_id : (int) $menu_id,
				'name'      => $menu_name,
				'slug'      => $menu ? $menu->slug : '',
			],
			'message' => sprintf(
				/* translators: 1: menu name, 2: menu ID */
				__( 'Navigation menu "%1$s" created (ID: %2$d).', 'wp-agent' ),
				$menu_name,
				(int) $menu_id
			),
		];
	}

	/**
	 * Add one or more items to a navigation menu.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Action parameters.
	 * @return array Execution result.
	 */
	private function add_item( array $params ): array {
		$menu_id = absint( $params['menu_id'] ?? 0 );

		if ( ! $menu_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'menu_id is required for the "add_item" operation.', 'wp-agent' ),
			];
		}

		if ( ! is_nav_menu( $menu_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: menu ID */
					__( 'Menu #%d not found.', 'wp-agent' ),
					$menu_id
				),
			];
		}

		$items = $params['items'] ?? [];

		if ( empty( $items ) || ! is_array( $items ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'items array is required and must not be empty for the "add_item" operation.', 'wp-agent' ),
			];
		}

		$added       = [];
		$failed      = [];
		$valid_types = [ 'page', 'post', 'custom', 'category' ];

		foreach ( $items as $index => $item ) {
			$type      = sanitize_key( $item['type'] ?? '' );
			$object_id = absint( $item['object_id'] ?? 0 );
			$title     = sanitize_text_field( $item['title'] ?? '' );
			$url       = esc_url_raw( $item['url'] ?? '' );

			if ( ! in_array( $type, $valid_types, true ) ) {
				$failed[] = [
					'index'  => $index,
					'reason' => sprintf(
						/* translators: %s: provided type */
						__( 'Invalid type "%s". Must be one of: page, post, custom, category.', 'wp-agent' ),
						$type
					),
				];
				continue;
			}

			$menu_item_args = [ 'menu-item-status' => 'publish' ];

			switch ( $type ) {
				case 'page':
				case 'post':
					if ( ! $object_id ) {
						$failed[] = [
							'index'  => $index,
							'reason' => sprintf(
								/* translators: %s: item type */
								__( 'object_id is required for type "%s".', 'wp-agent' ),
								$type
							),
						];
						continue 2;
					}

					$post = get_post( $object_id );
					if ( ! $post ) {
						$failed[] = [
							'index'  => $index,
							'reason' => sprintf(
								/* translators: 1: type, 2: object ID */
								__( '%1$s #%2$d not found.', 'wp-agent' ),
								ucfirst( $type ),
								$object_id
							),
						];
						continue 2;
					}

					$menu_item_args['menu-item-object-id'] = $object_id;
					$menu_item_args['menu-item-object']    = $type;
					$menu_item_args['menu-item-type']      = 'post_type';

					if ( ! empty( $title ) ) {
						$menu_item_args['menu-item-title'] = $title;
					}
					break;

				case 'custom':
					if ( empty( $url ) ) {
						$failed[] = [
							'index'  => $index,
							'reason' => __( 'url is required for type "custom".', 'wp-agent' ),
						];
						continue 2;
					}

					if ( empty( $title ) ) {
						$failed[] = [
							'index'  => $index,
							'reason' => __( 'title is required for type "custom".', 'wp-agent' ),
						];
						continue 2;
					}

					$menu_item_args['menu-item-title'] = $title;
					$menu_item_args['menu-item-url']   = $url;
					$menu_item_args['menu-item-type']  = 'custom';
					break;

				case 'category':
					if ( ! $object_id ) {
						$failed[] = [
							'index'  => $index,
							'reason' => __( 'object_id is required for type "category".', 'wp-agent' ),
						];
						continue 2;
					}

					$term = get_term( $object_id, 'category' );
					if ( ! $term || is_wp_error( $term ) ) {
						$failed[] = [
							'index'  => $index,
							'reason' => sprintf(
								/* translators: %d: category ID */
								__( 'Category #%d not found.', 'wp-agent' ),
								$object_id
							),
						];
						continue 2;
					}

					$menu_item_args['menu-item-object-id'] = $object_id;
					$menu_item_args['menu-item-object']    = 'category';
					$menu_item_args['menu-item-type']      = 'taxonomy';

					if ( ! empty( $title ) ) {
						$menu_item_args['menu-item-title'] = $title;
					}
					break;
			}

			$item_id = wp_update_nav_menu_item( $menu_id, 0, $menu_item_args );

			if ( is_wp_error( $item_id ) ) {
				$failed[] = [
					'index'  => $index,
					'reason' => $item_id->get_error_message(),
				];
				continue;
			}

			$added[] = [
				'item_id'   => (int) $item_id,
				'type'      => $type,
				'object_id' => $object_id ?: null,
				'title'     => sanitize_text_field( $menu_item_args['menu-item-title'] ?? '' ),
			];
		}

		$added_count  = count( $added );
		$failed_count = count( $failed );

		if ( 0 === $added_count && $failed_count > 0 ) {
			return [
				'success' => false,
				'data'    => [ 'added' => [], 'failed' => $failed ],
				'message' => sprintf(
					/* translators: %d: number of failed items */
					__( 'Failed to add %d item(s) to the menu.', 'wp-agent' ),
					$failed_count
				),
			];
		}

		$message = sprintf(
			/* translators: 1: added count, 2: menu ID */
			__( 'Added %1$d item(s) to menu #%2$d.', 'wp-agent' ),
			$added_count,
			$menu_id
		);

		if ( $failed_count > 0 ) {
			$message .= ' ' . sprintf(
				/* translators: %d: failed count */
				__( '%d item(s) could not be added.', 'wp-agent' ),
				$failed_count
			);
		}

		return [
			'success' => true,
			'data'    => [
				'menu_id' => $menu_id,
				'added'   => $added,
				'failed'  => $failed,
			],
			'message' => $message,
		];
	}

	/**
	 * Remove an item from a navigation menu.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Action parameters.
	 * @return array Execution result.
	 */
	private function remove_item( array $params ): array {
		$item_id = absint( $params['item_id'] ?? 0 );

		if ( ! $item_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'item_id is required for the "remove_item" operation.', 'wp-agent' ),
			];
		}

		// Verify the post exists and is actually a nav_menu_item.
		$item = get_post( $item_id );

		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: item ID */
					__( 'Menu item #%d not found.', 'wp-agent' ),
					$item_id
				),
			];
		}

		$item_title = sanitize_text_field( $item->post_title );
		$deleted    = wp_delete_post( $item_id, true );

		if ( ! $deleted ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: item ID */
					__( 'Failed to remove menu item #%d.', 'wp-agent' ),
					$item_id
				),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'item_id' => $item_id,
			],
			'message' => sprintf(
				/* translators: 1: item title, 2: item ID */
				__( 'Menu item "%1$s" (ID: %2$d) removed successfully.', 'wp-agent' ),
				$item_title,
				$item_id
			),
		];
	}

	/**
	 * Assign a menu to a theme location.
	 *
	 * @since 1.0.0
	 *
	 * @param array $params Action parameters.
	 * @return array Execution result.
	 */
	private function assign_location( array $params ): array {
		$menu_id  = absint( $params['menu_id'] ?? 0 );
		$location = sanitize_key( $params['location'] ?? '' );

		if ( ! $menu_id ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'menu_id is required for the "assign_location" operation.', 'wp-agent' ),
			];
		}

		if ( empty( $location ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => __( 'location is required for the "assign_location" operation.', 'wp-agent' ),
			];
		}

		// Verify menu exists.
		if ( ! is_nav_menu( $menu_id ) ) {
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: %d: menu ID */
					__( 'Menu #%d not found.', 'wp-agent' ),
					$menu_id
				),
			];
		}

		// Validate that the location is registered by the active theme.
		$registered_locations = get_registered_nav_menus();
		if ( ! array_key_exists( $location, $registered_locations ) ) {
			$available = implode( ', ', array_keys( $registered_locations ) );
			return [
				'success' => false,
				'data'    => null,
				'message' => sprintf(
					/* translators: 1: requested location, 2: available locations */
					__( 'Location "%1$s" is not registered by the active theme. Available locations: %2$s.', 'wp-agent' ),
					$location,
					$available ?: __( 'none', 'wp-agent' )
				),
			];
		}

		// Merge with existing assignments to avoid clearing other locations.
		$current_assignments              = get_nav_menu_locations();
		$current_assignments[ $location ] = $menu_id;

		set_theme_mod( 'nav_menu_locations', $current_assignments );

		$menu        = wp_get_nav_menu_object( $menu_id );
		$menu_name   = $menu ? sanitize_text_field( $menu->name ) : '#' . $menu_id;
		$location_label = sanitize_text_field( $registered_locations[ $location ] );

		return [
			'success' => true,
			'data'    => [
				'menu_id'        => $menu_id,
				'menu_name'      => $menu_name,
				'location'       => $location,
				'location_label' => $location_label,
			],
			'message' => sprintf(
				/* translators: 1: menu name, 2: location label */
				__( 'Menu "%1$s" assigned to theme location "%2$s".', 'wp-agent' ),
				$menu_name,
				$location_label
			),
		];
	}
}
