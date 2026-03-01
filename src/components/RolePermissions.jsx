import { Switch, Text } from '@bsf/force-ui';
import { Shield, ShieldCheck, ShieldAlert } from 'lucide-react';

const ALL_ROLES = [
	{ slug: 'administrator', label: 'Administrator', description: 'Full access to all WordPress features', locked: true },
	{ slug: 'editor', label: 'Editor', description: 'Can manage and publish all posts', locked: false },
	{ slug: 'author', label: 'Author', description: 'Can publish and manage own posts', locked: false },
	{ slug: 'contributor', label: 'Contributor', description: 'Can write but not publish posts', locked: false },
	{ slug: 'subscriber', label: 'Subscriber', description: 'Can only manage their profile', locked: false },
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

	const enabledCount = allowedRoles.length;

	return (
		<div className="flex flex-col gap-6">
			<div className="flex items-center gap-3">
				<div className="flex items-center justify-center size-9 rounded-lg bg-rose-50 shrink-0">
					<Shield className="size-4 text-rose-600" />
				</div>
				<div>
					<h3 className="text-sm font-semibold text-text-primary">
						Role Permissions
					</h3>
					<p className="text-xs text-text-tertiary mt-0.5">
						Choose which WordPress roles can interact with the AI agent ({ enabledCount } enabled).
					</p>
				</div>
			</div>

			<div className="flex flex-col gap-2">
				{ ALL_ROLES.map( ( role ) => {
					const isEnabled = allowedRoles.includes( role.slug );

					return (
						<div
							key={ role.slug }
							className={ `flex items-center justify-between rounded-xl border border-solid px-4 py-3.5 transition-all duration-200 ${
								isEnabled
									? 'border-border-interactive bg-background-primary shadow-sm'
									: 'border-border-subtle bg-background-primary hover:border-border-interactive'
							}` }
						>
							<div className="flex items-center gap-3">
								<div className={ `flex items-center justify-center size-8 rounded-lg shrink-0 ${
									isEnabled ? 'bg-emerald-50' : 'bg-background-secondary'
								}` }>
									{ isEnabled
										? <ShieldCheck className="size-3.5 text-emerald-600" />
										: <ShieldAlert className="size-3.5 text-icon-secondary" />
									}
								</div>
								<div>
									<div className="flex items-center gap-2">
										<Text size="sm" className="font-medium text-text-primary">
											{ role.label }
										</Text>
										{ role.locked && (
											<span className="text-[10px] font-semibold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded">
												ALWAYS ON
											</span>
										) }
									</div>
									<Text size="xs" className="text-text-tertiary mt-0.5">
										{ role.description }
									</Text>
								</div>
							</div>
							<Switch
								size="sm"
								value={ isEnabled }
								onChange={ () => toggleRole( role.slug ) }
								disabled={ role.locked }
							/>
						</div>
					);
				} ) }
			</div>

			<p className="text-xs text-text-tertiary px-1">
				Only enabled roles will see the JARVIS sidebar in the editor.
				Administrators always have access.
			</p>
		</div>
	);
}
