import { useState, useEffect, useCallback } from '@wordpress/element';
import { Container, Title, Badge, Button, Toaster, toast } from '@bsf/force-ui';
import { Save, Loader2 } from 'lucide-react';
import ApiKeyForm from '../components/ApiKeyForm';
import ModelSelector from '../components/ModelSelector';
import RolePermissions from '../components/RolePermissions';
import StatusCard from '../components/StatusCard';
import QuickStats from '../components/QuickStats';

const { version, restUrl, nonce } = window.wpAgentData || {};

export default function Settings() {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ isSaving, setIsSaving ] = useState( false );

	// Settings state — populated from REST API.
	const [ hasApiKey, setHasApiKey ] = useState( false );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ defaultModel, setDefaultModel ] = useState( '' );
	const [ allowedRoles, setAllowedRoles ] = useState( [ 'administrator' ] );
	const [ rateLimit, setRateLimit ] = useState( 0 );
	const [ dailyLimit, setDailyLimit ] = useState( 0 );

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
			setRateLimit( data.rate_limit || 0 );
			setDailyLimit( data.daily_limit || 0 );
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
		setIsSaving( true );

		const payload = {};

		// Only send API key if the user typed a new one.
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

			// If API key was saved, clear the input and update status.
			if ( data.updated?.api_key ) {
				setApiKey( '' );
				setHasApiKey( true );
			}
		} catch ( error ) {
			toast.error( 'Failed to save settings.', {
				description: error.message,
			} );
		} finally {
			setIsSaving( false );
		}
	};

	if ( isLoading ) {
		return (
			<div className="min-h-screen bg-background-primary p-6 md:p-8 flex items-center justify-center">
				<Container direction="row" align="center" gap="sm">
					<Loader2 size={ 20 } className="animate-spin text-icon-secondary" />
					<span className="text-text-secondary text-sm">Loading settings...</span>
				</Container>
			</div>
		);
	}

	return (
		<>
			<Toaster position="top-right" />
			<div className="min-h-screen bg-background-primary p-6 md:p-8">
				{ /* Header */ }
				<Container
					direction="row"
					justify="between"
					align="center"
					className="mb-8"
				>
					<Container direction="row" align="center" gap="sm">
						<Title
							title="Settings"
							description="Configure your WP Agent preferences"
							size="md"
							tag="h1"
						/>
						{ version && (
							<Badge
								label={ `v${ version }` }
								variant="neutral"
								size="xs"
							/>
						) }
					</Container>
					<Button
						variant="primary"
						size="md"
						icon={ <Save size={ 16 } /> }
						onClick={ handleSave }
						loading={ isSaving }
						disabled={ isSaving }
					>
						Save Settings
					</Button>
				</Container>

				{ /* Two-column layout */ }
				<div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
					{ /* Left column — settings */ }
					<div className="lg:col-span-2 flex flex-col gap-6">
						<ApiKeyForm
							apiKey={ apiKey }
							onApiKeyChange={ setApiKey }
							hasApiKey={ hasApiKey }
						/>
						<ModelSelector
							model={ defaultModel }
							onModelChange={ setDefaultModel }
						/>
						<RolePermissions
							allowedRoles={ allowedRoles }
							onRolesChange={ setAllowedRoles }
						/>
					</div>

					{ /* Right column — status */ }
					<div className="flex flex-col gap-6">
						<StatusCard
							hasApiKey={ hasApiKey }
							defaultModel={ defaultModel }
							rateLimit={ rateLimit }
						/>
						<QuickStats />
					</div>
				</div>
			</div>
		</>
	);
}
