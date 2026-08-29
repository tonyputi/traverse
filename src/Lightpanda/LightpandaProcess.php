<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Lightpanda;

use Symfony\Component\Process\Process;
use Tonyputi\Traverse\Exceptions\LightpandaBinaryNotFound;
use Tonyputi\Traverse\Exceptions\LightpandaProcessException;
use Tonyputi\Traverse\Exceptions\LightpandaTimeoutException;

final class LightpandaProcess
{
    private ?int $port = null;

    private ?Process $process = null;

    public function __construct(
        private readonly ?string $binary,
        private readonly int $timeout,
    ) {}

    public function connect(): CdpConnection
    {
        $this->start();

        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            try {
                return CdpConnection::open($this->endpoint(), 1);
            } catch (LightpandaProcessException|LightpandaTimeoutException) {
                if (! $this->process?->isRunning()) {
                    throw $this->processFailed();
                }

                usleep(50_000);
            }
        }

        throw new LightpandaTimeoutException(sprintf('Lightpanda did not start within %d seconds.', $this->timeout));
    }

    public function terminate(): void
    {
        if ($this->process?->isRunning()) {
            $this->process->stop(1);
        }

        $this->process = null;
        $this->port = null;
    }

    private function start(): void
    {
        if ($this->process?->isRunning()) {
            return;
        }

        $binary = $this->binary();
        $this->port = $this->allocatePort();
        $this->process = new Process([
            $binary,
            'serve',
            '--host',
            '127.0.0.1',
            '--port',
            (string) $this->port,
        ]);
        $this->process->setTimeout(null);
        $this->process->start();
    }

    private function binary(): string
    {
        if ($this->binary === null || $this->binary === '') {
            throw new LightpandaBinaryNotFound('Lightpanda requires TRAVERSE_LIGHTPANDA_BINARY to point to an executable binary.');
        }

        if (! is_file($this->binary) || ! is_executable($this->binary)) {
            throw new LightpandaBinaryNotFound(sprintf('Lightpanda binary [%s] does not exist or is not executable.', $this->binary));
        }

        return $this->binary;
    }

    private function endpoint(): string
    {
        if ($this->port === null) {
            throw new LightpandaProcessException('Lightpanda has not been started.');
        }

        return sprintf('ws://127.0.0.1:%d/', $this->port);
    }

    private function allocatePort(): int
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new LightpandaProcessException(
                sprintf('Could not allocate a local port for Lightpanda: %s', $errorMessage),
                is_int($errorCode) ? $errorCode : 0,
            );
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if (! is_string($address) || ! str_contains($address, ':')) {
            throw new LightpandaProcessException('Could not determine the local port allocated for Lightpanda.');
        }

        $port = filter_var(substr($address, strrpos($address, ':') + 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65_535],
        ]);

        if (! is_int($port)) {
            throw new LightpandaProcessException('Could not determine a valid local port for Lightpanda.');
        }

        return $port;
    }

    private function processFailed(): LightpandaProcessException
    {
        $diagnostics = trim($this->process?->getErrorOutput() ?? '');
        $message = 'Lightpanda exited before its CDP server became available.';

        if ($diagnostics !== '') {
            $message .= sprintf(' Diagnostics: %s', $diagnostics);
        }

        $exitCode = $this->process?->getExitCode();

        return new LightpandaProcessException($message, is_int($exitCode) ? $exitCode : 0);
    }
}
