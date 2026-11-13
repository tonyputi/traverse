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
- Use Conventional Commit messages: `feat:`, `fix:`, `docs:`, `refactor:`, or `chore:`. Semantic Release uses `feat:` and `fix:` commits on `main` to determine package versions.
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

## Protected PRs and dependency updates

Repository settings and the active `main` ruleset are managed in
[Terraform](https://github.com/tonyputi/terraform/pull/111).
Changes require one human CODEOWNER approval from `@tonyputi`, passing up-to-date
Quality checks, and resolved review threads. New commits dismiss stale approvals;
no bypass actors are configured. A PR author cannot approve their own PR.

Required checks from GitHub Actions App `15368` are `Validate Composer`, `Lint`,
`Analyse`, `Test (PHP 8.3)` and `Test (PHP 8.4)`.
Quality runs on PRs without path filters, secrets or write permissions.
Release runs after merge and must not be required as a PR check. It publishes
tags/releases without writing commits back to the protected branch.

Dependabot checks Composer and Actions weekly, with a five-day cooldown and at
most five open version-update PRs per ecosystem. Minor/patch updates are grouped;
major and security updates remain individual. Composer production updates use
`fix(deps)`; development dependencies and Actions use `chore` conventions.

Native auto-merge is allowed but must be enabled separately on each PR by a
maintainer. GitHub then waits for the required reviews and checks. There is no
automatic queue, reusable merge workflow, dedicated merge App or activation flag.
Bots and AI must not replace human approval.

## Language

Repository-facing content—including issues, pull requests, commit messages, code
comments, and documentation—must be written in English.
