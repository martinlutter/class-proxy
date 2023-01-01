<?php

namespace App\DependencyInjection\Compiler;

use App\DependencyInjection\Attribute\Cache;
use App\Util\ProxyGenerator;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

class ProxyPass extends AbstractRecursivePass
{
    private ?ConfigCacheFactoryInterface $configCacheFactory = null;

    protected function processValue(mixed $value, bool $isRoot = false)
    {
        $processValue = parent::processValue($value, $isRoot);

        if (!$value instanceof Definition || !$value->isAutowired() || $value->isAbstract() || !$value->getClass()) {
            return $value;
        }
        if (!$reflectionClass = $this->container->getReflectionClass($value->getClass(), false)) {
            return $value;
        }

        foreach ($reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            foreach ($parameter->getAttributes(Cache::class) as $attribute) {
                /** @var Definition $argumentDef */
                $argumentDef = $processValue->getArgument($parameter->getPosition());

                $proxyClassData = ProxyGenerator::generate(
                    $argumentDef->getClass(),
                    $this->container->getReflectionClass($argumentDef->getClass())
                );

                eval($proxyClassData->body);

                $proxyDef = $this->container
                    ->register("proxy_cache.$proxyClassData->hash", $proxyClassData->classFQCN)
                    ->setArguments($argumentDef->getArguments());
                try {
                    $processValue->replaceArgument('$'.$parameter->getName(), $proxyDef);
                } catch (OutOfBoundsException) {
                    $processValue->replaceArgument($parameter->getPosition(), $proxyDef);
                }

                $this->getConfigCacheFactory()->cache(
                    "{$this->getCacheDir()}/$proxyClassData->className.php",
                    function (ConfigCacheInterface $cache) use ($proxyClassData) {
                        $cache->write("<?php\n\n{$proxyClassData->body}\n");
                    }
                );

                break;
            }
        }

        return $processValue;
    }

    private function getConfigCacheFactory(): ConfigCacheFactoryInterface
    {
        if (!$this->container->has('proxy_cache.cache_factory')) {
            $this->container->set(
                'proxy_cache.cache_factory',
                $this->configCacheFactory ??= new ConfigCacheFactory($this->container->getParameter('kernel.debug'))
            );
        }

        return $this->configCacheFactory;
    }

    private function getCacheDir(): string
    {
        return $this->container->getParameter('kernel.cache_dir').'/ProxyCache';
    }
}
