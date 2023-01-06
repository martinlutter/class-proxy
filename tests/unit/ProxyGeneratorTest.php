<?php

namespace App\Tests\unit;

use App\Tests\_data\ProxyClasses\EmptyClass;
use App\Tests\_data\ProxyClasses\MultiplePublicMethodsClass;
use App\Tests\_data\ProxyClasses\OnePublicMethodClass;
use App\Util\ProxyGenerator;
use Codeception\Test\Unit;

class ProxyGeneratorTest extends Unit
{
    public function testEmptyClass(): void
    {
        $proxyData = ProxyGenerator::generate(EmptyClass::class, new \ReflectionClass(EmptyClass::class));

        $this->assertStringContainsString(
            'class GeneratedProxyClass'.$proxyData->hash.' extends \\'.EmptyClass::class,
            $proxyData->body
        );

        eval($proxyData->body);
        $object = new $proxyData->classFQCN;

        $this->assertTrue(is_a($object, EmptyClass::class, true));
    }

    public function testOnePublicMethod(): void
    {
        $proxyData = ProxyGenerator::generate(
            OnePublicMethodClass::class, new \ReflectionClass(
            OnePublicMethodClass::class
        ));

        $this->assertStringContainsString('public function noParams(): string', $proxyData->body);
        $this->assertStringContainsString('$methodName = \'noParams\';', $proxyData->body);

        eval($proxyData->body);
        /** @var OnePublicMethodClass $object */
        $object = new $proxyData->classFQCN;

        $this->assertEquals('text', $object->noParams());
    }

    public function testMultiplePublicMethods(): void
    {
        $proxyData = ProxyGenerator::generate(
            MultiplePublicMethodsClass::class, new \ReflectionClass(
            MultiplePublicMethodsClass::class
        ));

        $this->assertStringNotContainsString('__construct', $proxyData->body);
        $this->assertStringContainsString("public function noParamsAndReturnType()\n", $proxyData->body);
        $this->assertStringContainsString("public function oneParam(string \$param1): int\n", $proxyData->body);
        $this->assertStringContainsString(
            "public function moreParams(int \$param1, array \$param2): array\n",
            $proxyData->body
        );

        eval($proxyData->body);
        /** @var MultiplePublicMethodsClass $object */
        $object = new $proxyData->classFQCN;

        //test returned values, check that they are the same & that the original method is not called a second time
        $this->assertEquals('text', $object->noParamsAndReturnType());
        $this->assertEquals('called noParamsAndReturnType', $object->lastAction);
        $object->lastAction = '';
        $this->assertEquals('text', $object->noParamsAndReturnType());
        $this->assertEquals('', $object->lastAction);

        $this->assertEquals(42, $object->oneParam('tralala'));
        $this->assertEquals('called oneParam', $object->lastAction);
        $object->lastAction = '';
        $this->assertEquals(42, $object->oneParam('tralala'));
        $this->assertEquals('', $object->lastAction);

        $this->assertEquals(['text1', 42, new EmptyClass()], $object->moreParams(1, []));
        $this->assertEquals('called moreParams', $object->lastAction);
        $object->lastAction = '';
        $this->assertEquals(['text1', 42, new EmptyClass()], $object->moreParams(1, []));
        $this->assertEquals('', $object->lastAction);
    }

//    public function testComplexParameterTypes(): void
//    {
//
//    }

//    public function testComplexReturnTypes(): void
//    {
//
//    }
}
