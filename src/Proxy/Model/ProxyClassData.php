<?php

declare(strict_types=1);

namespace ClassProxy\Proxy\Model;

class ProxyClassData
{
    public function __construct(
        public readonly string $className,
        public readonly string $hash,
        public readonly string $classFQCN,
        public readonly string $body
    ) {
    }
}
