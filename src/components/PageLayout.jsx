import { Topbar, Badge, Toaster } from '@bsf/force-ui';
import { Bot } from 'lucide-react';
import { cn } from '../utils/cn';

const { adminUrl, currentPage, version } = window.wpAgentData || {};

const NAV_ITEMS = [
	{ label: 'Dashboard', slug: 'wp-agent' },
	{ label: 'History', slug: 'wp-agent-history' },
	{ label: 'Schedules', slug: 'wp-agent-schedules' },
	{ label: 'Settings', slug: 'wp-agent-settings' },
	{ label: 'Capabilities', slug: 'wp-agent-capabilities' },
	{ label: 'Help', slug: 'wp-agent-help' },
];

export default function PageLayout( { children } ) {
	return (
		<div className="bg-background-secondary min-h-screen">
			<Toaster position="top-right" />

			<Topbar className="border-b border-solid border-border-subtle bg-background-primary px-4">
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
										'px-3 py-2 text-sm font-medium rounded-md no-underline transition-colors',
										isActive
											? 'text-text-primary bg-background-secondary'
											: 'text-text-secondary hover:text-text-primary hover:bg-background-secondary'
									) }
								>
									{ item.label }
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

			<div className="p-5 sm:p-6 xl:p-8">
				{ children }
			</div>
		</div>
	);
}
