import { cn } from '../../utils/cn';

export default function PageHeader( { title, description, actions, className } ) {
	return (
		<div className={ cn( 'flex items-center justify-between mb-6', className ) }>
			<div>
				<h1 className="text-2xl font-semibold text-text-primary">
					{ title }
				</h1>
				{ description && (
					<p className="text-sm text-text-tertiary mt-0.5">
						{ description }
					</p>
				) }
			</div>
			{ actions && (
				<div className="flex items-center gap-3">
					{ actions }
				</div>
			) }
		</div>
	);
}
