<?php

declare(strict_types=1);

namespace Tonyputi\Traverse;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use LogicException;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\TerminableBrowser;
use Tonyputi\Traverse\Lightpanda\LightpandaBrowser;
use Tonyputi\Traverse\Lightpanda\LightpandaProcess;

final class BrowserManager extends Manager implements Factory
{
    public function browser(?string $driver = null): Browser
    {
        $browser = $this->driver($driver);

        if (! $browser instanceof Browser) {
            throw new LogicException(sprintf(
                'Driver [%s] must implement [%s].',
                $driver ?? $this->getDefaultDriver(),
                Browser::class,
            ));
        }

        return $browser;
    }

    public function getDefaultDriver(): string
    {
        return $this->config->get('traverse.default', 'lightpanda');
    }

    public function terminate(): void
    {
        foreach ($this->drivers as $driver) {
            if ($driver instanceof TerminableBrowser) {
                $driver->terminate();
            }
        }
    }

    protected function createLightpandaDriver(): Browser
    {
        $config = $this->config->get('traverse.drivers.lightpanda', []);

        if (! is_array($config)) {
            throw new InvalidArgumentException('Lightpanda driver configuration must be an array.');
        }

        $binary = $config['binary'] ?? null;
        $timeout = $config['timeout'] ?? 30;

        if ($binary !== null && ! is_string($binary)) {
            throw new InvalidArgumentException('Lightpanda binary configuration must be a string or null.');
        }

        if (! is_int($timeout) || $timeout < 1) {
            throw new InvalidArgumentException('Lightpanda timeout configuration must be a positive integer.');
        }

        return new LightpandaBrowser(new LightpandaProcess($binary, $timeout));
    }
}
