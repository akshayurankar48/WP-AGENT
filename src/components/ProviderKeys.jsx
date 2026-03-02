import { useState } from '@wordpress/element';
import { Input, Button, Text, toast } from '@bsf/force-ui';
import {
	CheckCircle,
	ShieldCheck,
	ExternalLink,
	Loader2,
	ToggleLeft,
	ToggleRight,
} from 'lucide-react';

const { restUrl, nonce } = window.wpAgentData || {};

const PROVIDERS = [
	{
		id: 'anthropic',
		name: 'Anthropic',
		description: 'Claude models (Sonnet 4, Opus 4, Haiku). Best for complex reasoning and tool use.',
		placeholder: 'sk-ant-...',
		color: 'orange',
		bgClass: 'bg-orange-50',
		textClass: 'text-orange-600',
		borderClass: 'border-orange-200',
		url: 'https://console.anthropic.com/settings/keys',
	},
	{
		id: 'openai',
		name: 'OpenAI',
		description: 'GPT-4o, GPT-4o Mini. Strong general-purpose models.',
		placeholder: 'sk-...',
		color: 'green',
		bgClass: 'bg-green-50',
		textClass: 'text-green-600',
		borderClass: 'border-green-200',
		url: 'https://platform.openai.com/api-keys',
	},
	{
		id: 'google',
		name: 'Google',
		description: 'Gemini 2.0 Flash, Gemini 2.5 Pro. Fast and multimodal.',
		placeholder: 'AIza...',
		color: 'blue',
		bgClass: 'bg-blue-50',
		textClass: 'text-blue-600',
		borderClass: 'border-blue-200',
		url: 'https://aistudio.google.com/app/apikey',
	},
];

export default function ProviderKeys( {
	aiBackend = 'openrouter',
	onBackendChange,
	configuredProviders = {},
	providerKeys = {},
	onProviderKeyChange,
} ) {
	const [ verifying, setVerifying ] = useState( {} );

	const handleVerify = async ( provider ) => {
		const key = providerKeys[ provider ] || '';
		if ( ! key.trim() ) {
			toast.error( 'Please enter an API key.' );
			return;
		}

		setVerifying( ( prev ) => ( { ...prev, [ provider ]: true } ) );

		try {
			const response = await fetch( `${ restUrl }verify-provider`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( { provider, api_key: key } ),
			} );

			const data = await response.json();

			if ( ! response.ok ) {
				throw new Error( data.message || 'Verification failed.' );
			}

			toast.success( `${ data.message }`, {
				description: 'Key verified. Click "Save Settings" to store it.',
			} );
		} catch ( error ) {
			toast.error( `${ provider.charAt( 0 ).toUpperCase() + provider.slice( 1 ) } key verification failed.`, {
				description: error.message,
			} );
		} finally {
			setVerifying( ( prev ) => ( { ...prev, [ provider ]: false } ) );
		}
	};

	const isProviders = aiBackend === 'providers';

	return (
		<div className="flex flex-col gap-6">
			{ /* Backend Toggle */ }
			<div className="flex flex-col gap-3">
				<h3 className="text-sm font-semibold text-text-primary">
					AI Backend
				</h3>
				<div className="flex gap-3">
					<button
						type="button"
						onClick={ () => onBackendChange?.( 'openrouter' ) }
						className={ `flex-1 flex items-center gap-3 p-4 rounded-xl border border-solid cursor-pointer transition-all duration-150 ${
							! isProviders
								? 'border-brand-800 bg-violet-50 shadow-sm'
								: 'border-border-subtle bg-background-primary hover:border-border-strong'
						}` }
					>
						<div className="flex-1 text-left">
							<p className={ `text-sm font-medium ${ ! isProviders ? 'text-brand-800' : 'text-text-primary' }` }>
								OpenRouter
							</p>
							<p className="text-xs text-text-tertiary mt-0.5">
								Single key, 100+ models
							</p>
						</div>
						{ ! isProviders
							? <ToggleRight className="size-5 text-brand-800" />
							: <ToggleLeft className="size-5 text-icon-secondary" />
						}
					</button>
					<button
						type="button"
						onClick={ () => onBackendChange?.( 'providers' ) }
						className={ `flex-1 flex items-center gap-3 p-4 rounded-xl border border-solid cursor-pointer transition-all duration-150 ${
							isProviders
								? 'border-brand-800 bg-violet-50 shadow-sm'
								: 'border-border-subtle bg-background-primary hover:border-border-strong'
						}` }
					>
						<div className="flex-1 text-left">
							<p className={ `text-sm font-medium ${ isProviders ? 'text-brand-800' : 'text-text-primary' }` }>
								Direct Providers
							</p>
							<p className="text-xs text-text-tertiary mt-0.5">
								Your own API keys, no middleman
							</p>
						</div>
						{ isProviders
							? <ToggleRight className="size-5 text-brand-800" />
							: <ToggleLeft className="size-5 text-icon-secondary" />
						}
					</button>
				</div>
			</div>

			{ /* Provider Keys (shown when Direct Providers is selected) */ }
			{ isProviders && (
				<div className="flex flex-col gap-5">
					<div className="border-t border-solid border-border-subtle" />

					{ PROVIDERS.map( ( provider ) => {
						const isConfigured = configuredProviders[ provider.id ] || false;
						const currentKey = providerKeys[ provider.id ] || '';
						const isVerifyingThis = verifying[ provider.id ] || false;

						return (
							<div key={ provider.id } className="flex flex-col gap-3">
								<div className="flex items-center gap-3">
									<div className={ `flex items-center justify-center size-8 rounded-lg ${ provider.bgClass } shrink-0` }>
										<span className={ `text-xs font-bold ${ provider.textClass }` }>
											{ provider.name.charAt( 0 ) }
										</span>
									</div>
									<div className="flex-1">
										<div className="flex items-center gap-2">
											<h4 className="text-sm font-semibold text-text-primary">
												{ provider.name }
											</h4>
											{ isConfigured && (
												<span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-medium">
													<ShieldCheck className="size-2.5" />
													Active
												</span>
											) }
										</div>
										<p className="text-xs text-text-tertiary">
											{ provider.description }
										</p>
									</div>
								</div>

								<div className="flex items-end gap-2">
									<div className="flex-1">
										<Input
											type="password"
											size="sm"
											placeholder={
												isConfigured
													? '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'
													: provider.placeholder
											}
											value={ currentKey }
											onChange={ ( value ) =>
												onProviderKeyChange?.( provider.id, value )
											}
										/>
									</div>
									<Button
										variant="outline"
										size="sm"
										icon={
											isVerifyingThis
												? <Loader2 className="size-3.5 animate-spin" />
												: <CheckCircle size={ 14 } />
										}
										onClick={ () => handleVerify( provider.id ) }
										disabled={ isVerifyingThis || ! currentKey.trim() }
									>
										Verify
									</Button>
								</div>

								{ ! isConfigured && (
									<p className="text-xs text-text-tertiary">
										Get your key at{ ' ' }
										<a
											href={ provider.url }
											target="_blank"
											rel="noopener noreferrer"
											className="text-brand-800 font-medium hover:underline inline-flex items-center gap-1"
										>
											{ new URL( provider.url ).hostname }
											<ExternalLink className="size-3" />
										</a>
									</p>
								) }

								{ provider.id !== 'google' && (
									<div className="border-t border-solid border-border-subtle" />
								) }
							</div>
						);
					} ) }
				</div>
			) }
		</div>
	);
}
