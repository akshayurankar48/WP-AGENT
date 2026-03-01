import { Container, Title, Select, Text } from '@bsf/force-ui';
import { Cpu } from 'lucide-react';

// Must match model IDs in ai/model-router.php.
const MODELS = [
	{
		value: 'google/gemini-2.0-flash-001',
		label: 'Gemini 2.0 Flash — Fast',
		description: 'Quick responses for simple tasks.',
	},
	{
		value: 'openai/gpt-4o-mini',
		label: 'GPT-4o Mini — Balanced',
		description: 'Great balance of speed and quality. Recommended.',
	},
	{
		value: 'anthropic/claude-sonnet-4',
		label: 'Claude Sonnet 4 — Powerful',
		description: 'Best quality for complex tasks.',
	},
];

export default function ModelSelector( { model = '', onModelChange } ) {
	const selectedModel = MODELS.find( ( m ) => m.value === model );

	return (
		<Container direction="column" gap="md">
			<Container direction="row" align="center" gap="sm">
				<Cpu size={ 20 } className="text-icon-secondary" />
				<Title
					title="AI Model"
					description="Choose which model the agent uses. You can always change this later."
					size="sm"
				/>
			</Container>

			<Select
				size="md"
				value={ model }
				onChange={ ( value ) => onModelChange?.( value ) }
			>
				<Select.Button placeholder="Select a model" />
				<Select.Options>
					{ MODELS.map( ( m ) => (
						<Select.Option key={ m.value } value={ m.value }>
							{ m.label }
						</Select.Option>
					) ) }
				</Select.Options>
			</Select>

			{ selectedModel && (
				<Text size="sm" color="secondary">
					{ selectedModel.description }
				</Text>
			) }
		</Container>
	);
}
