<?php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\SelfCheckingResourceInterface;

class SimpleResource implements SelfCheckingResourceInterface
{
    public function __construct(private readonly string $classAsString)
    {
    }

    public function isFresh(int $timestamp): bool
    {
        // TODO: Implement isFresh() method.
    }

    public function __toString(): string
    {
        return $this->classAsString;
    }
}
