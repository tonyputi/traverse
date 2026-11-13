<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('keeps the privileged queue dormant and metadata-only', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/dependabot-auto-merge.yml');
    $job = $workflow['jobs']['queue'];

    expect($workflow['on'])->toBe([
        'workflow_run' => ['workflows' => ['Quality'], 'types' => ['completed']],
    ])->and($workflow['permissions'])->toBe(['contents' => 'read', 'pull-requests' => 'read'])
        ->and($job['if'])->toContain("vars.DEPENDABOT_AUTO_MERGE_ENABLED == 'true'")
        ->toContain("github.repository == 'tonyputi/traverse'")
        ->toContain("github.event.workflow_run.event == 'pull_request'")
        ->toContain("github.event.workflow_run.conclusion == 'success'")
        ->toContain("github.event.workflow_run.head_repository.full_name == 'tonyputi/traverse'");

    $actions = array_values(array_filter(array_column($job['steps'], 'uses')));
    expect($actions)->toHaveCount(1)
        ->and($actions[0])->toMatch('/^actions\/create-github-app-token@[a-f0-9]{40}$/')
        ->and($job['steps'][1]['if'])->toBe("steps.metadata.outputs.number != ''")
        ->and($job['steps'][1]['with']['repositories'])->toBe('traverse')
        ->and($job['steps'][1]['with']['permission-contents'])->toBe('write')
        ->and($job['steps'][1]['with']['permission-pull-requests'])->toBe('write')
        ->and($job['steps'][2]['if'])->toBe("steps.metadata.outputs.number != ''")
        ->and($job['steps'][2]['run'])->toContain('--auto --squash', '--match-head-commit "$HEAD_SHA"')
        ->not->toContain('--admin', '--approve');
});

it('queues only current same-repository Dependabot metadata', function (array $changes, bool $eligible, int $copies = 1, string $enabled = 'true'): void {
    $sha = str_repeat('a', 40);
    $pr = array_replace_recursive([
        'number' => 42,
        'state' => 'open',
        'draft' => false,
        'user' => ['login' => 'dependabot[bot]', 'id' => 49699333, 'type' => 'Bot'],
        'base' => ['ref' => 'main', 'repo' => ['full_name' => 'tonyputi/traverse']],
        'head' => [
            'sha' => $sha,
            'ref' => 'dependabot/composer/example',
            'repo' => ['full_name' => 'tonyputi/traverse'],
        ],
    ], $changes);
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/dependabot-auto-merge.yml');
    $output = tempnam(sys_get_temp_dir(), 'traverse-queue-');

    try {
        $process = new Process(['bash', '-euo', 'pipefail', '-c',
            'gh() { printf \'%s\' "$PR_RESPONSE"; }'."\n".$workflow['jobs']['queue']['steps'][0]['run'],
        ], env: [
            'PR_RESPONSE' => json_encode(array_fill(0, $copies, $pr), JSON_THROW_ON_ERROR),
            'QUEUE_ENABLED' => $enabled,
            'HEAD_SHA' => $sha,
            'HEAD_BRANCH' => 'dependabot/composer/example',
            'GITHUB_OUTPUT' => $output,
        ]);
        $process->mustRun();

        expect(file_get_contents($output))->toBe($eligible ? "number=42\n" : '');
    } finally {
        unlink($output);
    }
})->with([
    'Dependabot' => [[], true],
    'absent activation' => [[], false, 1, ''],
    'disabled activation' => [[], false, 1, 'false'],
    'uppercase activation' => [[], false, 1, 'TRUE'],
    'numeric activation' => [[], false, 1, '1'],
    'human' => [['user' => ['login' => 'tonyputi', 'type' => 'User']], false],
    'other bot' => [['user' => ['id' => 1]], false],
    'not a bot' => [['user' => ['type' => 'User']], false],
    'fork' => [['head' => ['repo' => ['full_name' => 'attacker/traverse']]], false],
    'other base repository' => [['base' => ['repo' => ['full_name' => 'attacker/traverse']]], false],
    'other base branch' => [['base' => ['ref' => 'develop']], false],
    'new commit' => [['head' => ['sha' => str_repeat('b', 40)]], false],
    'other head branch' => [['head' => ['ref' => 'dependabot/composer/other']], false],
    'closed' => [['state' => 'closed'], false],
    'draft' => [['draft' => true], false],
    'missing head repository' => [['head' => ['repo' => null]], false],
    'invalid PR number' => [['number' => "42\ninjected=true"], false],
    'no matching PR' => [[], false, 0],
    'ambiguous PRs' => [[], false, 2],
]);
