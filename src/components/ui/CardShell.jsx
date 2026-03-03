import { cn } from '../../utils/cn';

export default function CardShell( { children, className, hover = true, ...props } ) {
	return (
		<div
			className={ cn(
				'rounded-lg border border-solid border-border-subtle bg-background-primary',
				hover && 'hover:shadow-sm transition-shadow duration-200',
				className
			) }
			{ ...props }
		>
			{ children }
		</div>
	);
}
