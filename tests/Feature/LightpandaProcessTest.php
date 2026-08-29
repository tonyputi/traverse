<?php

declare(strict_types=1);

use Tonyputi\Traverse\Exceptions\LightpandaBinaryNotFound;
use Tonyputi\Traverse\Exceptions\LightpandaProcessException;
use Tonyputi\Traverse\Lightpanda\LightpandaProcess;

it('rejects a missing Lightpanda binary before starting a process', function (): void {
    expect(fn () => (new LightpandaProcess(null, 1))->connect())
        ->toThrow(LightpandaBinaryNotFound::class, 'TRAVERSE_LIGHTPANDA_BINARY');
});

it('reports a process that exits before starting its CDP server', function (): void {
    expect(fn () => (new LightpandaProcess(PHP_BINARY, 1))->connect())
        ->toThrow(LightpandaProcessException::class, 'exited before its CDP server became available');
});
