# Action Catalog

JARVIS AI ships with 77 AI-callable actions. Each implements `Action_Interface` and is registered in the `Action_Registry` at boot. The orchestrator dispatches actions based on AI tool calls during the [tool loop](AI-Orchestrator).

## Action Interface

Every action provides:

- **`get_name()`** -- Unique slug (e.g. `create_post`)
- **`get_description()`** -- Human-readable description for the AI system prompt
- **`get_parameters()`** -- JSON Schema of accepted parameters
- **`get_capability()`** -- WordPress capability required (e.g. `edit_posts`)
- **`execute( array $params )`** -- Performs the action, returns result array

## Actions by Category

### Content Management (14)

| Slug | Description | Capability |
|------|-------------|------------|
| `create_post` | Create a new post or page | `edit_posts` |
| `edit_post` | Edit an existing post | `edit_posts` |
| `delete_post` | Delete a post (trash or force) | `delete_posts` |
| `clone_post` | Duplicate a post with its content | `edit_posts` |
| `search_posts` | Search posts by keyword, type, status | `edit_posts` |
| `insert_blocks` | Insert block markup into a post | `edit_posts` |
| `read_blocks` | Read block content from a post | `edit_posts` |
| `bulk_edit` | Bulk edit post fields | `edit_posts` |
| `bulk_find_replace` | Find and replace across post content | `edit_posts` |
| `import_content` | Import content from external sources | `edit_posts` |
| `manage_revisions` | List, compare, or restore revisions | `edit_posts` |
| `manage_comments` | Approve, trash, spam, reply to comments | `moderate_comments` |
| `manage_taxonomies` | Create/edit/delete terms and taxonomies | `manage_categories` |
| `generate_content` | AI-generated text content | `edit_posts` |

### Plugins (6)

| Slug | Description | Capability |
|------|-------------|------------|
| `install_plugin` | Install a plugin from wp.org | `install_plugins` |
| `activate_plugin` | Activate an installed plugin | `activate_plugins` |
| `deactivate_plugin` | Deactivate a plugin | `activate_plugins` |
| `delete_plugin` | Delete a plugin | `delete_plugins` |
| `update_plugin` | Update a plugin to latest version | `update_plugins` |
| `list_plugins` | List installed plugins with status | `activate_plugins` |

### Themes (3)

| Slug | Description | Capability |
|------|-------------|------------|
| `install_theme` | Install a theme from wp.org | `install_themes` |
| `search_theme` | Search the theme directory | `install_themes` |
| `manage_theme` | Activate, customize, or switch themes | `switch_themes` |

### Design and Appearance (6)

| Slug | Description | Capability |
|------|-------------|------------|
| `edit_global_styles` | Edit theme.json global styles | `edit_theme_options` |
| `edit_template_parts` | Edit template parts (header, footer) | `edit_theme_options` |
| `add_custom_css` | Add custom CSS to the site | `edit_theme_options` |
| `set_page_template` | Set a page template | `edit_posts` |
| `get_page_templates` | List available page templates | `edit_posts` |
| `manage_widgets` | Add, edit, remove widgets | `edit_theme_options` |

### Users (4)

| Slug | Description | Capability |
|------|-------------|------------|
| `create_user` | Create a new WordPress user | `create_users` |
| `list_users` | List users with filters | `list_users` |
| `manage_users` | Edit, delete, or modify users | `edit_users` |
| `manage_roles` | Create, edit, delete custom roles | `promote_users` |

### Media (3)

| Slug | Description | Capability |
|------|-------------|------------|
| `import_media` | Import media from URL | `upload_files` |
| `search_media` | Search media library | `upload_files` |
| `set_featured_image` | Set a post's featured image | `edit_posts` |

### SEO and Accessibility (3)

| Slug | Description | Capability |
|------|-------------|------------|
| `manage_seo` | Manage SEO meta, titles, descriptions | `edit_posts` |
| `generate_sitemap` | Generate XML sitemap | `manage_options` |
| `audit_accessibility` | Run accessibility audit on a page | `edit_posts` |

### Site Management (13)

| Slug | Description | Capability |
|------|-------------|------------|
| `update_settings` | Update WordPress settings | `manage_options` |
| `manage_options_bulk` | Bulk read/write wp_options | `manage_options` |
| `manage_menus` | Create, edit, delete nav menus | `edit_theme_options` |
| `manage_permalinks` | Update permalink structure | `manage_options` |
| `manage_redirects` | Create URL redirects | `manage_options` |
| `manage_rewrite_rules` | Flush or inspect rewrite rules | `manage_options` |
| `manage_cron` | View, add, remove WP-Cron events | `manage_options` |
| `manage_transients` | View, delete transients | `manage_options` |
| `manage_sessions` | View, destroy user sessions | `edit_users` |
| `manage_shortcodes` | List registered shortcodes | `edit_posts` |
| `export_site` | Export site content to WXR | `export` |
| `optimize_performance` | Run performance optimizations | `manage_options` |
| `database_optimize` | Optimize database tables | `manage_options` |

### AI and Research (5)

| Slug | Description | Capability |
|------|-------------|------------|
| `generate_image` | Generate images via DALL-E | `upload_files` |
| `web_search` | Search the web via Tavily | `edit_posts` |
| `read_url` | Read and extract content from a URL | `edit_posts` |
| `analyze_reference_site` | Analyze a reference website's design | `edit_posts` |
| `recommend_plugin` | AI-powered plugin recommendations | `install_plugins` |

### Patterns (4)

| Slug | Description | Capability |
|------|-------------|------------|
| `list_patterns` | List available block patterns | `edit_posts` |
| `get_pattern` | Get a specific pattern's markup | `edit_posts` |
| `create_pattern` | Create a reusable block pattern | `edit_posts` |
| `build_from_blueprint` | Build a full page from a blueprint | `edit_posts` |

### WooCommerce (8)

| Slug | Description | Capability |
|------|-------------|------------|
| `woo_manage_products` | CRUD for WooCommerce products | `edit_products` |
| `woo_manage_orders` | View, update, refund orders | `edit_shop_orders` |
| `woo_manage_coupons` | Create, edit, delete coupons | `edit_shop_coupons` |
| `woo_manage_categories` | Manage product categories | `manage_product_terms` |
| `woo_manage_inventory` | Bulk inventory management | `edit_products` |
| `woo_manage_shipping` | Configure shipping zones/methods | `manage_woocommerce` |
| `woo_manage_settings` | Update WooCommerce settings | `manage_woocommerce` |
| `woo_analytics` | Sales and revenue analytics | `view_woocommerce_reports` |

### System (8)

| Slug | Description | Capability |
|------|-------------|------------|
| `site_health` | Run Site Health checks | `manage_options` |
| `read_debug_log` | Read the debug.log file | `manage_options` |
| `screenshot_page` | Take a screenshot of a page | `edit_posts` |
| `manage_ab_test` | Create and manage A/B tests | `manage_options` |
| `manage_scheduled_tasks` | Create/manage scheduled task chains | `manage_options` |
| `manage_memory` | Store/retrieve cross-conversation memory | `edit_posts` |
| `undo_action` | Undo a previous action via checkpoint | `edit_posts` |

## See Also

- [AI Orchestrator](AI-Orchestrator) -- How actions are dispatched
- [REST API Reference](REST-API-Reference) -- `/action/execute` endpoint
- [Security Model](Security-Model) -- Capability enforcement
