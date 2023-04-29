<?php

declare(strict_types=1);

namespace ClassProxy\Tests\_data\ProxyPass;

use ClassProxy\DependencyInjection\Attribute\Cache;
use ClassProxy\Tests\_data\RepoInterface;
use Symfony\Contracts\Service\Attribute\Required;

class MethodInjecteeClass
{
    private RepoInterface $repo;

    #[Required]
    public function setDependencies(#[Cache] RepoInterface $repo): void
    {
        $this->repo = $repo;
    }
}
