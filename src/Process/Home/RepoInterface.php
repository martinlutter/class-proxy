<?php

namespace App\Process\Home;

interface RepoInterface
{
    public function byStringParam(string $param): string;

    public function byIntParam(int $param): int;
}
