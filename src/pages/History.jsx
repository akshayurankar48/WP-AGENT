import { Container, Title, Badge } from '@bsf/force-ui';
import { Clock } from 'lucide-react';

const { version } = window.wpAgentData || {};

export default function History() {
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
						title="History"
						description="Review past actions taken by WP Agent"
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
				<Badge label="Coming Soon" variant="neutral" size="sm" />
			</Container>

			{ /* Empty state */ }
			<div className="flex flex-col items-center justify-center rounded-xl border border-border-subtle bg-background-primary p-12 shadow-sm text-center">
				<div className="flex items-center justify-center w-16 h-16 rounded-full bg-background-secondary mb-4">
					<Clock size={ 28 } className="text-icon-secondary" />
				</div>
				<h2 className="text-lg font-semibold text-text-primary mb-2">
					Action history will appear here
				</h2>
				<p className="text-sm text-text-secondary max-w-md">
					Once WP Agent starts executing actions, you'll see a
					detailed log of everything it does — timestamps, action
					types, affected content, and undo capability.
				</p>
			</div>
		</div>
	);
}
