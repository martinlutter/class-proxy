<?php

namespace App\Tests\_data\ProxyPass;

use App\DependencyInjection\Attribute\Cache;
use App\Process\Home\RepoInterface;
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
