<?php

namespace App\Tests\_data\ProxyClasses;

class MultiplePublicMethodsClass
{
    public function __construct(public string $lastAction = '')
    {
    }

    public function noParamsAndReturnType()
    {
        $this->lastAction = 'called noParamsAndReturnType';

        return 'text';
    }

    public function oneParam(string $param1): int
    {
        $this->lastAction = 'called oneParam';

        return 42;
    }

    public function moreParams(int $param1, array $param2): array
    {
        $this->lastAction = 'called moreParams';

        return ['text1', 42, new EmptyClass()];
    }
}
