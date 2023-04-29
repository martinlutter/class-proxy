<?php

declare(strict_types=1);

namespace ClassProxy\Tests\_data\ProxyGenerator;

class OnePublicMethodClass
{
    public function noParams(): string
    {
        return 'text';
    }
}
