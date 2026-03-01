/**
 * Block actions hook.
 *
 * Watches pendingActions in the store and executes client-side
 * block insertion into the Gutenberg editor when action chunks
 * arrive from the AI streaming response.
 *
 * Supports nested block structures (groups, columns, covers)
 * for building complex landing page layouts.
 *
 * @package
 * @since 1.0.0
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { createBlock } from '@wordpress/blocks';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { STORE_NAME } from '../store/constants';

/**
 * Map of block types to their content attribute name.
 *
 * When the AI sends innerHTML, the client maps it to the
 * correct registered attribute for that block type.
 */
const CONTENT_ATTR_MAP = {
	'core/paragraph': 'content',
	'core/heading': 'content',
	'core/preformatted': 'content',
	'core/verse': 'content',
	'core/list-item': 'content',
	'core/code': 'content',
	'core/pullquote': 'value',
	'core/quote': 'value',
	'core/button': 'text',
	'core/freeform': 'content',
};

/**
 * Normalize core/cover attributes to match what WordPress expects.
 *
 * Fixes common mismatches that cause "Block contains unexpected content"
 * validation errors after save/reload:
 * - Splits "600px" minHeight into minHeight: 600 + minHeightUnit: "px"
 * - Removes isDark (auto-calculated by WP from overlay color)
 * - Ensures dimRatio is a number
 *
 * @param {Object} attrs Raw attributes from the AI.
 * @return {Object} Normalized attributes.
 */
function normalizeCoverAttrs( attrs ) {
	const normalized = { ...attrs };

	// Split minHeight string into value + unit.
	if ( typeof normalized.minHeight === 'string' && normalized.minHeight ) {
		const match = normalized.minHeight.match( /^(\d+(?:\.\d+)?)\s*(px|em|rem|vh|vw|%)$/ );
		if ( match ) {
			normalized.minHeight = parseFloat( match[ 1 ] );
			normalized.minHeightUnit = match[ 2 ];
		}
	}

	// Remove isDark — WordPress auto-calculates this from overlay color.
	delete normalized.isDark;

	// Ensure dimRatio is a number.
	if ( typeof normalized.dimRatio === 'string' ) {
		normalized.dimRatio = parseInt( normalized.dimRatio, 10 );
	}

	return normalized;
}

/**
 * Convert a block spec from the AI into a WP block object.
 *
 * Uses createBlock directly with proper attribute mapping.
 * Recursively processes innerBlocks for nested layouts.
 *
 * @param {Object} spec             Block spec from the server.
 * @param {string} spec.blockName   Block type name (e.g. "core/group").
 * @param {Object} spec.attrs       Block attributes (styling, config).
 * @param {string} spec.innerHTML   Text/HTML content for leaf blocks.
 * @param {Array}  spec.innerBlocks Nested child block specs.
 * @return {Object|null} WP block object or null if invalid.
 */
function specToBlock( spec ) {
	if ( ! spec || ! spec.blockName ) {
		return null;
	}

	const { blockName, attrs = {}, innerHTML = '', innerBlocks = [] } = spec;

	// Recursively convert inner blocks.
	const wpInnerBlocks = innerBlocks
		.map( specToBlock )
		.filter( Boolean );

	// Build the final attributes object with block-specific normalization.
	let finalAttrs = { ...attrs };

	if ( blockName === 'core/cover' ) {
		finalAttrs = normalizeCoverAttrs( finalAttrs );
	}

	// Map innerHTML to the block's content attribute.
	if ( innerHTML ) {
		const contentAttr = CONTENT_ATTR_MAP[ blockName ];
		if ( contentAttr && ! finalAttrs[ contentAttr ] ) {
			finalAttrs[ contentAttr ] = innerHTML;
		}
	}

	try {
		return createBlock( blockName, finalAttrs, wpInnerBlocks );
	} catch {
		// Unknown block type — skip it.
		return null;
	}
}

/**
 * Hook that processes pending client-side actions from the AI.
 *
 * Handles `insert_blocks` actions by converting block specs to
 * WP block objects and dispatching them to the editor.
 *
 * Actions arrive one at a time from the SSE stream (each tool call
 * iteration adds one action). The hook processes each action as it
 * arrives, then clears it to accept the next one.
 *
 * Supports three positions:
 * - "append": insert after existing content (default)
 * - "prepend": insert before existing content
 * - "replace": clear editor and insert fresh (for full page builds)
 */
export function useBlockActions() {
	const pendingActions = useSelect(
		( select ) => select( STORE_NAME ).getPendingActions(),
		[]
	);

	const { clearPendingActions } = useDispatch( STORE_NAME );
	const { insertBlocks, resetBlocks } = useDispatch( blockEditorStore );

	useEffect( () => {
		if ( ! pendingActions.length ) {
			return;
		}

		for ( const pending of pendingActions ) {
			if ( pending.action !== 'insert_blocks' || ! pending.data?.blocks ) {
				continue;
			}

			const { blocks: blockSpecs, position = 'append' } = pending.data;

			// Convert all specs to WP block objects.
			const wpBlocks = blockSpecs
				.map( specToBlock )
				.filter( Boolean );

			if ( ! wpBlocks.length ) {
				continue;
			}

			if ( position === 'replace' ) {
				resetBlocks( wpBlocks );
			} else if ( position === 'prepend' ) {
				insertBlocks( wpBlocks, 0 );
			} else {
				insertBlocks( wpBlocks );
			}
		}

		clearPendingActions();
	}, [ pendingActions, clearPendingActions, insertBlocks, resetBlocks ] );
}
