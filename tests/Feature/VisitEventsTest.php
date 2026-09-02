<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Tonyputi\Traverse\Contracts\Browser;
use Tonyputi\Traverse\Contracts\Factory;
use Tonyputi\Traverse\Contracts\Page;
use Tonyputi\Traverse\Events\VisitCompleted;
use Tonyputi\Traverse\Events\VisitFailed;
use Tonyputi\Traverse\Events\VisitStarted;

it('dispatches correlated start and completion events for a custom driver', function (): void {
    Event::fake();

    $page = new class implements Page
    {
        public function markdown(): string
        {
            return '# Traverse';
        }

        public function semanticTree(): array
        {
            return [];
        }

        public function interactiveElements(): array
        {
            return [];
        }

        public function structuredData(): array
        {
            return [];
        }
    };

    $browser = new class($page) implements Browser
    {
        public function __construct(private readonly Page $page) {}

        public function visit(string $url): Page
        {
            return $this->page;
        }
    };

    $factory = app(Factory::class);
    $factory->extend('fake', fn () => $browser);

    expect($factory->browser('fake')->visit('https://example.test'))->toBe($page);

    Event::assertDispatchedOnce(VisitStarted::class);
    Event::assertDispatchedOnce(VisitCompleted::class);
    Event::assertNotDispatched(VisitFailed::class);

    $started = Event::dispatched(VisitStarted::class)->sole()[0];
    $completed = Event::dispatched(VisitCompleted::class)->sole()[0];

    expect($started)
        ->toBeInstanceOf(VisitStarted::class)
        ->invocationId->toBe($completed->invocationId)
        ->url->toBe('https://example.test')
        ->driver->toBe('fake')
        ->startedAt->toBeInstanceOf(DateTimeImmutable::class)
        ->and($completed)
        ->toBeInstanceOf(VisitCompleted::class)
        ->url->toBe('https://example.test')
        ->driver->toBe('fake')
        ->completedAt->toBeInstanceOf(DateTimeImmutable::class)
        ->durationInMilliseconds->toBeGreaterThanOrEqual(0.0)
        ->and(get_object_vars($completed))->not->toHaveKey('page');
});

it('dispatches visit lifecycle events in order', function (): void {
    $events = [];

    Event::listen(VisitStarted::class, function () use (&$events): void {
        $events[] = VisitStarted::class;
    });
    Event::listen(VisitCompleted::class, function () use (&$events): void {
        $events[] = VisitCompleted::class;
    });

    $page = new class implements Page
    {
        public function markdown(): string
        {
            return '';
        }

        public function semanticTree(): array
        {
            return [];
        }

        public function interactiveElements(): array
        {
            return [];
        }

        public function structuredData(): array
        {
            return [];
        }
    };

    $factory = app(Factory::class);
    $factory->extend('fake', fn () => new class($page) implements Browser
    {
        public function __construct(private readonly Page $page) {}

        public function visit(string $url): Page
        {
            return $this->page;
        }
    });

    $factory->browser('fake')->visit('https://example.test');

    expect($events)->toBe([VisitStarted::class, VisitCompleted::class]);
});

it('dispatches a failure event and rethrows the driver exception', function (): void {
    Event::fake();

    $exception = new RuntimeException('The driver failed.');
    $browser = new class($exception) implements Browser
    {
        public function __construct(private readonly RuntimeException $exception) {}

        public function visit(string $url): Page
        {
            throw $this->exception;
        }
    };

    $factory = app(Factory::class);
    $factory->extend('failing', fn () => $browser);

    expect(fn () => $factory->browser('failing')->visit('https://example.test'))
        ->toThrow($exception);

    Event::assertDispatchedOnce(VisitStarted::class);
    Event::assertDispatchedOnce(VisitFailed::class);
    Event::assertNotDispatched(VisitCompleted::class);

    $started = Event::dispatched(VisitStarted::class)->sole()[0];
    $failed = Event::dispatched(VisitFailed::class)->sole()[0];

    expect($failed)
        ->toBeInstanceOf(VisitFailed::class)
        ->invocationId->toBe($started->invocationId)
        ->url->toBe('https://example.test')
        ->driver->toBe('failing')
        ->failedAt->toBeInstanceOf(DateTimeImmutable::class)
        ->durationInMilliseconds->toBeGreaterThanOrEqual(0.0)
        ->exceptionClass->toBe($exception::class);
});

it('serializes visit event payloads without a page result', function (): void {
    $started = new VisitStarted(
        '019c9aee-1234-7000-8000-000000000000',
        'https://example.test',
        'fake',
        new DateTimeImmutable,
    );
    $completed = new VisitCompleted(
        '019c9aee-1234-7000-8000-000000000000',
        'https://example.test',
        'fake',
        new DateTimeImmutable,
        12.5,
        true,
    );
    $failed = new VisitFailed(
        '019c9aee-1234-7000-8000-000000000000',
        'https://example.test',
        'fake',
        new DateTimeImmutable,
        12.5,
        RuntimeException::class,
    );

    $restoredStarted = unserialize(serialize($started));
    $restoredCompleted = unserialize(serialize($completed));
    $restoredFailed = unserialize(serialize($failed));

    expect($restoredStarted)
        ->toBeInstanceOf(VisitStarted::class)
        ->invocationId->toBe($started->invocationId)
        ->and($restoredCompleted)
        ->toBeInstanceOf(VisitCompleted::class)
        ->durationInMilliseconds->toBe(12.5)
        ->cacheHit->toBeTrue()
        ->and(get_object_vars($restoredCompleted))->not->toHaveKey('page')
        ->and($restoredFailed)
        ->toBeInstanceOf(VisitFailed::class)
        ->exceptionClass->toBe(RuntimeException::class);
});
