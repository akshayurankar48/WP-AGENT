# Troubleshooting FAQ

## API Key Issues

### "Invalid API key" error

- Verify the key is correct in **JARVIS AI > Settings**
- For OpenRouter: key should start with `sk-or-`
- For Anthropic: key should start with `sk-ant-`
- For OpenAI: key should start with `sk-`
- For Google: use a Generative AI API key
- Use the **Verify** button to test without saving

### "Encryption failed" when saving API key

- Ensure `AUTH_KEY` and `AUTH_SALT` are defined in `wp-config.php`
- These constants are required for AES-256-CBC encryption
- Regenerate salts at `https://api.wordpress.org/secret-key/1.1/salt/` if missing

## Editor Sidebar

### Sidebar not showing in the editor

- Check that the plugin is activated
- Verify your user role is in **Settings > Allowed Roles** (default: administrator only)
- Click the JARVIS icon in the top toolbar or press `Cmd+J` / `Ctrl+J`
- Check browser console for JavaScript errors
- Run `npm run build` if using a development installation

### Sidebar is too narrow

The Gutenberg PluginSidebar is limited to approximately 280px by WordPress core. This is not configurable. All JARVIS components are designed for this width.

## Installation

### npm install fails with peer dependency errors

Force UI has peer dependency conflicts. Always use:

```bash
npm install --legacy-peer-deps
```

### Build fails after npm install

1. Delete `node_modules/` and `package-lock.json`
2. Run `npm install --legacy-peer-deps`
3. Run `npm run build`

## Block Markup

### AI generates invalid block markup

Block markup must follow the exact format:

```
<!-- wp:blockname {"attrs"} -->
<div>HTML content</div>
<!-- /wp:blockname -->
```

Common issues:
- Missing closing comment tag
- Attributes not valid JSON
- Nested blocks not properly wrapped

The `insert_blocks` action includes validation and autocorrection for common issues.

### AI invents CSS class names that do not exist

The `autocorrect_animation_classes()` function in `insert-blocks.php` automatically maps invented class names to valid `wpa-*` animation classes. If a class cannot be mapped, it is stripped.

## Animations

### Animations not loading on the frontend

Animation CSS/JS only loads when the post content contains `wpa-` class names. If animations are not appearing:

1. Verify the post content contains `wpa-` prefixed classes
2. Check that no caching plugin is serving a stale version
3. Clear any page cache and reload

## Rate Limiting

### "Rate limit exceeded" (429) error

- Default: 30 requests/minute, 500 requests/day per user
- Adjust in **JARVIS AI > Settings > Rate Limits**
- Rate limiting uses WordPress transients (in-memory)
- Wait for the `Retry-After` period and try again

## Tailwind CSS

### Tailwind styles not applying in the editor sidebar

Tailwind CSS does not work in the editor sidebar because it renders in a body-level portal. Use Emotion CSS (`@emotion/css`) for editor sidebar styling. Tailwind is only for admin dashboard pages.

## WooCommerce

### WooCommerce actions returning errors

- Verify WooCommerce is installed and activated
- WooCommerce actions require WooCommerce-specific capabilities (`edit_products`, `manage_woocommerce`, etc.)
- Ensure your user role has the required WooCommerce capabilities

## Database

### Tables not created on activation

- Check the PHP error log for `dbDelta()` errors
- Manually trigger migration: deactivate and reactivate the plugin
- Verify MySQL user has `CREATE TABLE` permission

## Performance

### Large system prompt increasing token costs

The system prompt includes pattern metadata (~8K+ tokens). This is by design for design-aware generation. To reduce costs:
- Use a cheaper model (GPT-4o-mini, Gemini Flash) for simple tasks
- The Model Router automatically selects cheaper models for simple queries

### Rate limiting does not work across multiple servers

The rate limiter uses WordPress transients which are in-memory per server. For multi-server deployments, use an external object cache (Redis/Memcached) to share transient data.

## See Also

- [Getting Started](Getting-Started) -- Initial setup guide
- [Environment Configuration](Environment-Configuration) -- Build and config details
- [Security Model](Security-Model) -- Authentication and rate limiting
