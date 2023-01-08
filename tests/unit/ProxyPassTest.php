<?php

namespace App\Tests\unit;

use App\DependencyInjection\Compiler\ProxyPass;
use App\Process\Home\RepoClass;
use App\Tests\_data\ProxyPass\ConstructorInjecteeClass;
use App\Tests\_data\ProxyPass\MethodInjecteeClass;
use Codeception\Test\Unit;
use Symfony\Component\DependencyInjection\Compiler\AutowireRequiredMethodsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\String\s;

class ProxyPassTest extends Unit
{
    public function testConstructorArgumentReplacement(): void
    {
        $cacheDir = codecept_root_dir('var/cache/test');

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.debug', true);
        $containerBuilder->setParameter('kernel.cache_dir', $cacheDir);

        $containerBuilder->register('repo_class', RepoClass::class);
        $injecteeDef = $containerBuilder
            ->register('injectee', ConstructorInjecteeClass::class)
            ->setArguments([new Reference('repo_class')])
            ->setPublic(true);

        $containerBuilder->addCompilerPass(new ProxyPass(), PassConfig::TYPE_REMOVE);
        $containerBuilder->compile();

        /** @var Definition|mixed $argumentDef */
        $argumentDef = $injecteeDef->getArgument(0);

        $this->assertInstanceOf(Definition::class, $argumentDef);
        $this->assertStringContainsString('GeneratedProxy', $argumentDef->getClass());
        $this->assertFileExists(
            $cacheDir.'/ProxyCache/'.s($argumentDef->getClass())->afterLast('\\')->toString().'.php'
        );
    }

    public function testRequiredMethodArgumentReplacement(): void
    {
        $cacheDir = codecept_root_dir('var/cache/test');

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.debug', true);
        $containerBuilder->setParameter('kernel.cache_dir', $cacheDir);

        $containerBuilder->register('repo_class', RepoClass::class);
        $injecteeDef = $containerBuilder
            ->register('injectee', MethodInjecteeClass::class)
            ->addMethodCall('setDependencies', [new Reference('repo_class')])
            ->setPublic(true);

        $containerBuilder->addCompilerPass(new ProxyPass(), PassConfig::TYPE_REMOVE);
        $containerBuilder->compile();

        /** @var Definition|mixed $argumentDef */
        $argumentDef = $injecteeDef->getMethodCalls()[0][1][0];

        $this->assertInstanceOf(Definition::class, $argumentDef);
        $this->assertStringContainsString('GeneratedProxy', $argumentDef->getClass());
        $this->assertFileExists(
            $cacheDir.'/ProxyCache/'.s($argumentDef->getClass())->afterLast('\\')->toString().'.php'
        );
    }
}
