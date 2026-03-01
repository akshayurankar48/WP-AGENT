import { Container, Title, Badge } from '@bsf/force-ui';
import { BarChart3 } from 'lucide-react';

const { version } = window.wpAgentData || {};

function StatCard( { label, value } ) {
	return (
		<div className="rounded-xl border border-border-subtle bg-background-primary p-5 shadow-sm">
			<p className="text-sm text-text-secondary mb-1">{ label }</p>
			<p className="text-2xl font-semibold text-text-primary">
				{ value }
			</p>
		</div>
	);
}

export default function Usage() {
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
						title="Usage"
						description="Token usage and cost tracking"
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

			{ /* Stat cards */ }
			<div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
				<StatCard label="Tokens Used" value="--" />
				<StatCard label="Estimated Cost" value="--" />
				<StatCard label="Requests Today" value="--" />
			</div>

			{ /* Chart placeholder */ }
			<div className="flex flex-col items-center justify-center rounded-xl border border-border-subtle bg-background-primary p-12 shadow-sm text-center">
				<div className="flex items-center justify-center w-16 h-16 rounded-full bg-background-secondary mb-4">
					<BarChart3 size={ 28 } className="text-icon-secondary" />
				</div>
				<h2 className="text-lg font-semibold text-text-primary mb-2">
					Usage analytics will appear here
				</h2>
				<p className="text-sm text-text-secondary max-w-md">
					Track your token consumption, estimated costs, and request
					volume over time. Data will populate once you start using
					WP Agent.
				</p>
			</div>
		</div>
	);
}
