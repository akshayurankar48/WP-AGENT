import { Container, Title, Badge, Text } from '@bsf/force-ui';
import { Activity } from 'lucide-react';

const MODEL_LABELS = {
	'google/gemini-2.0-flash-001': 'Gemini Flash',
	'openai/gpt-4o-mini': 'GPT-4o Mini',
	'anthropic/claude-sonnet-4': 'Claude Sonnet 4',
};

export default function StatusCard( { hasApiKey = false, defaultModel = '', rateLimit = 0 } ) {
	const modelLabel = MODEL_LABELS[ defaultModel ] || defaultModel || 'Not set';

	const items = [
		{
			label: 'API Connection',
			value: hasApiKey ? 'Connected' : 'Not configured',
			variant: hasApiKey ? 'green' : 'yellow',
		},
		{
			label: 'Current Model',
			value: modelLabel,
			variant: 'neutral',
		},
		{
			label: 'Rate Limit',
			value: rateLimit ? `${ rateLimit } req/min` : 'Default',
			variant: 'green',
		},
	];

	return (
		<div className="rounded-lg border border-border-subtle bg-background-primary p-6">
			<Container direction="column" gap="md">
				<Container direction="row" align="center" gap="sm">
					<Activity size={ 20 } className="text-icon-secondary" />
					<Title title="Status" size="sm" />
				</Container>

				<Container direction="column" gap="sm">
					{ items.map( ( item ) => (
						<Container
							key={ item.label }
							direction="row"
							justify="between"
							align="center"
						>
							<Text size="sm" color="secondary">
								{ item.label }
							</Text>
							<Badge
								label={ item.value }
								variant={ item.variant }
								size="xs"
							/>
						</Container>
					) ) }
				</Container>
			</Container>
		</div>
	);
}
