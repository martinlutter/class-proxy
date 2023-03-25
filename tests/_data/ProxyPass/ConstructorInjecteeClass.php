<?php

namespace App\Tests\_data\ProxyPass;

use App\DependencyInjection\Attribute\Cache;
use App\Tests\_data\RepoInterface;

class ConstructorInjecteeClass
{
    public function __construct(#[Cache] private readonly RepoInterface $repo)
    {
    }
}
