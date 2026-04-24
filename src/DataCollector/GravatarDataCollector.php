<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * Collects Gravatar service calls for display in the Symfony Web Profiler.
 */
class GravatarDataCollector extends DataCollector
{
    /** @var array<int, array{method: string, email_hash: string, duration_ms: float, cache_hit: bool, success: bool}> */
    private array $calls = [];

    /**
     * Records a single Gravatar service call.
     */
    public function addCall(
        string $method,
        string $email,
        float $durationMs,
        bool $cacheHit,
        bool $success,
    ): void {
        $this->calls[] = [
            'method' => $method,
            'email_hash' => md5(strtolower(trim($email))),
            'duration_ms' => round($durationMs, 2),
            'cache_hit' => $cacheHit,
            'success' => $success,
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = ['calls' => $this->calls];
    }

    public function reset(): void
    {
        $this->data = [];
        $this->calls = [];
    }

    public function getName(): string
    {
        return 'gravatar';
    }

    /** @return array<int, array{method: string, email_hash: string, duration_ms: float, cache_hit: bool, success: bool}> */
    public function getCalls(): array
    {
        return $this->data['calls'] ?? [];
    }

    public function getCallCount(): int
    {
        return \count($this->getCalls());
    }

    public function getCacheHitCount(): int
    {
        return \count(array_filter($this->getCalls(), fn (array $c): bool => $c['cache_hit']));
    }

    public function getErrorCount(): int
    {
        return \count(array_filter($this->getCalls(), fn (array $c): bool => !$c['success']));
    }

    public function getTotalDurationMs(): float
    {
        return round(array_sum(array_column($this->getCalls(), 'duration_ms')), 2);
    }
}
