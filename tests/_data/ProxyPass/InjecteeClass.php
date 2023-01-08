<?php

namespace App\Tests\_data\ProxyPass;

use App\DependencyInjection\Attribute\Cache;
use App\Process\Home\RepoInterface;

class InjecteeClass
{
    public function __construct(#[Cache] private readonly RepoInterface $repo)
    {
    }
}
