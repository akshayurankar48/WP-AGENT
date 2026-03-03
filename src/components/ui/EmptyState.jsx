import { cn } from '../../utils/cn';

export default function EmptyState( { icon: Icon, title, description, action, className } ) {
	return (
		<div className={ cn(
			'flex flex-col items-center justify-center py-16 text-center',
			className
		) }>
			{ Icon && (
				<div className="flex items-center justify-center size-12 rounded-lg bg-background-secondary mb-4">
					<Icon className="size-5 text-icon-secondary" />
				</div>
			) }
			{ title && (
				<h3 className="text-base font-semibold text-text-primary mb-1">
					{ title }
				</h3>
			) }
			{ description && (
				<p className="text-sm text-text-secondary max-w-sm leading-relaxed">
					{ description }
				</p>
			) }
			{ action && (
				<div className="mt-4">
					{ action }
				</div>
			) }
		</div>
	);
}
