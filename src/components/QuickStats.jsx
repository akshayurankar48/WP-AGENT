import { Container, Title, Text } from '@bsf/force-ui';
import { Database, Zap, MessageSquare } from 'lucide-react';

const STATS = [
	{
		icon: Database,
		label: 'DB Tables',
		value: '0 / 4',
	},
	{
		icon: Zap,
		label: 'Available Actions',
		value: '12',
	},
	{
		icon: MessageSquare,
		label: 'Conversations',
		value: '0',
	},
];

export default function QuickStats() {
	return (
		<div className="rounded-lg border border-border-subtle bg-background-primary p-6">
			<Container direction="column" gap="md">
				<Title title="Quick Stats" size="sm" />

				<Container direction="column" gap="sm">
					{ STATS.map( ( stat ) => {
						const Icon = stat.icon;
						return (
							<Container
								key={ stat.label }
								direction="row"
								align="center"
								gap="sm"
								className="rounded-md border border-border-subtle p-3"
							>
								<Icon
									size={ 18 }
									className="text-icon-secondary"
								/>
								<Container
									direction="column"
									gap="xs"
									className="flex-1"
								>
									<Text size="xs" color="secondary">
										{ stat.label }
									</Text>
									<Text
										size="md"
										weight="semibold"
										color="primary"
									>
										{ stat.value }
									</Text>
								</Container>
							</Container>
						);
					} ) }
				</Container>
			</Container>
		</div>
	);
}
