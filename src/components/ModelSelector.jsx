import { Select, Text, Badge } from '@bsf/force-ui';
import { Cpu, Zap, Scale, Sparkles } from 'lucide-react';

const MODELS = [
	{
		value: 'google/gemini-2.0-flash-001',
		label: 'Gemini 2.0 Flash',
		tier: 'Fast',
		description: 'Quick responses for simple tasks.',
		icon: Zap,
		color: { bg: 'bg-amber-50', text: 'text-amber-600', badge: 'yellow' },
	},
	{
		value: 'openai/gpt-4o-mini',
		label: 'GPT-4o Mini',
		tier: 'Balanced',
		description: 'Great balance of speed and quality. Recommended for most users.',
		icon: Scale,
		color: { bg: 'bg-blue-50', text: 'text-blue-600', badge: 'blue' },
		recommended: true,
	},
	{
		value: 'anthropic/claude-sonnet-4',
		label: 'Claude Sonnet 4',
		tier: 'Powerful',
		description: 'Best quality for complex tasks like full page builds.',
		icon: Sparkles,
		color: { bg: 'bg-violet-50', text: 'text-violet-600', badge: 'purple' },
	},
];

export default function ModelSelector( { model = '', onModelChange } ) {
	const selectedModel = MODELS.find( ( m ) => m.value === model );

	return (
		<div className="flex flex-col gap-6">
			<div className="flex items-center gap-3">
				<div className="flex items-center justify-center size-9 rounded-lg bg-emerald-50 shrink-0">
					<Cpu className="size-4 text-emerald-600" />
				</div>
				<div>
					<h3 className="text-sm font-semibold text-text-primary">
						AI Model
					</h3>
					<p className="text-xs text-text-tertiary mt-0.5">
						Choose which model the agent uses. You can always change this later.
					</p>
				</div>
			</div>

			{ /* Model Cards */ }
			<div className="flex flex-col gap-2.5">
				{ MODELS.map( ( m ) => {
					const Icon = m.icon;
					const isSelected = model === m.value;
					return (
						<button
							key={ m.value }
							type="button"
							onClick={ () => onModelChange?.( m.value ) }
							className={ `group flex items-center gap-4 p-4 rounded-xl border border-solid text-left cursor-pointer transition-all duration-200 ${
								isSelected
									? 'border-border-interactive bg-background-primary shadow-sm ring-1 ring-border-interactive'
									: 'border-border-subtle bg-background-primary hover:border-border-interactive hover:shadow-sm'
							}` }
						>
							<div className={ `flex items-center justify-center size-10 rounded-lg ${ m.color.bg } shrink-0` }>
								<Icon className={ `size-4.5 ${ m.color.text }` } />
							</div>
							<div className="flex-1 min-w-0">
								<div className="flex items-center gap-2">
									<span className="text-sm font-semibold text-text-primary">
										{ m.label }
									</span>
									<Badge
										label={ m.tier }
										variant={ m.color.badge }
										size="xs"
									/>
									{ m.recommended && (
										<Badge
											label="Recommended"
											variant="green"
											size="xs"
										/>
									) }
								</div>
								<p className="text-xs text-text-secondary mt-0.5 leading-relaxed">
									{ m.description }
								</p>
							</div>
							<div className={ `flex items-center justify-center size-5 rounded-full border-2 border-solid shrink-0 transition-colors duration-200 ${
								isSelected
									? 'border-brand-800 bg-brand-800'
									: 'border-border-subtle group-hover:border-border-interactive'
							}` }>
								{ isSelected && (
									<div className="size-2 rounded-full bg-white" />
								) }
							</div>
						</button>
					);
				} ) }
			</div>

			{ selectedModel && (
				<p className="text-xs text-text-tertiary px-1">
					Selected: <span className="font-medium text-text-secondary">{ selectedModel.label }</span> — { selectedModel.description }
				</p>
			) }
		</div>
	);
}
