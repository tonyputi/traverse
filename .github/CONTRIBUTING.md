# Contributing to Traverse

Thank you for contributing to Traverse. It is a Laravel package for AI-native,
provider-independent web browsing. The project deliberately avoids becoming a generic
browser automation framework or a wrapper around every browser transport.

## Before you start

1. Read the [README](../README.md) to understand the project status and roadmap.
2. Read [AGENTS.md](../AGENTS.md) for the repository-wide engineering constraints.
3. Find an existing issue or open one before beginning implementation.

## Workflow

Traverse uses trunk-based development: `main` is the only long-lived branch.

- Create one short-lived branch and one focused pull request per issue.
- Use a kebab-case branch named `{type}/{slug}`, such as `feature/lightpanda-driver`,
  `bugfix/process-timeout`, or `chore/tooling`.
- Use Conventional Commit messages: `feat:`, `fix:`, `docs:`, `refactor:`, or `chore:`.
- Keep tests and documentation with observable behavior changes.
- Do not introduce speculative public APIs or browser capabilities that a driver does
  not provide natively.

Agents may implement issues and open pull requests. A maintainer reviews and merges them;
agents must not merge pull requests or close issues autonomously.

## Testing and quality

Traverse uses Pest and Orchestra Testbench. Before opening a pull request, run:

```bash
composer validate --strict
composer lint
composer test
```

Use `composer format` to apply Pint fixes. Add regression coverage for bugs and package
integration coverage for Laravel-facing behavior.

## Language

Repository-facing content—including issues, pull requests, commit messages, code
comments, and documentation—must be written in English.
