<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Lightpanda;

use Illuminate\Process\Factory;
use Illuminate\Process\InvokedProcess;
use Tonyputi\Traverse\Exceptions\Lightpanda\BinaryNotFoundException;
use Tonyputi\Traverse\Exceptions\Lightpanda\ProcessException;
use Tonyputi\Traverse\Exceptions\Lightpanda\TimeoutException;

final class Process
{
    private ?int $port = null;

    private ?InvokedProcess $process = null;

    public function __construct(
        private readonly ?string $binary,
        private readonly int $timeout,
        private readonly Factory $processFactory,
    ) {}

    public function connect(): CdpConnection
    {
        $this->start();

        $deadline = microtime(true) + $this->timeout;

        while (microtime(true) < $deadline) {
            try {
                return CdpConnection::open($this->endpoint(), 1);
            } catch (ProcessException|TimeoutException) {
                if (! $this->process?->running()) {
                    throw $this->processFailed();
                }

                usleep(50_000);
            }
        }

        throw new TimeoutException(sprintf('Lightpanda did not start within %d seconds.', $this->timeout));
    }

    public function terminate(): void
    {
        if ($this->process?->running()) {
            $this->process->stop(1);
        }

        $this->process = null;
        $this->port = null;
    }

    private function start(): void
    {
        if ($this->process?->running()) {
            return;
        }

        $binary = $this->binary();
        $this->port = $this->allocatePort();
        $this->process = $this->processFactory
            ->newPendingProcess()
            ->forever()
            ->start([
                $binary,
                'serve',
                '--host',
                '127.0.0.1',
                '--port',
                (string) $this->port,
            ]);
    }

    private function binary(): string
    {
        if ($this->binary === null || $this->binary === '') {
            throw new BinaryNotFoundException('Lightpanda requires TRAVERSE_LIGHTPANDA_BINARY to point to an executable binary.');
        }

        if (! is_file($this->binary) || ! is_executable($this->binary)) {
            throw new BinaryNotFoundException(sprintf('Lightpanda binary [%s] does not exist or is not executable.', $this->binary));
        }

        return $this->binary;
    }

    private function endpoint(): string
    {
        if ($this->port === null) {
            throw new ProcessException('Lightpanda has not been started.');
        }

        return sprintf('ws://127.0.0.1:%d/', $this->port);
    }

    private function allocatePort(): int
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new ProcessException(
                sprintf('Could not allocate a local port for Lightpanda: %s', $errorMessage),
                is_int($errorCode) ? $errorCode : 0,
            );
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        if (! is_string($address) || ! str_contains($address, ':')) {
            throw new ProcessException('Could not determine the local port allocated for Lightpanda.');
        }

        $port = filter_var(substr($address, strrpos($address, ':') + 1), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65_535],
        ]);

        if (! is_int($port)) {
            throw new ProcessException('Could not determine a valid local port for Lightpanda.');
        }

        return $port;
    }

    private function processFailed(): ProcessException
    {
        $diagnostics = trim($this->process?->errorOutput() ?? '');
        $message = 'Lightpanda exited before its CDP server became available.';

        if ($diagnostics !== '') {
            $message .= sprintf(' Diagnostics: %s', $diagnostics);
        }

        return new ProcessException($message);
    }
}
