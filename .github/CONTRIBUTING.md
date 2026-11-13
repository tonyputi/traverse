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

`main` is the policy target. `.github/CODEOWNERS` assigns every file, including
CODEOWNERS itself and all automation, to the human maintainer `@tonyputi`.
Repository settings and the active ruleset are managed in
[Terraform](https://github.com/tonyputi/terraform/issues/109), not by workflows here.
The ruleset must require a PR, one CODEOWNER approval, dismissal of stale approvals
on new commits, resolved review threads, and no bypass actors. Bots and AI must
never approve PRs. An operator-authored PR cannot be self-approved: use a separate
App identity for agent-authored PRs, or agree another eligible human CODEOWNER.

Quality runs on every PR, without path filters, secrets, or write permissions,
including Dependabot and fork PRs. Require these exact contexts from the
`github-actions` App (integration ID `15368`):

- `Validate Composer`
- `Lint`
- `Analyse`
- `Test (PHP 8.3)`
- `Test (PHP 8.4)`

Do not require `release`, Dependabot's internal checks, or the auto-merge workflow.
Release runs after a merge to `main`; `.releaserc` uses only commit analysis,
release notes, and GitHub publication. It does not use `@semantic-release/git` or
write release commits back to `main`. No release-bot protection bypass is needed.

Dependabot checks Composer and Actions weekly, with a five-day version-update
cooldown and at most five open version-update PRs per ecosystem. Minor/patch
version updates are grouped; majors and security updates remain individual.
Production Composer updates use `fix(deps)` so they can trigger a package release;
development dependencies and Actions use `chore` conventions. Cooldown does not
delay security updates, which have GitHub's separate ten-PR limit.

### Dormant auto-merge queue

The queue runs only when the Actions repository variable
`DEPENDABOT_AUTO_MERGE_ENABLED` equals the exact string `true`. Absent or any other
value means disabled. Terraform enabling native auto-merge does **not** activate
this workflow. Ordinary PRs remain explicit maintainer opt-in.

Traverse keeps only a small `workflow_run` caller pinned to a full commit SHA of
[`tonyputi/.github`'s shared queue](https://github.com/tonyputi/.github#queue-dependabot).
Detailed metadata verification, exact activation, security checks and merge behavior
belong there, not in each consumer. Its only input is
`validation_workflow: .github/workflows/quality.yml`; repository, owner, default
branch and variables come from Traverse's caller context.

The caller grants Contents read + Pull requests read and explicitly maps its
`DEPENDABOT_QUEUE_APP_PRIVATE_KEY` secret to the shared workflow_call secret
`github_app_private_key`. It does not use `secrets: inherit` or shared-repository
secrets. Configure the caller variable `DEPENDABOT_QUEUE_APP_CLIENT_ID` separately.
Missing client ID or private key safely skips before API/token/merge operations.
The dedicated App must be installed only on Traverse with Contents/Pull requests
write, implicit Metadata read and no admin/bypass rights. There is no broad PAT or
`GITHUB_TOKEN` merge fallback: the App preserves the existing release push trigger.
Required checks, human CODEOWNER approval and stale dismissal remain independent
gates; bot/AI reviews never replace human approval.

Safe rollout order:

1. Human-review/merge the [shared PR](https://github.com/tonyputi/.github/pull/18)
   first. Confirm the caller's pin is the final reviewed/merged shared revision;
   update it if squash changes the SHA. An unmerged feature pin is not final evidence.
2. Independently verify the activation variable is absent/false, then land the
   reviewed Traverse caller with all five Quality checks green. The existing 403
   when inspecting variables/secrets makes independent confirmation mandatory.
3. Review the Terraform PR, authorize its normal CI apply separately, and verify
   effective `main` protections, required check App IDs, no bypasses, and native
   auto-merge. A plan or `allow_auto_merge` alone is not evidence of protection.
4. Complete the credential and negative/positive gate checks in
   [#28](https://github.com/tonyputi/traverse/issues/28), coordinated with
   [Terraform #110](https://github.com/tonyputi/terraform/issues/110). Only then authorize setting
   the activation variable to `true`. Rerun Quality to queue an existing eligible
   Dependabot PR; verify approval, auto-merge, and the subsequent Release run.

The flag is an operator-controlled rollout latch, not a live protection monitor.
Before weakening protections, disable it **and** cancel pending native auto-merge
requests; disabling the workflow does not cancel already queued PRs. No live
settings changes, applies, merges, or credential provisioning are implicit here.

## Language

Repository-facing content—including issues, pull requests, commit messages, code
comments, and documentation—must be written in English.
