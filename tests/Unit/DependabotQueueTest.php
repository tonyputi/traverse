<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

it('calls only the pinned shared queue with explicit caller credentials', function (): void {
    $workflow = Yaml::parseFile(__DIR__.'/../../.github/workflows/dependabot-auto-merge.yml');
    $job = $workflow['jobs']['queue'];

    expect($workflow['on'])->toBe([
        'workflow_run' => ['workflows' => ['Quality'], 'types' => ['completed']],
    ])->and($workflow['permissions'])->toBe(['contents' => 'read', 'pull-requests' => 'read'])
        ->and(array_keys($workflow['jobs']))->toBe(['queue'])
        ->and(array_keys($job))->toBe(['if', 'uses', 'with', 'secrets'])
        ->and($job['if'])->toBe("vars.DEPENDABOT_AUTO_MERGE_ENABLED == 'true'")
        ->and($job['uses'])->toMatch('/^tonyputi\/\.github\/\.github\/workflows\/queue-dependabot\.yml@[a-f0-9]{40}$/')
        ->and($job['with'])->toBe(['validation_workflow' => '.github/workflows/quality.yml'])
        ->and($job['secrets'])->toBe([
            'github_app_private_key' => '${{ secrets.DEPENDABOT_QUEUE_APP_PRIVATE_KEY }}',
        ]);
});
