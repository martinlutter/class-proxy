<?php

namespace App\Process\Home;

class RepoClass implements RepoInterface
{
    public function byStringParam(string $param): string
    {
        dump('dump from repo class');
        return "Wowee by string - $param";
    }

    public function byIntParam(?int $param): int
    {
        dump('dump from repo class');
        return "Wowee by int - $param";
    }

    public function comp(RepoInterface & \Stringable $bla): RepoInterface | \Serializable
    {
    }
}
