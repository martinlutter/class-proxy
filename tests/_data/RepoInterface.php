<?php

namespace ClassProxy\Tests\_data;

interface RepoInterface
{
    public function byStringParam(string $param): string;

    public function byIntParam(int $param): int;
}
