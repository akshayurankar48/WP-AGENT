/**
 * Prompt Gallery — Curated prompt template browser.
 *
 * Displays a filterable gallery of pre-built prompts organized by category.
 * Can be used in the Dashboard (horizontal slider) and editor WelcomeScreen.
 *
 * @package
 * @since 1.2.0
 */

import { useState, useMemo } from '@wordpress/element';
import {
	Rocket,
	Cpu,
	ShoppingBag,
	Image,
	FileText,
	UtensilsCrossed,
	Briefcase,
	Zap,
	Sparkles,
	ArrowRight,
	LayoutGrid,
} from 'lucide-react';
import CardShell from './ui/CardShell';
import SectionHeader from './ui/SectionHeader';
import HorizontalSlider from './ui/HorizontalSlider';
import templateData from '../data/prompt-templates.json';

/* ── Icon Map ──────────────────────────────────────────────────── */

const ICON_MAP = {
	Rocket,
	Cpu,
	ShoppingBag,
	Image,
	FileText,
	UtensilsCrossed,
	Briefcase,
	Zap,
};

/* ── Template Card ─────────────────────────────────────────────── */

function TemplateCard( { template, onSelect, fixedWidth } ) {
	return (
		<button
			type="button"
			onClick={ () => onSelect( template.prompt ) }
			className={ `group flex flex-col gap-2 p-4 rounded-lg border border-solid border-border-subtle bg-background-primary hover:shadow-sm hover:border-border-interactive transition-all duration-200 cursor-pointer text-left snap-start ${
				fixedWidth ? 'w-[260px] shrink-0' : 'w-full'
			}` }
		>
			<div className="flex items-center justify-between">
				<h3 className="text-sm font-medium text-text-primary group-hover:text-text-primary transition-colors duration-150">
					{ template.title }
				</h3>
				<ArrowRight className="size-3.5 text-text-tertiary opacity-0 group-hover:opacity-100 transition-opacity duration-200 shrink-0" />
			</div>
			<p className="text-xs text-text-tertiary leading-relaxed line-clamp-2">
				{ template.description }
			</p>
			<div className="flex items-center gap-1.5 mt-auto">
				{ template.tags.map( ( tag ) => (
					<span
						key={ tag }
						className="inline-flex px-1.5 py-0.5 rounded text-[9px] font-medium bg-background-secondary text-text-secondary"
					>
						{ tag }
					</span>
				) ) }
			</div>
		</button>
	);
}

/* ── Main Component ────────────────────────────────────────────── */

/**
 * PromptGallery component.
 *
 * @param {Object}   props          Component props.
 * @param {Function} props.onSelect Callback when a template prompt is selected.
 * @param {boolean}  props.compact  If true, shows a condensed version (for editor sidebar).
 * @param {number}   props.limit    Max number of templates to show before "View all".
 */
export default function PromptGallery( { onSelect, compact = false, limit = 0 } ) {
	const [ activeCategory, setActiveCategory ] = useState( 'all' );

	const { categories, templates } = templateData;

	const filtered = useMemo( () => {
		if ( activeCategory === 'all' ) {
			return templates;
		}
		return templates.filter( ( t ) => t.category === activeCategory );
	}, [ activeCategory, templates ] );

	const useSlider = limit > 0 && ! compact;
	const displayed = useSlider ? filtered : filtered;

	const capabilitiesUrl = ( window.jarvisAiData?.adminUrl || '' ) + 'admin.php?page=jarvis-ai-capabilities';

	return (
		<div className={ compact ? '' : '' }>
			<CardShell className={ compact ? 'p-0 border-0' : 'p-5' } hover={ false }>
				{ ! compact && (
					<SectionHeader
						icon={ LayoutGrid }
						title="Prompt Templates"
						badge={ `${ templates.length }` }
						actions={
							limit > 0
								? <a href={ capabilitiesUrl } className="text-xs font-medium text-text-tertiary no-underline hover:text-text-primary transition-colors">View all &rarr;</a>
								: null
						}
					/>
				) }

				{ /* Category filter pills */ }
				<div className="flex items-center gap-1.5 mb-4 overflow-x-auto pb-1 scrollbar-none">
					<button
						type="button"
						onClick={ () => setActiveCategory( 'all' ) }
						className={ `flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150 border border-solid shrink-0 cursor-pointer ${
							activeCategory === 'all'
								? 'bg-background-primary text-text-primary shadow-sm border-border-interactive'
								: 'bg-transparent text-text-tertiary border-border-subtle hover:text-text-secondary hover:border-border-interactive'
						}` }
					>
						<Sparkles className="size-3" />
						All
					</button>
					{ categories.map( ( cat ) => {
						const CatIcon = ICON_MAP[ cat.icon ] || Zap;
						const isActive = activeCategory === cat.id;
						return (
							<button
								key={ cat.id }
								type="button"
								onClick={ () => setActiveCategory( cat.id ) }
								className={ `flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-all duration-150 border border-solid shrink-0 cursor-pointer ${
									isActive
										? 'bg-background-primary text-text-primary shadow-sm border-border-interactive'
										: 'bg-transparent text-text-tertiary border-border-subtle hover:text-text-secondary hover:border-border-interactive'
								}` }
							>
								<CatIcon className="size-3" />
								{ cat.label }
							</button>
						);
					} ) }
				</div>

				{ /* Template display */ }
				{ useSlider ? (
					<HorizontalSlider>
						{ displayed.map( ( template ) => (
							<TemplateCard
								key={ template.id }
								template={ template }
								onSelect={ onSelect }
								fixedWidth
							/>
						) ) }
					</HorizontalSlider>
				) : (
					<div className={ `grid gap-3 ${ compact ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' }` }>
						{ displayed.map( ( template ) => (
							<TemplateCard
								key={ template.id }
								template={ template }
								onSelect={ onSelect }
								fixedWidth={ false }
							/>
						) ) }
					</div>
				) }
			</CardShell>
		</div>
	);
}
