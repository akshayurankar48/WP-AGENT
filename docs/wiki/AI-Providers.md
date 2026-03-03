# AI Providers

JARVIS AI uses a BYOK (Bring Your Own Key) model. Users provide their own API keys for one or more providers. Keys are encrypted at rest and never sent anywhere except the chosen provider.

## Supported Providers

### OpenRouter (Default)

- **File:** `ai/open-router-client.php`
- **Endpoint:** `https://openrouter.ai/api/v1/chat/completions`
- **Models:** 100+ models including GPT-4o, Claude Sonnet/Opus, Gemini, DeepSeek, Llama, Mistral
- **Advantage:** Single API key for all models, unified billing
- **Setting:** `jarvis_ai_openrouter_api_key`

### Direct Providers

Handled by `ai/ai-client-adapter.php`, which normalizes the request/response format across providers.

| Provider | Models | Endpoint |
|----------|--------|----------|
| **Anthropic** | Claude Sonnet, Haiku, Opus | `https://api.anthropic.com/v1/messages` |
| **OpenAI** | GPT-4o, GPT-4o-mini | `https://api.openai.com/v1/chat/completions` |
| **Google** | Gemini 2.0 Flash | `https://generativelanguage.googleapis.com/v1beta/` |

## Backend Selection

The `jarvis_ai_ai_backend` option determines routing:

- **`openrouter`** -- All requests go through OpenRouter
- **`providers`** -- Requests route to direct providers based on model selection and `preferred_provider` order

## API Key Encryption

All API keys are encrypted before storage using AES-256-CBC:

1. **Encryption key** derived from WordPress `AUTH_KEY` constant
2. **IV** derived from `AUTH_SALT` constant
3. **Method:** `openssl_encrypt()` with `aes-256-cbc`
4. Encrypted value stored as base64 in `wp_options`
5. Decrypted on-the-fly when making API calls

Keys are never logged, never included in error messages, and never sent to any endpoint other than the provider API.

## Model Router

The Model Router (`ai/model-router.php`) selects the optimal model based on task complexity.

### Three Tiers

| Tier | Use Case | Example Models |
|------|----------|---------------|
| **FAST** | Simple queries, quick lookups | GPT-4o-mini, Gemini Flash, Haiku |
| **BALANCED** | Most tasks (default) | GPT-4o, Claude Sonnet |
| **POWERFUL** | Complex multi-step, full-page builds | Claude Opus, GPT-4 Turbo |

### Complexity Scoring

The router analyzes the user's message to determine complexity:

- **Token count** of the request
- **Action count** -- messages requiring multiple actions score higher
- **Keywords** -- "build full page", "redesign", "migrate" increase complexity
- **Conversation length** -- longer conversations may need more capable models

### Fallback Chain

If a model fails, the router tries the next model in the tier, then falls back to lower tiers:

```
POWERFUL -> BALANCED -> FAST
```

For direct providers, the fallback follows `preferred_provider` order (default: Anthropic -> OpenAI -> Google).

## Provider Verification

The `/verify-provider` endpoint validates an API key without saving it by making a minimal test request to the provider.

## See Also

- [AI Orchestrator](AI-Orchestrator) -- How the orchestrator uses providers
- [Environment Configuration](Environment-Configuration) -- API key options
- [Security Model](Security-Model) -- Encryption details
