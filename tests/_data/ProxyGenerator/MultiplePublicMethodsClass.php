<?php

namespace App\Tests\_data\ProxyGenerator;

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

        return strlen($param1);
    }

    public function moreParams(int $param1, array &$param2): array
    {
        $this->lastAction = 'called moreParams';

        return ['text1', $param1, new EmptyClass()];
    }

    public function variadicParams(\Stringable $param1, array ...$param2): \Stringable|string
    {
        return 'text';
    }

    public function complexParamTypes(\Stringable|string $param1, \Stringable&\Serializable $param2): bool
    {
        return true;
    }
}
