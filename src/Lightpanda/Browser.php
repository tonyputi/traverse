<?php

declare(strict_types=1);

namespace Tonyputi\Traverse\Lightpanda;

use Tonyputi\Traverse\Contracts\Page as PageContract;
use Tonyputi\Traverse\Contracts\TerminableBrowser;
use Tonyputi\Traverse\Exceptions\Lightpanda\ProtocolException;

final class Browser implements TerminableBrowser
{
    private const MINIMUM_VERSION = '0.3.7';

    private const NEXT_MINOR_VERSION = '0.4.0';

    public function __construct(private readonly Process $process) {}

    public function visit(string $url): PageContract
    {
        $connection = $this->process->connect();
        $targetId = null;

        try {
            $this->assertSupportedVersion($connection);
            $target = $connection->call('Target.createTarget', ['url' => 'about:blank']);
            $targetId = $this->stringResult($target, 'targetId', 'Target.createTarget');
            $session = $connection->call('Target.attachToTarget', ['targetId' => $targetId, 'flatten' => true]);
            $sessionId = $this->stringResult($session, 'sessionId', 'Target.attachToTarget');

            $connection->call('Page.enable', sessionId: $sessionId);
            $connection->navigate($url, $sessionId);

            $markdown = $this->stringResult($connection->call('LP.getMarkdown', sessionId: $sessionId), 'markdown', 'LP.getMarkdown');
            $semanticTree = $this->semanticTreeResult($connection->call('LP.getSemanticTree', sessionId: $sessionId));
            $interactiveElements = $this->interactiveElementsResult($connection->call('LP.getInteractiveElements', sessionId: $sessionId));
            $structuredData = $this->arrayResult($connection->call('LP.getStructuredData', sessionId: $sessionId), 'structuredData', 'LP.getStructuredData');

            return new Page($markdown, $semanticTree, $interactiveElements, $structuredData);
        } finally {
            if ($targetId !== null) {
                try {
                    $connection->call('Target.closeTarget', ['targetId' => $targetId]);
                } catch (\Throwable) {
                    // The connection is about to close and the browser owns the target lifecycle.
                }
            }

            $connection->close();
        }
    }

    public function terminate(): void
    {
        $this->process->terminate();
    }

    private function assertSupportedVersion(CdpConnection $connection): void
    {
        $version = $this->stringResult($connection->call('LP.version'), 'version', 'LP.version');

        if (version_compare($version, self::MINIMUM_VERSION, '<') || version_compare($version, self::NEXT_MINOR_VERSION, '>=')) {
            throw new ProtocolException(sprintf(
                'Lightpanda version [%s] is not supported. Traverse requires Lightpanda >= %s and < %s.',
                $version,
                self::MINIMUM_VERSION,
                self::NEXT_MINOR_VERSION,
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function stringResult(array $result, string $key, string $method): string
    {
        if (! is_string($result[$key] ?? null)) {
            throw new ProtocolException(sprintf('Lightpanda returned an invalid [%s] result for [%s].', $key, $method));
        }

        return $result[$key];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function semanticTreeResult(array $result): array
    {
        $semanticTree = $result['semanticTree'] ?? null;

        if (! is_array($semanticTree) || array_is_list($semanticTree)) {
            throw new ProtocolException('Lightpanda returned an invalid [semanticTree] result for [LP.getSemanticTree].');
        }

        return $semanticTree;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int, mixed>
     */
    private function interactiveElementsResult(array $result): array
    {
        $elements = $result['elements'] ?? null;

        if (! is_array($elements) || ! array_is_list($elements)) {
            throw new ProtocolException('Lightpanda returned an invalid [elements] result for [LP.getInteractiveElements].');
        }

        return $elements;
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<int|string, mixed>
     */
    private function arrayResult(array $result, string $key, string $method): array
    {
        if (! is_array($result[$key] ?? null)) {
            throw new ProtocolException(sprintf('Lightpanda returned an invalid [%s] result for [%s].', $key, $method));
        }

        return $result[$key];
    }
}
