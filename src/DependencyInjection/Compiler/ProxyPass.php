<?php

namespace ClassProxy\DependencyInjection\Compiler;

use ClassProxy\DependencyInjection\Attribute\Cache;
use ClassProxy\Proxy\Model\ProxyClassData;
use ClassProxy\Proxy\ProxyGenerator;
use Symfony\Component\Config\ConfigCacheFactory;
use Symfony\Component\Config\ConfigCacheFactoryInterface;
use Symfony\Component\Config\ConfigCacheInterface;
use Symfony\Component\DependencyInjection\Compiler\AbstractRecursivePass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Contracts\Service\Attribute\Required;

class ProxyPass extends AbstractRecursivePass
{
    private ?ConfigCacheFactoryInterface $configCacheFactory = null;

    protected function processValue(mixed $value, bool $isRoot = false)
    {
        $processedValue = parent::processValue($value, $isRoot);

        if (!$processedValue instanceof Definition || $processedValue->isAbstract() || !$processedValue->getClass()) {
            return $processedValue;
        }

        if (!$reflectionClass = $this->container->getReflectionClass($processedValue->getClass(), false)) {
            return $processedValue;
        }

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->getAttributes(Required::class)) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                if (!$parameter->getAttributes(Cache::class)) {
                    continue;
                }

                $methodCalls = $processedValue->getMethodCalls();
                foreach ($methodCalls as &$methodCall) {
                    [$methodName, $arguments] = $methodCall;
                    if ($methodName !== $method->getName()) {
                        continue;
                    }

                    /** @var Definition $argumentDef */
                    $argumentDef = $arguments[$parameter->getPosition()];
                    $argumentClass = $argumentDef->getClass();
                    if (!$argumentClass) {
                        continue;
                    }

                    $argumentClassReflection = $this->container->getReflectionClass($argumentClass);
                    if (!$argumentClassReflection) {
                        continue;
                    }

                    $proxyClassData = ProxyGenerator::generate($argumentClass, $argumentClassReflection);
                    $arguments[$parameter->getPosition()] = $this->getProxyDefinition($proxyClassData, $argumentDef);
                    $methodCall = [$methodName, $arguments];

                    $this->cacheClass($proxyClassData);
                }

                $processedValue->setMethodCalls($methodCalls);
            }
        }

        foreach ($reflectionClass->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (!$parameter->getAttributes(Cache::class)) {
                continue;
            }

            /** @var Definition $argumentDef */
            $argumentDef = $processedValue->getArgument($parameter->getPosition());
            $argumentClass = $argumentDef->getClass();
            if (!$argumentClass) {
                continue;
            }

            $argumentClassReflection = $this->container->getReflectionClass($argumentClass);
            if (!$argumentClassReflection) {
                continue;
            }

            $proxyClassData = ProxyGenerator::generate($argumentClass, $argumentClassReflection);
            $proxyDef = $this->getProxyDefinition($proxyClassData, $argumentDef);

            try {
                $processedValue->replaceArgument('$'.$parameter->getName(), $proxyDef);
            } catch (OutOfBoundsException) {
                $processedValue->replaceArgument($parameter->getPosition(), $proxyDef);
            }

            $this->cacheClass($proxyClassData);
        }

        return $processedValue;
    }

    private function getConfigCacheFactory(): ConfigCacheFactoryInterface
    {
        return $this->configCacheFactory ??= new ConfigCacheFactory(
            (bool) $this->container->getParameter('kernel.debug')
        );
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
            ->setMethodCalls($argumentDefinition->getMethodCalls())
            ->setTags($argumentDefinition->getTags())
            ->setProperties($argumentDefinition->getProperties())
        ;
    }

    private function cacheClass(ProxyClassData $proxyClassData): void
    {
        $this->getConfigCacheFactory()->cache(
            "{$this->getCacheDir()}/$proxyClassData->className.php",
            static function (ConfigCacheInterface $cache) use ($proxyClassData) {
                $cache->write("<?php\n\n{$proxyClassData->body}\n");
            }
        );
    }
}
