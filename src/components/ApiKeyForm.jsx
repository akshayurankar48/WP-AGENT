import { useState } from '@wordpress/element';
import { Container, Title, Input, Button, Text, toast } from '@bsf/force-ui';
import { KeyRound, CheckCircle } from 'lucide-react';

const { restUrl, nonce } = window.wpAgentData || {};

export default function ApiKeyForm( { apiKey = '', onApiKeyChange, hasApiKey = false } ) {
	const [ isValidating, setIsValidating ] = useState( false );

	const handleValidate = async () => {
		if ( ! apiKey.trim() ) {
			toast.error( 'Please enter an API key.' );
			return;
		}

		setIsValidating( true );

		try {
			// Validate by attempting a save — the REST endpoint validates with OpenRouter.
			const response = await fetch( `${ restUrl }settings`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( { api_key: apiKey } ),
			} );

			const data = await response.json();

			if ( ! response.ok ) {
				throw new Error( data.message || 'Validation failed.' );
			}

			toast.success( 'API key validated and saved!', {
				description: 'Your OpenRouter key is securely stored.',
			} );

			// Clear input and update parent state.
			onApiKeyChange?.( '' );
		} catch ( error ) {
			toast.error( 'API key validation failed.', {
				description: error.message,
			} );
		} finally {
			setIsValidating( false );
		}
	};

	return (
		<div className="rounded-lg border border-border-subtle bg-background-primary p-6">
			<Container direction="column" gap="md">
				<Container direction="row" align="center" gap="sm">
					<KeyRound size={ 20 } className="text-icon-secondary" />
					<Title
						title="OpenRouter API Key"
						description="Connect to AI models via OpenRouter. Your key is stored securely in the database."
						size="sm"
					/>
				</Container>

				<Container direction="row" gap="sm" align="end">
					<div className="flex-1">
						<Input
							type="password"
							size="md"
							placeholder={
								hasApiKey
									? '••••••••••••••••'
									: 'sk-or-v1-…'
							}
							value={ apiKey }
							onChange={ ( value ) => onApiKeyChange?.( value ) }
						/>
					</div>
					<Button
						variant="outline"
						size="md"
						icon={ <CheckCircle size={ 16 } /> }
						onClick={ handleValidate }
						loading={ isValidating }
						disabled={ isValidating }
					>
						Validate Key
					</Button>
				</Container>

				{ hasApiKey && (
					<Text size="sm" color="success">
						An API key is already saved. Enter a new one to replace
						it.
					</Text>
				) }
			</Container>
		</div>
	);
}
