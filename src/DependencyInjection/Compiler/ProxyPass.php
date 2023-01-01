<?php

namespace App\DependencyInjection\Compiler;

use App\DependencyInjection\Attribute\Cache;
use App\Util\Model\ProxyClassData;
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
                $proxyDef = $this->getProxyDefinition($proxyClassData, $argumentDef);

                try {
                    $processValue->replaceArgument('$'.$parameter->getName(), $proxyDef);
                } catch (OutOfBoundsException) {
                    $processValue->replaceArgument($parameter->getPosition(), $proxyDef);
                }

                $this->getConfigCacheFactory()->cache(
                    "{$this->getCacheDir()}/$proxyClassData->className.php",
                    static function (ConfigCacheInterface $cache) use ($proxyClassData) {
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
        return $this->configCacheFactory ??= new ConfigCacheFactory($this->container->getParameter('kernel.debug'));
    }

    private function getCacheDir(): string
    {
        return $this->container->getParameter('kernel.cache_dir').'/ProxyCache';
    }

    private function getProxyDefinition(ProxyClassData $proxyClassData, Definition $argumentDefinition): Definition
    {
        $proxyServiceId = "proxy_cache.$proxyClassData->hash";
        if ($this->container->hasDefinition($proxyServiceId)) {
            return $this->container->getDefinition($proxyServiceId);
        }

        return $this->container
            ->register($proxyServiceId, $proxyClassData->classFQCN)
            ->setArguments($argumentDefinition->getArguments())
        ;
    }
}
