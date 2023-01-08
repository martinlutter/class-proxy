<?php

namespace App\Tests\unit;

use App\Tests\_data\ProxyGenerator\EmptyClass;
use App\Tests\_data\ProxyGenerator\MultiplePublicMethodsClass;
use App\Tests\_data\ProxyGenerator\OnePublicMethodClass;
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
            OnePublicMethodClass::class,
            new \ReflectionClass(OnePublicMethodClass::class)
        );

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
            MultiplePublicMethodsClass::class,
            new \ReflectionClass(MultiplePublicMethodsClass::class)
        );

        $this->assertStringNotContainsString('__construct', $proxyData->body);
        $this->assertStringContainsString("public function noParamsAndReturnType()\n", $proxyData->body);
        $this->assertStringContainsString("public function oneParam(string \$param1): int\n", $proxyData->body);
    }

    public function testReferenceParam(): void
    {
        $proxyData = ProxyGenerator::generate(
            MultiplePublicMethodsClass::class, new \ReflectionClass(
            MultiplePublicMethodsClass::class
        ));
        $this->assertStringContainsString(
            "public function moreParams(int \$param1, array &\$param2): array\n",
            $proxyData->body
        );
        $this->assertStringContainsString('parent::$methodName($param1, $param2)', $proxyData->body);
    }

    public function testComplexTypes(): void
    {
        $proxyData = ProxyGenerator::generate(
            MultiplePublicMethodsClass::class,
            new \ReflectionClass(MultiplePublicMethodsClass::class)
        );
        $this->assertStringContainsString(
            "public function variadicParams(\Stringable \$param1, array ...\$param2): \Stringable|string\n",
            $proxyData->body
        );
        $this->assertStringContainsString(
            "public function complexParamTypes(\Stringable|string \$param1, \Stringable&\Serializable \$param2): bool\n",
            $proxyData->body
        );
    }

    public function testCaching(): void
    {
        $proxyData = ProxyGenerator::generate(
            MultiplePublicMethodsClass::class,
            new \ReflectionClass(MultiplePublicMethodsClass::class)
        );

        eval($proxyData->body);
        /** @var MultiplePublicMethodsClass $object */
        $object = new $proxyData->classFQCN;

        $this->assertEquals('text', $object->noParamsAndReturnType(), 'check params first time');
        $this->assertEquals('called noParamsAndReturnType', $object->lastAction, 'original method called');
        $object->lastAction = '';
        $this->assertEquals('text', $object->noParamsAndReturnType(), 'check params second time');
        $this->assertEquals('', $object->lastAction, 'cached result returned');

        $this->assertEquals(7, $object->oneParam('tralala'), 'check params first time');
        $this->assertEquals('called oneParam', $object->lastAction, 'original method called');
        $object->lastAction = '';
        $this->assertEquals(7, $object->oneParam('tralala'), 'check params second time');
        $this->assertEquals('', $object->lastAction, 'cached result returned');
        $this->assertEquals(3, $object->oneParam('bla'), 'check params first time');
        $this->assertEquals('called oneParam', $object->lastAction, 'original method called');
        $object->lastAction = '';
        $this->assertEquals(3, $object->oneParam('bla'), 'check params second time');
        $this->assertEquals('', $object->lastAction, 'cached result returned');

        $array = [];
        $this->assertEquals(['text1', 1, new EmptyClass()], $object->moreParams(1, $array), 'check params first time');
        $this->assertEquals('called moreParams', $object->lastAction, 'original method called');
        $object->lastAction = '';
        $this->assertEquals(['text1', 4, new EmptyClass()], $object->moreParams(4, $array), 'check params first time');
        $this->assertEquals('called moreParams', $object->lastAction, 'original method called');
        $object->lastAction = '';
        $this->assertEquals(['text1', 1, new EmptyClass()], $object->moreParams(1, $array), 'check params second time');
        $this->assertEquals('', $object->lastAction, 'cached result returned');
        $this->assertEquals(['text1', 4, new EmptyClass()], $object->moreParams(4, $array), 'check params second time');
        $this->assertEquals('', $object->lastAction, 'cached result returned');
    }
}
