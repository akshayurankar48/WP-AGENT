import { Topbar, Badge, Toaster } from '@bsf/force-ui';
import { Bot } from 'lucide-react';
import { cn } from '../utils/cn';

const { adminUrl, currentPage, version } = window.jarvisAiData || {};

const NAV_ITEMS = [
	{ label: 'Dashboard', slug: 'jarvis-ai' },
	{ label: 'History', slug: 'jarvis-ai-history' },
	{ label: 'Schedules', slug: 'jarvis-ai-schedules' },
	{ label: 'Settings', slug: 'jarvis-ai-settings' },
	{ label: 'Capabilities', slug: 'jarvis-ai-capabilities' },
	{ label: 'Help', slug: 'jarvis-ai-help' },
];

export default function PageLayout( { children } ) {
	return (
		<div className="bg-background-secondary min-h-screen">
			<Toaster position="top-right" className="!z-[9999]" />

			<Topbar className="sticky top-0 z-50 border-b border-solid border-border-subtle bg-background-primary/95 backdrop-blur-sm px-4">
				<Topbar.Left gap="sm">
					<div className="flex items-center gap-2">
						<Bot className="size-5 text-brand-800" />
						<span className="text-base font-semibold text-text-primary">
							JARVIS
						</span>
					</div>
				</Topbar.Left>

				<Topbar.Middle align="center">
					<nav className="flex items-center gap-1">
						{ NAV_ITEMS.map( ( item ) => {
							const isActive = currentPage === item.slug;
							const href = `${ adminUrl }admin.php?page=${ item.slug }`;

							return (
								<a
									key={ item.slug }
									href={ href }
									className={ cn(
										'relative px-3 py-2 text-sm font-medium no-underline transition-colors',
										isActive
											? 'text-text-primary'
											: 'text-text-tertiary hover:text-text-primary'
									) }
								>
									{ item.label }
									{ isActive && (
										<span className="absolute bottom-0 left-3 right-3 h-0.5 bg-text-primary rounded-full" />
									) }
								</a>
							);
						} ) }
					</nav>
				</Topbar.Middle>

				<Topbar.Right>
					{ version && (
						<Badge
							label={ `v${ version }` }
							variant="neutral"
							size="xs"
						/>
					) }
				</Topbar.Right>
			</Topbar>

			<div className="p-6 lg:p-8">
				<div className="max-w-6xl mx-auto">
					{ children }
				</div>
			</div>
		</div>
	);
}
