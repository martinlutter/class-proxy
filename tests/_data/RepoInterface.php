<?php

namespace App\Tests\_data;

interface RepoInterface
{
    public function byStringParam(string $param): string;

    public function byIntParam(int $param): int;
}
