# Pattern Library

JARVIS AI includes a built-in library of 93 block patterns and 17 full-page blueprints across 24 categories. These are injected into the AI system prompt so the model can generate design-aware block markup.

## Overview

- **93 patterns** -- Individual sections (hero, pricing, testimonials, etc.)
- **17 blueprints** -- Full-page layouts assembled from multiple patterns
- **24 categories** -- Organized by section type
- **JSON format** -- Each pattern is a JSON file with metadata and block markup

## Categories (24)

| Category | Description |
|----------|-------------|
| `banner` | Announcement and promotional banners |
| `blog` | Blog post layouts and grids |
| `blueprints` | Full-page templates (17) |
| `comparison` | Comparison tables and layouts |
| `contact` | Contact forms and info sections |
| `content` | General content blocks |
| `cta` | Call-to-action sections |
| `dividers` | Section dividers and spacers |
| `faq` | FAQ accordions and lists |
| `features` | Feature grids and showcases |
| `footers` | Page footer layouts |
| `gallery` | Image galleries |
| `headers` | Page header sections |
| `heroes` | Hero sections with CTA |
| `logos` | Logo grids and marquees |
| `newsletter` | Email signup sections |
| `portfolio` | Portfolio showcases |
| `pricing` | Pricing tables and cards |
| `process` | Step-by-step process sections |
| `services` | Service listing layouts |
| `stats` | Statistics and counters |
| `team` | Team member showcases |
| `testimonials` | Testimonial carousels and grids |
| `video` | Video embed sections |

## JSON Format

Each pattern JSON file contains:

```json
{
  "title": "Hero with CTA",
  "slug": "hero-with-cta",
  "category": "heroes",
  "description": "Full-width hero with heading, subheading, and CTA button",
  "keywords": ["hero", "banner", "cta"],
  "content": "<!-- wp:group {\"align\":\"full\"} -->\n..."
}
```

### Key Fields

- **`title`** -- Display name
- **`slug`** -- Unique identifier
- **`category`** -- Category directory name
- **`content`** -- Full WordPress block markup
- **`keywords`** -- Search terms for pattern matching

## Variable Substitution

Patterns support placeholder variables that the AI replaces with contextual content:

- `{{site_name}}` -- Replaced with the site title
- `{{brand_color}}` -- Replaced with configured brand color
- `{{tagline}}` -- Replaced with site or brand tagline

## Theme Token Resolution

The Pattern Manager resolves theme.json design tokens in patterns:

- Colors reference `var(--wp--preset--color--primary)` etc.
- Spacing uses `var(--wp--preset--spacing--50)` etc.
- Typography follows `var(--wp--preset--font-size--large)` etc.

This ensures patterns adapt to the active theme's design system.

## Blueprints (17)

Blueprints are full-page layouts assembled from multiple patterns. They serve as starting points for the AI when building complete pages.

Examples include: landing pages, about pages, service pages, portfolio pages, blog layouts, pricing pages, contact pages, and more.

## AI Integration

The Prompt Builder includes pattern metadata in the system prompt:

1. Pattern slugs and descriptions are listed so the AI knows what is available
2. When the AI needs a section, it can reference a pattern by slug via `get_pattern`
3. The AI can also `build_from_blueprint` for full-page generation
4. Custom patterns created via `create_pattern` are also available

## Actions

| Action | Description |
|--------|-------------|
| `list_patterns` | List all available patterns with filters |
| `get_pattern` | Retrieve a specific pattern's block markup |
| `create_pattern` | Create a new reusable pattern |
| `build_from_blueprint` | Generate a full page from a blueprint |

## See Also

- [Action Catalog](Action-Catalog) -- Pattern-related actions
- [AI Orchestrator](AI-Orchestrator) -- How patterns are used in generation
- [Architecture Overview](Architecture-Overview) -- Pattern layer in the stack
