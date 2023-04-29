<?php

declare(strict_types=1);

namespace ClassProxy\Tests\_data;

class BlablaRepoClass implements RepoInterface
{
    public function byStringParam(string $param): string
    {
        return 'blabla';
    }

    public function byIntParam(int $param): int
    {
        return 12345;
    }
}
