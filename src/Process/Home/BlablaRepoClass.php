<?php

namespace App\Process\Home;

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
