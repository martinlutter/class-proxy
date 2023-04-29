<?php

declare(strict_types=1);

namespace ClassProxy\Tests\_data;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

class RepoClass implements RepoInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        private int $customValue = 0
    ) {
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
        return "Wowee by string - $param";
    }

    public function byIntParam(?int $param): int
    {
        return (int) $param;
    }

    public function getCustomValue(): int
    {
        return $this->customValue;
    }
}
