<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tonyputi\Traverse\Contracts\Factory;

it('reads native Lightpanda page primitives from deterministic fixtures', function (): void {
    $binary = getenv('TRAVERSE_LIGHTPANDA_BINARY');

    if (! is_string($binary) || ! is_executable($binary)) {
        $this->markTestSkipped('Set TRAVERSE_LIGHTPANDA_BINARY to run the Lightpanda integration test.');
    }

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

    if ($socket === false) {
        throw new RuntimeException(sprintf('Could not allocate a fixture server port: %s', $errorMessage));
    }

    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr((string) $address, strrpos((string) $address, ':') + 1);
    $server = new Process([PHP_BINARY, '-S', sprintf('127.0.0.1:%d', $port), '-t', __DIR__.'/../Fixtures/pages']);
    $server->setTimeout(null);
    $server->start();

    try {
        usleep(100_000);

        config()->set('traverse.drivers.lightpanda.binary', $binary);
        $browser = app(Factory::class)->browser('lightpanda');
        $forms = $browser->visit(sprintf('http://127.0.0.1:%d/forms.html', $port));
        $javascript = $browser->visit(sprintf('http://127.0.0.1:%d/javascript-rendered.html', $port));
        $structuredData = $browser->visit(sprintf('http://127.0.0.1:%d/structured-data.html', $port));

        expect($forms->markdown())->toContain('# Forms fixture')
            ->and($forms->semanticTree())->not->toBeEmpty()
            ->and($forms->interactiveElements())->toHaveCount(6)
            ->and($javascript->markdown())->toContain('JavaScript rendered fixture content.')
            ->and($structuredData->structuredData()['jsonLd'][0] ?? null)->not->toBeNull();
    } finally {
        $server->stop(1);
    }
});
