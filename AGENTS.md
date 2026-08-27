# Traverse contributor instructions

## Product

Traverse is an AI-native, provider-independent web browsing layer for Laravel. Its first planned driver is Lightpanda. It is not a replacement for Dusk, Playwright, Puppeteer, generic scraping, or full browser automation.

Preserve a small Laravel-first public API and compact, token-efficient representations for agent use. Do not emulate browser capabilities a driver does not natively provide.

## Workflow

- Work from a GitHub issue: one issue, one short-lived branch, and one focused PR.
- Name branches by concern, such as `feat/lightpanda-driver` or `chore/package-bootstrap`.
- Use Conventional Commit messages; `feat:` and `fix:` drive semantic releases.
- Do not merge PRs, create release tags, or close issues autonomously.
- Keep all repository-facing content in English.
- Before material Laravel or Lightpanda decisions, consult their current official documentation.
- Do not invent or document public APIs before their behavior exists.
- Add tests and documentation with every observable behavior.

## Implementation

- Prefer Laravel conventions, dependency injection, and explicit capability boundaries.
- Keep transports such as CLI, MCP, and CDP below the primary browser abstraction.
- Avoid speculative abstractions, aliases, and compatibility layers.
- Use Pest for tests.

## Quality checks

Run the relevant checks before opening a PR:

```bash
composer validate --strict
composer lint
composer test
```
