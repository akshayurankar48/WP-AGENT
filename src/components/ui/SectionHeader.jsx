import { Badge } from '@bsf/force-ui';
import { cn } from '../../utils/cn';

export default function SectionHeader( { icon: Icon, title, badge, actions, className } ) {
	return (
		<div className={ cn( 'flex items-center justify-between mb-4', className ) }>
			<div className="flex items-center gap-2">
				{ Icon && <Icon className="size-4 text-icon-secondary" /> }
				<h2 className="text-sm font-medium uppercase tracking-wider text-text-tertiary">
					{ title }
				</h2>
				{ badge && (
					<Badge
						label={ badge }
						variant="neutral"
						size="xs"
					/>
				) }
			</div>
			{ actions && (
				<div className="flex items-center gap-2">
					{ actions }
				</div>
			) }
		</div>
	);
}
