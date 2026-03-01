import { useState, useEffect, useCallback } from '@wordpress/element';
import { Container, Button, Tabs, toast } from '@bsf/force-ui';
import { Save, Loader2, Check } from 'lucide-react';
import ApiKeyForm from '../components/ApiKeyForm';
import ModelSelector from '../components/ModelSelector';
import RolePermissions from '../components/RolePermissions';
import PageLayout from '../components/PageLayout';

const { restUrl, nonce } = window.wpAgentData || {};

export default function Settings() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ saveState, setSaveState ] = useState( 'idle' ); // idle | saving | saved
	const [ activeTab, setActiveTab ] = useState( 'general' );

	// Settings state.
	const [ hasApiKey, setHasApiKey ] = useState( false );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ defaultModel, setDefaultModel ] = useState( '' );
	const [ allowedRoles, setAllowedRoles ] = useState( [ 'administrator' ] );

	// Fetch settings on mount.
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
			setDefaultModel( data.default_model || '' );
			setAllowedRoles( data.allowed_roles || [ 'administrator' ] );
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

	// Save all settings.
	const handleSave = async () => {
		setSaveState( 'saving' );

		const payload = {};

		if ( apiKey.trim() ) {
			payload.api_key = apiKey;
		}

		if ( defaultModel ) {
			payload.default_model = defaultModel;
		}

		payload.allowed_roles = allowedRoles;

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
				<div className="flex items-center justify-center min-h-[60vh]">
					<Container direction="row" align="center" gap="sm">
						<Loader2
							className="size-5 animate-spin text-icon-secondary"
						/>
						<span className="text-text-secondary text-sm">
							Loading settings...
						</span>
					</Container>
				</div>
			</PageLayout>
		);
	}

	return (
		<PageLayout>
			<div className="max-w-[768px] mx-auto">
				{ /* Header row */ }
				<div className="flex items-center justify-between mb-6">
					<div>
						<h1 className="text-xl font-semibold text-text-primary">
							Settings
						</h1>
						<p className="text-sm text-text-secondary mt-0.5">
							Configure your WP Agent preferences
						</p>
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

				{ /* Tab card */ }
				<div className="bg-background-primary border-0.5 border-solid border-border-subtle rounded-xl shadow-sm">
					<Tabs activeItem={ activeTab }>
						<Tabs.Group
							activeItem={ activeTab }
							onChange={ ( { value } ) =>
								setActiveTab( value )
							}
							className="border-b border-solid border-border-subtle px-6 pt-2"
							size="md"
						>
							<Tabs.Tab slug="general">General</Tabs.Tab>
							<Tabs.Tab slug="model">AI Model</Tabs.Tab>
							<Tabs.Tab slug="permissions">
								Permissions
							</Tabs.Tab>
						</Tabs.Group>

						<div className="p-6">
							<Tabs.Panel slug="general">
								<ApiKeyForm
									apiKey={ apiKey }
									onApiKeyChange={ setApiKey }
									hasApiKey={ hasApiKey }
								/>
							</Tabs.Panel>
							<Tabs.Panel slug="model">
								<ModelSelector
									model={ defaultModel }
									onModelChange={ setDefaultModel }
								/>
							</Tabs.Panel>
							<Tabs.Panel slug="permissions">
								<RolePermissions
									allowedRoles={ allowedRoles }
									onRolesChange={ setAllowedRoles }
								/>
							</Tabs.Panel>
						</div>
					</Tabs>
				</div>
			</div>
		</PageLayout>
	);
}
