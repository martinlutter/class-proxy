<?php

namespace ClassProxy\Tests\_data\ProxyPass;

use ClassProxy\DependencyInjection\Attribute\Cache;
use ClassProxy\Tests\_data\RepoInterface;

class ConstructorInjecteeClass
{
    public function __construct(#[Cache] private readonly RepoInterface $repo)
    {
    }
}
