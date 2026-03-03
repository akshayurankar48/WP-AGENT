# JARVIS AI Wiki

JARVIS AI is an autonomous AI-powered admin assistant for WordPress. It operates your site from natural language -- planning multi-step tasks, confirming destructive actions, and executing them from a sidebar chat in the Gutenberg editor.

**77 actions** | **93 patterns + 17 blueprints** | **Multi-provider AI** | **Streaming SSE** | **6 custom DB tables**

---

## Getting Started

- [Getting Started](Getting-Started) -- Requirements, installation, first setup
- [Environment Configuration](Environment-Configuration) -- Dev setup, build commands, wp_options

## Architecture

- [Architecture Overview](Architecture-Overview) -- Layers, request flow, directory structure
- [Database Schema](Database-Schema) -- 6 custom tables, columns, indexes, migrations
- [Security Model](Security-Model) -- Encryption, nonces, capabilities, rate limiting

## AI System

- [AI Orchestrator](AI-Orchestrator) -- Message lifecycle, tool loop, retry logic, streaming
- [AI Providers](AI-Providers) -- OpenRouter, Anthropic, OpenAI, Google, model routing
- [Action Catalog](Action-Catalog) -- All 77 AI-callable actions by category

## Patterns

- [Pattern Library](Pattern-Library) -- 93 patterns, 17 blueprints, 24 categories, JSON format

## REST API

- [REST API Reference](REST-API-Reference) -- All 17 endpoints with parameters and responses

## Frontend

- [Frontend Architecture](Frontend-Architecture) -- 3 entry points, build system, styling
- [Redux Store](Redux-Store) -- State shape, actions, thunks, selectors
- [Editor Sidebar](Editor-Sidebar) -- Chat panel, components, keyboard shortcuts
- [Admin Dashboard](Admin-Dashboard) -- Pages, layout, Force UI + Tailwind

## WordPress Integration

- [WordPress Hooks and Filters](WordPress-Hooks-and-Filters) -- Key hooks used by the plugin

## Development

- [Testing Guide](Testing-Guide) -- PHPUnit, Jest, E2E, linting, static analysis
- [Deployment Guide](Deployment-Guide) -- Build, release ZIP, version bumps
- [Contributing Guide](Contributing-Guide) -- Branch naming, commits, code standards, PRs
- [Troubleshooting FAQ](Troubleshooting-FAQ) -- Common issues and fixes

## Reference

- [Changelog](Changelog) -- Release history
