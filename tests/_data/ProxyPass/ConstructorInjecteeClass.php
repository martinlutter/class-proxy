<?php

declare(strict_types=1);

namespace ClassProxy\Tests\_data\ProxyPass;

use ClassProxy\DependencyInjection\Attribute\Cache;
use ClassProxy\Tests\_data\RepoInterface;

class ConstructorInjecteeClass
{
    public function __construct(#[Cache] private readonly RepoInterface $repo)
    {
    }

    public function getRepo(): RepoInterface
    {
        return $this->repo;
    }
}
