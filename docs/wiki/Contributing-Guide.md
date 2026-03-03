# Contributing Guide

## Branch Naming

```
feat/short-description     # New features
fix/short-description      # Bug fixes
refactor/short-description # Code refactoring
docs/short-description     # Documentation
test/short-description     # Tests
chore/short-description    # Maintenance
```

## Commit Message Format

```
<type>: <description>

<optional body>
```

**Types:** `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `ci`

Examples:

```
feat: add voice input to editor sidebar
fix: prevent duplicate conversation creation on rapid clicks
refactor: extract SSE parsing into reusable utility
```

## Code Standards

### PHP

- **Standard:** WordPress Coding Standards (WPCS) via PHPCS
- **Indentation:** Tabs (not spaces)
- **Yoda conditions:** `if ( 'value' === $var )` (constant on left)
- **File naming:** `kebab-case.php` (NOT `class-` prefixed)
- **Namespace:** `JarvisAI\` with PSR-4 autoloading
- **Prefix:** `jarvis_ai_` for functions, hooks, options, meta keys
- **Doc comments:** PHPDoc on all methods with `@since`, `@param`, `@return`
- **i18n:** All user-facing strings use `__()` / `_e()` with `'jarvis-ai'` text domain
- **Security:** Sanitize all input, escape all output, nonces on forms, capabilities on actions

Run before committing:

```bash
composer lint      # Check
composer format    # Auto-fix
```

### JavaScript

- **Standard:** `@wordpress/eslint-plugin`
- **Build:** `@wordpress/scripts` (webpack)
- **State:** `@wordpress/data` (not raw Redux)
- **Imports:** Prefer `@wordpress/*` packages over raw npm equivalents
- **Styling:** Emotion CSS in editor, Tailwind in admin (never mix)

Run before committing:

```bash
npm run lint-js        # Check
npm run lint-js:fix    # Auto-fix
npm run lint-css       # Check CSS
npm run pretty:fix     # Format
```

## File Naming

- PHP: `kebab-case.php` -- e.g., `ai-client-adapter.php`, `model-router.php`
- JS/JSX: `PascalCase.jsx` for components, `camelCase.js` for utilities
- CSS: `kebab-case.css`
- JSON patterns: `kebab-case.json`

## i18n Requirements

Every user-facing string must be translatable:

```php
// PHP
__( 'Settings saved.', 'jarvis-ai' )
_e( 'Enter your API key', 'jarvis-ai' )
esc_html__( 'Error occurred', 'jarvis-ai' )

// Add translator comments for ambiguous strings
/* translators: %s: model name */
sprintf( __( 'Using %s model', 'jarvis-ai' ), $model )
```

```js
// JavaScript
import { __ } from '@wordpress/i18n';
__( 'Send message', 'jarvis-ai' );
```

## Adding a New Action

1. Create `actions/your-action.php`
2. Implement `Action_Interface` with all required methods
3. The autoloader registers it automatically via `Action_Registry`
4. Add a PHPUnit test in `tests/php/`

## Pull Request Process

1. Create a feature branch from `main`
2. Make changes following the coding standards above
3. Run all linters and tests
4. Push and open a PR against `main`
5. PR description should include: summary, test plan, screenshots (if UI)

## See Also

- [Testing Guide](Testing-Guide) -- Running tests
- [Architecture Overview](Architecture-Overview) -- Understanding the codebase
- [Action Catalog](Action-Catalog) -- Existing action reference
