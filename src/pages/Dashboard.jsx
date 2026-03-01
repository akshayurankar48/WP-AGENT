import { Container, Title, Badge, Button } from '@bsf/force-ui';
import {
	CheckCircle2,
	Circle,
	ExternalLink,
	Settings,
	Cpu,
	Wifi,
} from 'lucide-react';

const { hasApiKey, version, adminUrl } = window.wpAgentData || {};

function ChecklistItem( { done, label, action } ) {
	return (
		<div className="flex items-center justify-between py-2">
			<div className="flex items-center gap-3">
				{ done ? (
					<CheckCircle2
						size={ 18 }
						className="text-text-success shrink-0"
					/>
				) : (
					<Circle
						size={ 18 }
						className="text-icon-secondary shrink-0"
					/>
				) }
				<span
					className={ `text-sm ${
						done
							? 'text-text-secondary line-through'
							: 'text-text-primary font-medium'
					}` }
				>
					{ label }
				</span>
			</div>
			{ ! done && action }
		</div>
	);
}

function StatusRow( { icon: Icon, label, value } ) {
	return (
		<div className="flex items-center justify-between py-2">
			<div className="flex items-center gap-2 text-text-secondary">
				<Icon size={ 14 } />
				<span className="text-sm">{ label }</span>
			</div>
			<span className="text-sm font-medium text-text-primary">
				{ value }
			</span>
		</div>
	);
}

export default function Dashboard() {
	const settingsUrl = `${ adminUrl }admin.php?page=wp-agent-settings`;
	const setupComplete = hasApiKey;

	return (
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
						title="Dashboard"
						description="Your WP Agent overview"
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
			</Container>

			<div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
				{ /* Setup Checklist */ }
				<div className="rounded-xl border border-border-subtle bg-background-primary p-6 shadow-sm">
					<div className="flex items-center justify-between mb-4">
						<h2 className="text-base font-semibold text-text-primary">
							Setup Checklist
						</h2>
						{ setupComplete && (
							<Badge
								label="Complete"
								variant="green"
								size="xs"
							/>
						) }
					</div>
					<div className="divide-y divide-border-subtle">
						<ChecklistItem
							done={ true }
							label="Install WP Agent plugin"
						/>
						<ChecklistItem
							done={ hasApiKey }
							label="Configure API key"
							action={
								<a href={ settingsUrl }>
									<Button
										variant="ghost"
										size="xs"
										icon={ <Settings size={ 14 } /> }
									>
										Settings
									</Button>
								</a>
							}
						/>
						<ChecklistItem
							done={ false }
							label="Select a preferred model"
							action={
								<a href={ settingsUrl }>
									<Button
										variant="ghost"
										size="xs"
										icon={ <Settings size={ 14 } /> }
									>
										Settings
									</Button>
								</a>
							}
						/>
						<ChecklistItem
							done={ false }
							label="Send your first message"
							action={
								<Button
									variant="ghost"
									size="xs"
									icon={ <ExternalLink size={ 14 } /> }
									onClick={ () => {
										window.location.href = `${ adminUrl }post-new.php`;
									} }
								>
									Open Editor
								</Button>
							}
						/>
					</div>
				</div>

				{ /* System Status */ }
				<div className="rounded-xl border border-border-subtle bg-background-primary p-6 shadow-sm">
					<h2 className="text-base font-semibold text-text-primary mb-4">
						System Status
					</h2>
					<div className="divide-y divide-border-subtle">
						<StatusRow
							icon={ Wifi }
							label="API Connection"
							value={ hasApiKey ? 'Configured' : 'Not configured' }
						/>
						<StatusRow
							icon={ Cpu }
							label="Model"
							value="Not selected"
						/>
						<StatusRow
							icon={ Settings }
							label="Plugin Version"
							value={ version ? `v${ version }` : '--' }
						/>
					</div>

					<div className="mt-6">
						<Button
							variant="primary"
							size="md"
							className="w-full"
							icon={ <ExternalLink size={ 16 } /> }
							onClick={ () => {
								window.location.href = `${ adminUrl }post-new.php`;
							} }
						>
							Open Editor
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
}
