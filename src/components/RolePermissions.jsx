import { Container, Title, Switch, Text } from '@bsf/force-ui';
import { Shield } from 'lucide-react';

const ALL_ROLES = [
	{ slug: 'administrator', label: 'Administrator', locked: true },
	{ slug: 'editor', label: 'Editor', locked: false },
	{ slug: 'author', label: 'Author', locked: false },
	{ slug: 'contributor', label: 'Contributor', locked: false },
	{ slug: 'subscriber', label: 'Subscriber', locked: false },
];

export default function RolePermissions( { allowedRoles = [], onRolesChange } ) {
	const toggleRole = ( slug ) => {
		if ( ! onRolesChange ) {
			return;
		}

		const isEnabled = allowedRoles.includes( slug );

		if ( isEnabled ) {
			onRolesChange( allowedRoles.filter( ( r ) => r !== slug ) );
		} else {
			onRolesChange( [ ...allowedRoles, slug ] );
		}
	};

	return (
		<Container direction="column" gap="md">
			<Container direction="row" align="center" gap="sm">
				<Shield size={ 20 } className="text-icon-secondary" />
				<Title
					title="Role Permissions"
					description="Choose which WordPress roles can interact with the AI agent."
					size="sm"
				/>
			</Container>

			<Container direction="column" gap="xs">
				{ ALL_ROLES.map( ( role ) => {
					const isEnabled = allowedRoles.includes( role.slug );

					return (
						<Container
							key={ role.slug }
							direction="row"
							justify="between"
							align="center"
							className="rounded-md border border-border-subtle px-4 py-3"
						>
							<Container direction="column" gap="xs">
								<Text size="sm" weight="medium">
									{ role.label }
								</Text>
								{ role.locked && (
									<Text size="xs" color="secondary">
										Always enabled
									</Text>
								) }
							</Container>
							<Switch
								size="sm"
								value={ isEnabled }
								onChange={ () => toggleRole( role.slug ) }
								disabled={ role.locked }
							/>
						</Container>
					);
				} ) }
			</Container>
		</Container>
	);
}
