<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Lightpanda;

use Amp\CancelledException;
use Amp\TimeoutCancellation;
use Amp\Websocket\Client\WebsocketConnection;
use JsonException;
use Tonyputi\Traverse\Exceptions\Lightpanda\ProcessException;
use Tonyputi\Traverse\Exceptions\Lightpanda\ProtocolException;
use Tonyputi\Traverse\Exceptions\Lightpanda\TimeoutException;

use function Amp\Websocket\Client\connect;

final class CdpConnection
{
    private int $requestId = 0;

    private function __construct(
        private readonly WebsocketConnection $connection,
        private readonly int $timeout,
    ) {}

    public static function open(string $endpoint, int $timeout): self
    {
        try {
            return new self(connect($endpoint, new TimeoutCancellation($timeout)), $timeout);
        } catch (CancelledException $exception) {
            throw new TimeoutException('Timed out while connecting to the Lightpanda CDP server.', previous: $exception);
        } catch (\Throwable $exception) {
            throw new ProcessException('Could not connect to the Lightpanda CDP server.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function call(string $method, array $params = [], ?string $sessionId = null): array
    {
        $id = $this->send($method, $params, $sessionId);

        while (true) {
            $response = $this->receive();

            if (($response['id'] ?? null) !== $id) {
                continue;
            }

            return $this->result($response, $method);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function navigate(string $url, string $sessionId): array
    {
        $id = $this->send('Page.navigate', ['url' => $url], $sessionId);
        $navigation = null;
        $loaded = false;

        while ($navigation === null || ! $loaded) {
            $response = $this->receive();

            if (($response['id'] ?? null) === $id) {
                $navigation = $this->result($response, 'Page.navigate');

                if (isset($navigation['errorText']) && is_string($navigation['errorText'])) {
                    throw new ProtocolException(sprintf('Lightpanda could not navigate to [%s]: %s', $url, $navigation['errorText']));
                }

                continue;
            }

            if (($response['method'] ?? null) === 'Page.loadEventFired' && ($response['sessionId'] ?? null) === $sessionId) {
                $loaded = true;
            }
        }

        return $navigation;
    }

    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function send(string $method, array $params, ?string $sessionId): int
    {
        $request = [
            'id' => ++$this->requestId,
            'method' => $method,
            'params' => (object) $params,
        ];

        if ($sessionId !== null) {
            $request['sessionId'] = $sessionId;
        }

        try {
            $this->connection->sendText(json_encode($request, JSON_THROW_ON_ERROR));
        } catch (JsonException $exception) {
            throw new ProtocolException(sprintf('Could not encode the Lightpanda CDP command [%s].', $method), previous: $exception);
        }

        return $request['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function receive(): array
    {
        try {
            $message = $this->connection->receive(new TimeoutCancellation($this->timeout));
        } catch (CancelledException $exception) {
            throw new TimeoutException('Timed out while waiting for a Lightpanda CDP response.', previous: $exception);
        }

        if ($message === null) {
            throw new ProcessException('The Lightpanda CDP connection closed unexpectedly.');
        }

        try {
            $response = json_decode($message->buffer(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ProtocolException('Lightpanda returned an invalid CDP response.', previous: $exception);
        }

        if (! is_array($response)) {
            throw new ProtocolException('Lightpanda returned an invalid CDP response shape.');
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function result(array $response, string $method): array
    {
        if (isset($response['error'])) {
            $message = is_array($response['error']) && is_string($response['error']['message'] ?? null)
                ? $response['error']['message']
                : 'Unknown CDP error.';

            throw new ProtocolException(sprintf('Lightpanda rejected the CDP command [%s]: %s', $method, $message));
        }

        $result = $response['result'] ?? [];

        if (! is_array($result)) {
            throw new ProtocolException(sprintf('Lightpanda returned an invalid result for the CDP command [%s].', $method));
        }

        return $result;
    }
}
