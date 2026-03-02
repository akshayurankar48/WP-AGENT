import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Sidebar, toast } from '@bsf/force-ui';
import {
	Save,
	Loader2,
	Check,
	KeyRound,
	Palette,
	Cpu,
	Shield,
	Zap,
	Settings as SettingsIcon,
} from 'lucide-react';
import ApiKeyForm from '../components/ApiKeyForm';
import ProviderKeys from '../components/ProviderKeys';
import ModelSelector from '../components/ModelSelector';
import RolePermissions from '../components/RolePermissions';
import BrandPresets from '../components/BrandPresets';
import PageLayout from '../components/PageLayout';

const { restUrl, nonce } = window.wpAgentData || {};

const NAV_ITEMS = [
	{ slug: 'general', label: 'API Keys', icon: KeyRound },
	{ slug: 'providers', label: 'AI Backend', icon: Zap },
	{ slug: 'brand', label: 'Brand', icon: Palette },
	{ slug: 'model', label: 'AI Model', icon: Cpu },
	{ slug: 'permissions', label: 'Permissions', icon: Shield },
];

export default function Settings() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ saveState, setSaveState ] = useState( 'idle' );
	const [ activeTab, setActiveTab ] = useState( 'general' );

	const [ hasApiKey, setHasApiKey ] = useState( false );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ hasTavilyKey, setHasTavilyKey ] = useState( false );
	const [ tavilyKey, setTavilyKey ] = useState( '' );
	const [ defaultModel, setDefaultModel ] = useState( '' );
	const [ allowedRoles, setAllowedRoles ] = useState( [ 'administrator' ] );
	const [ brand, setBrand ] = useState( {} );
	const [ aiBackend, setAiBackend ] = useState( 'openrouter' );
	const [ configuredProviders, setConfiguredProviders ] = useState( {} );
	const [ providerKeys, setProviderKeys ] = useState( {} );

	const fetchSettings = useCallback( async () => {
		try {
			const response = await fetch( `${ restUrl }settings`, {
				headers: { 'X-WP-Nonce': nonce },
			} );

			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}

			const data = await response.json();

			setHasApiKey( data.has_api_key || false );
			setHasTavilyKey( data.has_tavily_key || false );
			setDefaultModel( data.default_model || '' );
			setAllowedRoles( data.allowed_roles || [ 'administrator' ] );
			setBrand( data.brand || {} );
			setAiBackend( data.ai_backend || 'openrouter' );
			setConfiguredProviders( data.configured_providers || {} );
		} catch ( error ) {
			toast.error( 'Failed to load settings.', {
				description: error.message,
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	const handleSave = async () => {
		setSaveState( 'saving' );

		const payload = {};

		if ( apiKey.trim() ) {
			payload.api_key = apiKey;
		}

		if ( tavilyKey.trim() ) {
			payload.tavily_api_key = tavilyKey;
		}

		if ( defaultModel ) {
			payload.default_model = defaultModel;
		}

		payload.allowed_roles = allowedRoles;
		payload.brand = brand;
		payload.ai_backend = aiBackend;

		// Provider keys.
		for ( const [ provider, key ] of Object.entries( providerKeys ) ) {
			if ( key.trim() ) {
				payload[ `${ provider }_api_key` ] = key;
			}
		}

		try {
			const response = await fetch( `${ restUrl }settings`, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': nonce,
				},
				body: JSON.stringify( payload ),
			} );

			const data = await response.json();

			if ( ! response.ok ) {
				throw new Error( data.message || `HTTP ${ response.status }` );
			}

			toast.success( 'Settings saved successfully!', {
				description: 'Your WP Agent configuration has been updated.',
			} );

			if ( data.updated?.api_key ) {
				setApiKey( '' );
				setHasApiKey( true );
			}

			if ( data.updated?.tavily_api_key ) {
				setTavilyKey( '' );
				setHasTavilyKey( true );
			}

			// Clear saved provider keys from input fields.
			const newProviderKeys = { ...providerKeys };
			let providersChanged = false;
			for ( const p of [ 'anthropic', 'openai', 'google' ] ) {
				if ( data.updated?.[ `${ p }_api_key` ] ) {
					delete newProviderKeys[ p ];
					providersChanged = true;
				}
			}
			if ( providersChanged ) {
				setProviderKeys( newProviderKeys );
				// Re-fetch to update configured_providers status.
				fetchSettings();
			}

			setSaveState( 'saved' );
			setTimeout( () => setSaveState( 'idle' ), 2000 );
		} catch ( error ) {
			toast.error( 'Failed to save settings.', {
				description: error.message,
			} );
			setSaveState( 'idle' );
		}
	};

	const saveButtonProps = {
		idle: {
			icon: <Save className="size-4" />,
			children: 'Save Settings',
		},
		saving: {
			icon: <Loader2 className="size-4 animate-spin" />,
			children: 'Saving...',
		},
		saved: {
			icon: <Check className="size-4" />,
			children: 'Saved',
		},
	};

	if ( isLoading ) {
		return (
			<PageLayout>
				<div className="flex flex-col items-center justify-center min-h-[60vh]">
					<Loader2 className="size-7 animate-spin text-brand-800 mb-3" />
					<p className="text-sm text-text-secondary">
						Loading settings...
					</p>
				</div>
			</PageLayout>
		);
	}

	return (
		<PageLayout>
			{ /* Header */ }
			<div className="flex items-center justify-between mb-6">
				<div className="flex items-center gap-3">
					<div className="flex items-center justify-center size-9 rounded-xl bg-violet-50">
						<SettingsIcon className="size-4.5 text-violet-600" />
					</div>
					<div>
						<h1 className="text-xl font-bold text-text-primary">
							Settings
						</h1>
						<p className="text-xs text-text-tertiary mt-0.5">
							Configure your WP Agent preferences
						</p>
					</div>
				</div>
				<Button
					variant="primary"
					size="md"
					icon={ saveButtonProps[ saveState ].icon }
					onClick={ handleSave }
					disabled={ saveState !== 'idle' }
				>
					{ saveButtonProps[ saveState ].children }
				</Button>
			</div>

			{ /* Settings Layout: Sidebar + Content */ }
			<div className="flex rounded-2xl border border-solid border-border-subtle bg-background-primary shadow-sm overflow-hidden min-h-[480px]">
				<Sidebar
					collapsible={ false }
					borderOn={ true }
					className="!w-56 !py-3 !px-3 !bg-background-secondary/50"
				>
					<Sidebar.Body>
						{ NAV_ITEMS.map( ( item ) => {
							const Icon = item.icon;
							const isActive = activeTab === item.slug;
							return (
								<Sidebar.Item key={ item.slug }>
									<button
										type="button"
										onClick={ () => setActiveTab( item.slug ) }
										className={ `flex items-center gap-3 w-full px-3 py-2.5 rounded-lg text-left border-0 cursor-pointer transition-all duration-150 ${
											isActive
												? 'bg-background-primary shadow-sm text-text-primary font-medium'
												: 'bg-transparent text-text-secondary hover:bg-background-primary hover:text-text-primary'
										}` }
									>
										<Icon className={ `size-4 shrink-0 ${
											isActive ? 'text-brand-800' : 'text-icon-secondary'
										}` } />
										<span className="text-sm">
											{ item.label }
										</span>
									</button>
								</Sidebar.Item>
							);
						} ) }
					</Sidebar.Body>
				</Sidebar>

				{ /* Content Panel */ }
				<div className="flex-1 p-6 overflow-y-auto">
					{ activeTab === 'general' && (
						<ApiKeyForm
							apiKey={ apiKey }
							onApiKeyChange={ setApiKey }
							hasApiKey={ hasApiKey }
							tavilyKey={ tavilyKey }
							onTavilyKeyChange={ setTavilyKey }
							hasTavilyKey={ hasTavilyKey }
						/>
					) }
					{ activeTab === 'providers' && (
						<ProviderKeys
							aiBackend={ aiBackend }
							onBackendChange={ setAiBackend }
							configuredProviders={ configuredProviders }
							providerKeys={ providerKeys }
							onProviderKeyChange={ ( provider, value ) =>
								setProviderKeys( ( prev ) => ( { ...prev, [ provider ]: value } ) )
							}
						/>
					) }
					{ activeTab === 'brand' && (
						<BrandPresets
							brand={ brand }
							onBrandChange={ setBrand }
						/>
					) }
					{ activeTab === 'model' && (
						<ModelSelector
							model={ defaultModel }
							onModelChange={ setDefaultModel }
						/>
					) }
					{ activeTab === 'permissions' && (
						<RolePermissions
							allowedRoles={ allowedRoles }
							onRolesChange={ setAllowedRoles }
						/>
					) }
				</div>
			</div>
		</PageLayout>
	);
}
