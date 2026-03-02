<?php
/**
 * Manage Taxonomies Action.
 *
 * Creates terms, lists existing terms, assigns terms to posts,
 * and removes term assignments across categories, tags, and
 * custom taxonomies.
 *
 * @package WPAgent\Actions
 * @since   1.0.0
 */

namespace WPAgent\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Manage_Taxonomies
 *
 * @since 1.0.0
 */
class Manage_Taxonomies implements Action_Interface {

	/**
	 * Maximum terms returned per list query.
	 *
	 * @var int
	 */
	const MAX_TERMS = 100;

	/**
	 * Get the action name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return 'manage_taxonomies';
	}

	/**
	 * Get the action description.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_description(): string {
		return 'Manage WordPress categories, tags, and custom taxonomy terms. '
			. 'Create new terms, list existing ones, assign terms to posts, or remove term assignments. '
			. 'Use this to organize content — "Create a Products category and assign these posts to it."';
	}

	/**
	 * Get the JSON Schema for parameters.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_parameters(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'operation'        => array(
					'type'        => 'string',
					'enum'        => array( 'list', 'create', 'assign', 'remove' ),
					'description' => 'Operation to perform: list terms, create a term, assign terms to a post, or remove term assignments.',
				),
				'taxonomy'         => array(
					'type'        => 'string',
					'description' => 'Taxonomy slug (e.g. "category", "post_tag", or a custom taxonomy). Defaults to "category".',
				),
				'term_name'        => array(
					'type'        => 'string',
					'description' => 'Name of the new term. Required for the "create" operation.',
				),
				'term_slug'        => array(
					'type'        => 'string',
					'description' => 'Optional slug override for the new term. Used with "create".',
				),
				'parent_term_id'   => array(
					'type'        => 'integer',
					'description' => 'Parent term ID for hierarchical taxonomies. Used with "create".',
				),
				'term_description' => array(
					'type'        => 'string',
					'description' => 'Optional description for the new term. Used with "create".',
				),
				'term_ids'         => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'integer' ),
					'description' => 'Array of term IDs to assign or remove. Required for "assign" and "remove".',
				),
				'post_id'          => array(
					'type'        => 'integer',
					'description' => 'Post ID to assign terms to or remove terms from. Required for "assign" and "remove".',
				),
				'search'           => array(
					'type'        => 'string',
					'description' => 'Search string to filter term names. Used with "list".',
				),
			),
			'required'   => array( 'operation' ),
		);
	}

	/**
	 * Get the required capability.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_capabilities_required(): string {
		return 'manage_categories';
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
		$operation = ! empty( $params['operation'] ) ? sanitize_key( $params['operation'] ) : '';
		$taxonomy  = ! empty( $params['taxonomy'] ) ? sanitize_key( $params['taxonomy'] ) : 'category';

		// Validate operation.
		$allowed_operations = array( 'list', 'create', 'assign', 'remove' );
		if ( ! in_array( $operation, $allowed_operations, true ) ) {
			return array(
				'success' => false,
				'data'    => null,
				/* translators: %s: invalid operation name */
				'message' => sprintf(
					__( 'Invalid operation "%s". Allowed: list, create, assign, remove.', 'wp-agent' ),
					$operation
				),
			);
		}

		// Validate taxonomy exists (skip for 'list' of built-ins to allow graceful error messaging below).
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'success' => false,
				'data'    => null,
				/* translators: %s: taxonomy slug */
				'message' => sprintf(
					__( 'Taxonomy "%s" does not exist.', 'wp-agent' ),
					$taxonomy
				),
			);
		}

		switch ( $operation ) {
			case 'list':
				return $this->handle_list( $params, $taxonomy );
			case 'create':
				return $this->handle_create( $params, $taxonomy );
			case 'assign':
				return $this->handle_assign( $params, $taxonomy );
			case 'remove':
				return $this->handle_remove( $params, $taxonomy );
		}

		// Unreachable, but satisfies return type.
		return array(
			'success' => false,
			'data'    => null,
			'message' => __( 'Unknown operation.', 'wp-agent' ),
		);
	}

	/**
	 * Handle the 'list' operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $params   Raw parameters.
	 * @param string $taxonomy Sanitized taxonomy slug.
	 * @return array Execution result.
	 */
	private function handle_list( array $params, string $taxonomy ): array {
		$search = ! empty( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';

		$query_args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => self::MAX_TERMS,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( $search ) {
			$query_args['search'] = $search;
		}

		$terms = get_terms( $query_args );

		if ( is_wp_error( $terms ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => $terms->get_error_message(),
			);
		}

		if ( empty( $terms ) ) {
			$message = $search
				/* translators: 1: taxonomy slug, 2: search query */
				? sprintf( __( 'No terms found in "%1$s" matching "%2$s".', 'wp-agent' ), $taxonomy, $search )
				/* translators: %s: taxonomy slug */
				: sprintf( __( 'No terms found in "%s".', 'wp-agent' ), $taxonomy );

			return array(
				'success' => true,
				'data'    => array(
					'total' => 0,
					'terms' => array(),
				),
				'message' => $message,
			);
		}

		$results = array();
		foreach ( $terms as $term ) {
			$results[] = array(
				'id'          => $term->term_id,
				'name'        => sanitize_text_field( $term->name ),
				'slug'        => sanitize_title( $term->slug ),
				'count'       => absint( $term->count ),
				'parent'      => absint( $term->parent ),
				'description' => sanitize_text_field( $term->description ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'total'    => count( $results ),
				'taxonomy' => $taxonomy,
				'terms'    => $results,
			),
			/* translators: 1: number of terms found, 2: taxonomy slug */
			'message' => sprintf(
				__( 'Found %1$d term(s) in "%2$s".', 'wp-agent' ),
				count( $results ),
				$taxonomy
			),
		);
	}

	/**
	 * Handle the 'create' operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $params   Raw parameters.
	 * @param string $taxonomy Sanitized taxonomy slug.
	 * @return array Execution result.
	 */
	private function handle_create( array $params, string $taxonomy ): array {
		$term_name = ! empty( $params['term_name'] ) ? sanitize_text_field( $params['term_name'] ) : '';

		if ( ! $term_name ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'term_name is required for the "create" operation.', 'wp-agent' ),
			);
		}

		$insert_args = array();

		if ( ! empty( $params['term_slug'] ) ) {
			$insert_args['slug'] = sanitize_title( $params['term_slug'] );
		}

		if ( ! empty( $params['parent_term_id'] ) ) {
			$parent_id = absint( $params['parent_term_id'] );
			// Verify parent term exists in this taxonomy.
			if ( $parent_id && ! term_exists( $parent_id, $taxonomy ) ) {
				return array(
					'success' => false,
					'data'    => null,
					/* translators: 1: parent term ID, 2: taxonomy slug */
					'message' => sprintf(
						__( 'Parent term #%1$d does not exist in "%2$s".', 'wp-agent' ),
						$parent_id,
						$taxonomy
					),
				);
			}
			$insert_args['parent'] = $parent_id;
		}

		if ( ! empty( $params['term_description'] ) ) {
			$insert_args['description'] = sanitize_text_field( $params['term_description'] );
		}

		$result = wp_insert_term( $term_name, $taxonomy, $insert_args );

		if ( is_wp_error( $result ) ) {
			// Surface a friendly message for the common duplicate-term error.
			if ( 'term_exists' === $result->get_error_code() ) {
				$existing_id = absint( $result->get_error_data() );
				$existing    = get_term( $existing_id, $taxonomy );

				return array(
					'success' => false,
					'data'    => array(
						'existing_term_id' => $existing_id,
						'existing_slug'    => $existing instanceof \WP_Term ? sanitize_title( $existing->slug ) : '',
					),
					/* translators: 1: term name, 2: taxonomy slug, 3: existing term ID */
					'message' => sprintf(
						__( 'Term "%1$s" already exists in "%2$s" (ID: %3$d).', 'wp-agent' ),
						$term_name,
						$taxonomy,
						$existing_id
					),
				);
			}

			return array(
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			);
		}

		$term_id  = absint( $result['term_id'] );
		$new_term = get_term( $term_id, $taxonomy );

		return array(
			'success' => true,
			'data'    => array(
				'term_id'     => $term_id,
				'name'        => sanitize_text_field( $new_term->name ),
				'slug'        => sanitize_title( $new_term->slug ),
				'taxonomy'    => $taxonomy,
				'parent'      => absint( $new_term->parent ),
				'description' => sanitize_text_field( $new_term->description ),
			),
			/* translators: 1: term name, 2: term ID, 3: taxonomy slug */
			'message' => sprintf(
				__( 'Created term "%1$s" (ID: %2$d) in "%3$s".', 'wp-agent' ),
				sanitize_text_field( $new_term->name ),
				$term_id,
				$taxonomy
			),
		);
	}

	/**
	 * Handle the 'assign' operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $params   Raw parameters.
	 * @param string $taxonomy Sanitized taxonomy slug.
	 * @return array Execution result.
	 */
	private function handle_assign( array $params, string $taxonomy ): array {
		$post_id  = absint( $params['post_id'] ?? 0 );
		$term_ids = $this->sanitize_term_ids( $params['term_ids'] ?? array() );

		if ( ! $post_id ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'post_id is required for the "assign" operation.', 'wp-agent' ),
			);
		}

		if ( empty( $term_ids ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'term_ids is required for the "assign" operation.', 'wp-agent' ),
			);
		}

		// Verify post exists and user can edit it.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'data'    => null,
				/* translators: %d: post ID */
				'message' => sprintf( __( 'Post #%d not found.', 'wp-agent' ), $post_id ),
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to edit this post.', 'wp-agent' ),
			);
		}

		// Verify all term IDs exist in this taxonomy.
		$invalid_ids = array();
		foreach ( $term_ids as $tid ) {
			if ( ! term_exists( $tid, $taxonomy ) ) {
				$invalid_ids[] = $tid;
			}
		}

		if ( $invalid_ids ) {
			return array(
				'success' => false,
				'data'    => array( 'invalid_term_ids' => $invalid_ids ),
				/* translators: 1: taxonomy slug, 2: comma-separated list of invalid term IDs */
				'message' => sprintf(
					__( 'Term ID(s) not found in "%1$s": %2$s', 'wp-agent' ),
					$taxonomy,
					implode( ', ', $invalid_ids )
				),
			);
		}

		// Append terms (preserve existing assignments).
		$result = wp_set_object_terms( $post_id, $term_ids, $taxonomy, true );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			);
		}

		// Fetch the full list of assigned terms for confirmation.
		$assigned      = get_the_terms( $post_id, $taxonomy );
		$assigned_data = array();
		if ( $assigned && ! is_wp_error( $assigned ) ) {
			foreach ( $assigned as $term ) {
				$assigned_data[] = array(
					'id'   => $term->term_id,
					'name' => sanitize_text_field( $term->name ),
					'slug' => sanitize_title( $term->slug ),
				);
			}
		}

		return array(
			'success' => true,
			'data'    => array(
				'post_id'        => $post_id,
				'taxonomy'       => $taxonomy,
				'assigned_terms' => $assigned_data,
			),
			/* translators: 1: number of terms assigned, 2: post title, 3: post ID, 4: taxonomy slug */
			'message' => sprintf(
				__( 'Assigned %1$d term(s) to "%2$s" (Post #%3$d) in "%4$s".', 'wp-agent' ),
				count( $term_ids ),
				sanitize_text_field( $post->post_title ),
				$post_id,
				$taxonomy
			),
		);
	}

	/**
	 * Handle the 'remove' operation.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $params   Raw parameters.
	 * @param string $taxonomy Sanitized taxonomy slug.
	 * @return array Execution result.
	 */
	private function handle_remove( array $params, string $taxonomy ): array {
		$post_id  = absint( $params['post_id'] ?? 0 );
		$term_ids = $this->sanitize_term_ids( $params['term_ids'] ?? array() );

		if ( ! $post_id ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'post_id is required for the "remove" operation.', 'wp-agent' ),
			);
		}

		if ( empty( $term_ids ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'term_ids is required for the "remove" operation.', 'wp-agent' ),
			);
		}

		// Verify post exists and user can edit it.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'success' => false,
				'data'    => null,
				/* translators: %d: post ID */
				'message' => sprintf( __( 'Post #%d not found.', 'wp-agent' ), $post_id ),
			);
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'You do not have permission to edit this post.', 'wp-agent' ),
			);
		}

		$result = wp_remove_object_terms( $post_id, $term_ids, $taxonomy );

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => $result->get_error_message(),
			);
		}

		if ( false === $result ) {
			return array(
				'success' => false,
				'data'    => null,
				'message' => __( 'Failed to remove term assignments.', 'wp-agent' ),
			);
		}

		// Fetch remaining assigned terms for confirmation.
		$remaining      = get_the_terms( $post_id, $taxonomy );
		$remaining_data = array();
		if ( $remaining && ! is_wp_error( $remaining ) ) {
			foreach ( $remaining as $term ) {
				$remaining_data[] = array(
					'id'   => $term->term_id,
					'name' => sanitize_text_field( $term->name ),
					'slug' => sanitize_title( $term->slug ),
				);
			}
		}

		return array(
			'success' => true,
			'data'    => array(
				'post_id'         => $post_id,
				'taxonomy'        => $taxonomy,
				'removed_count'   => count( $term_ids ),
				'remaining_terms' => $remaining_data,
			),
			/* translators: 1: number of terms removed, 2: post title, 3: post ID, 4: taxonomy slug */
			'message' => sprintf(
				__( 'Removed %1$d term assignment(s) from "%2$s" (Post #%3$d) in "%4$s".', 'wp-agent' ),
				count( $term_ids ),
				sanitize_text_field( $post->post_title ),
				$post_id,
				$taxonomy
			),
		);
	}

	/**
	 * Sanitize an array of term IDs, ensuring each value is a positive integer.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $raw Raw input value.
	 * @return int[] Array of positive integers.
	 */
	private function sanitize_term_ids( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids = array();
		foreach ( $raw as $id ) {
			$clean = absint( $id );
			if ( $clean > 0 ) {
				$ids[] = $clean;
			}
		}

		return array_unique( $ids );
	}
}
