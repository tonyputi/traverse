<?php

declare(strict_types=1);

namespace Tonyputi\Traverse;

use Illuminate\Support\Manager;
use InvalidArgumentException;
use LogicException;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;

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
        return $this->config->get('traverse.driver', 'lightpanda');
    }

    protected function createDriver($driver)
    {
        if ($driver === 'lightpanda' && ! array_key_exists($driver, $this->customCreators)) {
            throw new InvalidArgumentException('Driver [lightpanda] is not available yet.');
        }

        return parent::createDriver($driver);
    }
}
