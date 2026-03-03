# Testing Guide

JARVIS AI uses PHPUnit for PHP tests, Jest for JavaScript tests, and multiple linting tools for code quality.

## PHP Testing

### PHPUnit

```bash
composer test
```

- **Framework:** PHPUnit 9.6
- **Config:** `phpunit.xml.dist`
- **Bootstrap:** Loads WordPress test suite
- **Test files:** `tests/php/` directory

### PHPCS (WordPress Coding Standard)

```bash
composer lint          # Check for violations
composer format        # Auto-fix violations
```

- **Standard:** WordPress (includes WordPress-Core, WordPress-Docs, WordPress-Extra)
- **Config:** `phpcs.xml.dist`
- **Key rules:** Tabs for indentation, Yoda conditions, proper WPCS naming

### PHPStan

```bash
composer phpstan
```

- Static analysis for type errors and logic bugs
- Configuration in `phpstan.neon` or `phpstan.neon.dist`

## JavaScript Testing

### Jest (via wp-scripts)

```bash
npm run test:unit
```

- **Framework:** Jest (bundled with `@wordpress/scripts`)
- **Test files:** `src/**/*.test.js` or `tests/js/` directory

### ESLint

```bash
npm run lint-js        # Check for violations
npm run lint-js:fix    # Auto-fix violations
```

- Uses `@wordpress/eslint-plugin` configuration
- Enforces WordPress JavaScript coding standards

### Stylelint

```bash
npm run lint-css
```

- Checks CSS files for style violations
- Uses `@wordpress/stylelint-config`

### Prettier

```bash
npm run pretty:fix
```

- Auto-formats JavaScript, CSS, and JSON files
- Uses `@wordpress/prettier-config`

## End-to-End Testing

```bash
npm run test:e2e
```

- Uses `@wordpress/e2e-test-utils` (Puppeteer-based)
- Tests full user flows in a browser environment
- Requires a running WordPress instance

## CI Pipeline

GitHub Actions workflow (`.github/workflows/code-analysis.yml`) runs on every push and PR:

1. **PHPCS** -- WordPress coding standard check
2. **PHPStan** -- Static analysis
3. **PHPUnit** -- PHP unit tests
4. **ESLint** -- JavaScript lint check
5. **Stylelint** -- CSS lint check

## Writing Tests

### PHP Test Example

```php
class Test_Create_Post_Action extends WP_UnitTestCase {
    public function test_creates_post_with_title() {
        $action = new \JarvisAI\Actions\Create_Post();
        $result = $action->execute( [
            'title'   => 'Test Post',
            'content' => 'Hello world',
            'status'  => 'draft',
        ] );

        $this->assertTrue( $result['success'] );
        $this->assertNotEmpty( $result['data']['post_id'] );
    }
}
```

### JavaScript Test Example

```js
import reducer from '../store/reducer';
import { SET_MESSAGES } from '../store/constants';

describe( 'reducer', () => {
    it( 'handles SET_MESSAGES', () => {
        const messages = [ { id: 1, content: 'Hello' } ];
        const state = reducer( undefined, { type: SET_MESSAGES, messages } );
        expect( state.messages ).toEqual( messages );
    } );
} );
```

## See Also

- [Contributing Guide](Contributing-Guide) -- Code standards for contributions
- [Environment Configuration](Environment-Configuration) -- Build commands
- [Deployment Guide](Deployment-Guide) -- Release process
