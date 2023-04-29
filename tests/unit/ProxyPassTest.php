<?php

declare(strict_types=1);

namespace ClassProxy\Tests\unit;

use ClassProxy\ClassProxyBundle;
use ClassProxy\DependencyInjection\Compiler\ProxyPass;
use ClassProxy\Tests\_data\ProxyPass\ConstructorInjecteeClass;
use ClassProxy\Tests\_data\ProxyPass\MethodInjecteeClass;
use ClassProxy\Tests\_data\RepoClass;
use Codeception\Test\Unit;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\String\s;

class ProxyPassTest extends Unit
{
    private string $cacheDir;
    private ?ContainerBuilder $containerBuilder;

    protected function _before(): void
    {
        $this->cacheDir = codecept_root_dir('var/cache/test');

        $this->containerBuilder = new ContainerBuilder();
        $this->containerBuilder->setParameter('kernel.debug', true);
        $this->containerBuilder->setParameter('kernel.cache_dir', $this->cacheDir);
        $this->containerBuilder->addCompilerPass(new ProxyPass());
        $this->containerBuilder->addCompilerPass(new ProxyPass(), PassConfig::TYPE_REMOVE);
    }

    protected function _after(): void
    {
        $this->containerBuilder = null;
    }

    public function testConstructorArgumentReplacement(): void
    {
        [$originalClassDef, $injecteeDef] = $this->registerServices(
            static fn (ContainerBuilder $builder) => $builder
                ->register('injectee', ConstructorInjecteeClass::class)
                ->setArguments([new Reference('repo_class')])
        );
        $this->containerBuilder->compile();

        /** @var Definition $argumentDef */
        $argumentDef = $injecteeDef->getArgument(0);

        $this->assertInstanceOf(Definition::class, $argumentDef);
        $this->assertStringContainsString('GeneratedProxy', $argumentDef->getClass());
        $this->assertFileExists(
            $this->cacheDir.'/ProxyCache/'.s($argumentDef->getClass())->afterLast('\\')->toString().'.php'
        );
        $this->assertEquals($originalClassDef->getArguments(), $argumentDef->getArguments());
    }

    public function testRequiredMethodArgumentReplacement(): void
    {
        [$originalClassDef, $injecteeDef] = $this->registerServices(
            fn (ContainerBuilder $builder) => $builder
                ->register('injectee', MethodInjecteeClass::class)
                ->addMethodCall('setDependencies', [new Reference('repo_class')])
        );
        $this->containerBuilder->compile();

        /** @var Definition $argumentDef */
        $argumentDef = $injecteeDef->getMethodCalls()[0][1][0];

        $this->assertInstanceOf(Definition::class, $argumentDef);
        $this->assertStringContainsString('GeneratedProxy', $argumentDef->getClass());
        $this->assertFileExists(
            $this->cacheDir.'/ProxyCache/'.s($argumentDef->getClass())->afterLast('\\')->toString().'.php'
        );
        $this->assertEquals($originalClassDef->getMethodCalls(), $argumentDef->getMethodCalls());
    }

    public function testMultipleServicesOfSameClass(): void
    {
        $this->containerBuilder
            ->register('repo_class_another', RepoClass::class)
            ->setArguments(['$projectDir' => '/var/www', '$customValue' => 12345])
            ->addMethodCall('setDependencies', ['$projectDir' => '/var/www'])
        ;
        $this->registerServices(
            fn (ContainerBuilder $builder) => $builder
                ->register('injectee1', ConstructorInjecteeClass::class)
                ->setArguments([new Reference('repo_class')])
        );
        $this->registerServices(
            fn (ContainerBuilder $builder) => $builder
                ->register('injectee2', ConstructorInjecteeClass::class)
                ->setArguments([new Reference('repo_class_another')])
        );
        $this->containerBuilder->compile();

        $bundle = new ClassProxyBundle();
        $bundle->setContainer($this->containerBuilder);
        $bundle->boot();

        /** @var ConstructorInjecteeClass $injectee */
        $injectee = $this->containerBuilder->get('injectee1');
        /** @var RepoClass $repo */
        $repo = $injectee->getRepo();
        $this->assertEquals(0, $repo->getCustomValue());

        /** @var ConstructorInjecteeClass $injectee */
        $injectee = $this->containerBuilder->get('injectee2');
        /** @var RepoClass $repo */
        $repo = $injectee->getRepo();
        $this->assertEquals(12345, $repo->getCustomValue());
    }

    /**
     * @param callable(ContainerBuilder): Definition $build
     *
     * @return Definition[]
     */
    private function registerServices(callable $build): array
    {
        $originalClassDef = $this->containerBuilder
            ->register('repo_class', RepoClass::class)
            ->setArguments(['$projectDir' => '/var/www'])
            ->addMethodCall('setDependencies', ['$projectDir' => '/var/www'])
        ;
        $injecteeDef = $build($this->containerBuilder)
            ->setPublic(true)
        ;

        $this->assertNotEmpty($originalClassDef->getArguments());

        return [$originalClassDef, $injecteeDef];
    }
}
