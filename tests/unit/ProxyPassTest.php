<?php

namespace App\Tests\unit;

use App\DependencyInjection\Compiler\ProxyPass;
use App\Tests\_data\ProxyPass\ConstructorInjecteeClass;
use App\Tests\_data\ProxyPass\MethodInjecteeClass;
use App\Tests\_data\RepoClass;
use Codeception\Test\Unit;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function Symfony\Component\String\s;

class ProxyPassTest extends Unit
{
    private string $cacheDir;

    protected function _before(): void
    {
        $this->cacheDir = codecept_root_dir('var/cache/test');
    }


    public function testConstructorArgumentReplacement(): void
    {
        [$originalClassDef, $injecteeDef] = $this->registerServices(
            static fn (ContainerBuilder $builder) => $builder
                ->register('injectee', ConstructorInjecteeClass::class)
                ->setArguments([new Reference('repo_class')])
        );

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

        /** @var Definition $argumentDef */
        $argumentDef = $injecteeDef->getMethodCalls()[0][1][0];

        $this->assertInstanceOf(Definition::class, $argumentDef);
        $this->assertStringContainsString('GeneratedProxy', $argumentDef->getClass());
        $this->assertFileExists(
            $this->cacheDir.'/ProxyCache/'.s($argumentDef->getClass())->afterLast('\\')->toString().'.php'
        );
        $this->assertEquals($originalClassDef->getMethodCalls(), $argumentDef->getMethodCalls());
    }

    /**
     * @param callable(ContainerBuilder): Definition $build
     *
     * @return Definition[]
     */
    private function registerServices(callable $build): array
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->setParameter('kernel.debug', true);
        $containerBuilder->setParameter('kernel.cache_dir', $this->cacheDir);

        $originalClassDef = $containerBuilder
            ->register('repo_class', RepoClass::class)
            ->setArguments(['$projectDir' => '/var/www'])
            ->addMethodCall('setDependencies', ['$projectDir' => '/var/www'])
        ;
        $injecteeDef = $build($containerBuilder)
            ->setPublic(true)
        ;

        $containerBuilder->addCompilerPass(new ProxyPass(), PassConfig::TYPE_REMOVE);
        $containerBuilder->compile();

        $this->assertNotEmpty($originalClassDef->getArguments());

        return [$originalClassDef, $injecteeDef];
    }
}
