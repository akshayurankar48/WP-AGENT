import { useState } from '@wordpress/element';
import { Container, Input, Button, Text, toast } from '@bsf/force-ui';
import { KeyRound, CheckCircle, Search, ExternalLink, ShieldCheck } from 'lucide-react';

const { restUrl, nonce } = window.wpAgentData || {};

export default function ApiKeyForm( {
	apiKey = '',
	onApiKeyChange,
	hasApiKey = false,
	tavilyKey = '',
	onTavilyKeyChange,
	hasTavilyKey = false,
} ) {
	const [ isValidating, setIsValidating ] = useState( false );

	const handleValidate = async () => {
		if ( ! apiKey.trim() ) {
			toast.error( 'Please enter an API key.' );
			return;
		}

		setIsValidating( true );

		try {
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
		<div className="flex flex-col gap-8">
			{ /* OpenRouter API Key */ }
			<div className="flex flex-col gap-4">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-9 rounded-lg bg-violet-50 shrink-0">
						<KeyRound className="size-4 text-violet-600" />
					</div>
					<div>
						<h3 className="text-sm font-semibold text-text-primary">
							OpenRouter API Key
						</h3>
						<p className="text-xs text-text-tertiary mt-0.5">
							Connect to AI models via OpenRouter. Your key is stored securely.
						</p>
					</div>
				</div>

				<div className="flex items-end gap-3">
					<div className="flex-1">
						<Input
							type="password"
							size="md"
							placeholder={
								hasApiKey
									? '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'
									: 'sk-or-v1-...'
							}
							value={ apiKey }
							onChange={ ( value ) =>
								onApiKeyChange?.( value )
							}
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
						Validate
					</Button>
				</div>

				{ hasApiKey && (
					<div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 border border-solid border-emerald-200">
						<ShieldCheck className="size-3.5 text-emerald-600 shrink-0" />
						<Text size="sm" className="text-emerald-700">
							API key saved and active. Enter a new one to replace it.
						</Text>
					</div>
				) }
			</div>

			{ /* Divider */ }
			<div className="border-t border-solid border-border-subtle" />

			{ /* Tavily API Key */ }
			<div className="flex flex-col gap-4">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-9 rounded-lg bg-blue-50 shrink-0">
						<Search className="size-4 text-blue-600" />
					</div>
					<div>
						<h3 className="text-sm font-semibold text-text-primary">
							Tavily API Key
						</h3>
						<p className="text-xs text-text-tertiary mt-0.5">
							Enable web search for research-driven content. Free tier: 1,000 searches/month.
						</p>
					</div>
				</div>

				<div className="flex-1">
					<Input
						type="password"
						size="md"
						placeholder={
							hasTavilyKey
								? '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'
								: 'tvly-...'
						}
						value={ tavilyKey }
						onChange={ ( value ) =>
							onTavilyKeyChange?.( value )
						}
					/>
				</div>

				{ hasTavilyKey ? (
					<div className="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 border border-solid border-emerald-200">
						<ShieldCheck className="size-3.5 text-emerald-600 shrink-0" />
						<Text size="sm" className="text-emerald-700">
							Tavily key saved. Web search is enabled.
						</Text>
					</div>
				) : (
					<p className="text-xs text-text-tertiary">
						Get your free API key at{ ' ' }
						<a
							href="https://app.tavily.com"
							target="_blank"
							rel="noopener noreferrer"
							className="text-brand-800 font-medium hover:underline inline-flex items-center gap-1"
						>
							app.tavily.com
							<ExternalLink className="size-3" />
						</a>
					</p>
				) }
			</div>
		</div>
	);
}
