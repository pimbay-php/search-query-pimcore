<?php

declare(strict_types=1);

namespace PimBay\SearchQuery\Pimcore\Tests\Fixture;

final class CallLog
{
    /** @var list<array{string, mixed}> */
    public array $calls = [];

    public function record(string $method, mixed $argument = null): void
    {
        $this->calls[] = [$method, $argument];
    }

    /**
     * @return list<string>
     */
    public function methods(): array
    {
        return array_map(static fn (array $call): string => $call[0], $this->calls);
    }

    /**
     * @return list<mixed>
     */
    public function argumentsOf(string $method): array
    {
        $arguments = [];

        foreach ($this->calls as [$name, $argument]) {
            if ($name === $method) {
                $arguments[] = $argument;
            }
        }

        return $arguments;
    }
}
