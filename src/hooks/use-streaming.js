/**
 * SSE stream parser utility.
 *
 * Reads a fetch Response body as an SSE stream, parsing each
 * `data: {json}` line and invoking the callback with parsed chunks.
 *
 * @package
 * @since 1.0.0
 */

/**
 * Parse an SSE response stream.
 *
 * @param {Response} response Fetch Response with readable body.
 * @param {Function} onChunk  Callback receiving parsed chunk objects.
 * @return {Promise<void>} Resolves when stream ends.
 */
export async function parseSSEStream( response, onChunk ) {
	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let buffer = '';

	try {
		while ( true ) {
			const { done, value } = await reader.read();

			if ( done ) {
				break;
			}

			buffer += decoder.decode( value, { stream: true } );

			// Split on double newline (SSE event boundary).
			const parts = buffer.split( '\n' );
			// Keep the last incomplete part in the buffer.
			buffer = parts.pop();

			for ( const line of parts ) {
				const trimmed = line.trim();

				if ( ! trimmed || ! trimmed.startsWith( 'data: ' ) ) {
					continue;
				}

				const data = trimmed.slice( 6 ); // Remove 'data: ' prefix.

				// Skip the termination signal.
				if ( data === '[DONE]' ) {
					return;
				}

				try {
					const parsed = JSON.parse( data );
					onChunk( parsed );
				} catch {
					// Skip malformed JSON lines.
				}
			}
		}
	} finally {
		reader.releaseLock();
	}
}
