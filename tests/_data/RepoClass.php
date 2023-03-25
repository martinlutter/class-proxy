<?php

namespace App\Tests\_data;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

class RepoClass implements RepoInterface
{
    private string $projectDir;

    public function __construct(#[Autowire('%kernel.project_dir%')] string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    #[Required]
    public function setDependencies(#[Autowire('%kernel.project_dir%')] string $projectDir): void
    {
        $this->projectDir = $projectDir;
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }
    
    public function byStringParam(string $param): string
    {
        dump('dump from repo class');
        return "Wowee by string - $param";
    }

    public function byIntParam(?int $param): int
    {
        dump('dump from repo class');
        return (int) $param;
    }
}
