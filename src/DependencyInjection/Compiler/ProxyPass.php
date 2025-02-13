<?php

declare(strict_types=1);

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
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Service\Attribute\Required;

class ProxyPass extends AbstractRecursivePass
{
    private ?ConfigCacheFactoryInterface $configCacheFactory = null;
    private static array $methodReferenceMap = [];
    private static array $constructorReferenceMap = [];

    protected function processValue(mixed $value, bool $isRoot = false)
    {
        $processedValue = parent::processValue($value, $isRoot);

        if (!$processedValue instanceof Definition || $processedValue->isAbstract() || $processedValue->getClass() === null) {
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

                    /** @var Definition|Reference $argumentDef */
                    $argumentDef = $arguments[$parameter->getPosition()];
                    if ($argumentDef instanceof Reference) {
                        //PassConfig::BEFORE_OPTIMIZATION
                        $id = (string) $argumentDef;
                        self::$methodReferenceMap[$this->currentId][$method->getName()][$parameter->getPosition()] = $id;

                        continue;
                    }

                    $argumentClass = $argumentDef->getClass();
                    if ($argumentClass === null) {
                        continue;
                    }

                    $argumentClassReflection = $this->container->getReflectionClass($argumentClass);
                    if (!$argumentClassReflection) {
                        continue;
                    }

                    $proxyClassData = ProxyGenerator::generate($argumentClass, $argumentClassReflection);
                    $arguments[$parameter->getPosition()] = $this->getProxyDefinition(
                        $proxyClassData,
                        $argumentDef,
                        static::$methodReferenceMap[$this->currentId][$method->getName()][$parameter->getPosition()]
                    );
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

            /** @var Definition|Reference $argumentDef */
            $argumentDef = $processedValue->getArgument($parameter->getPosition());
            if ($argumentDef instanceof Reference) {
                //PassConfig::BEFORE_OPTIMIZATION
                $id = (string) $argumentDef;
                self::$constructorReferenceMap[$this->currentId][$parameter->getPosition()] = $id;

                continue;
            }

            $argumentClass = $argumentDef->getClass();
            if ($argumentClass === null) {
                continue;
            }

            $argumentClassReflection = $this->container->getReflectionClass($argumentClass);
            if (!$argumentClassReflection) {
                continue;
            }

            $proxyClassData = ProxyGenerator::generate($argumentClass, $argumentClassReflection);
            $proxyDef = $this->getProxyDefinition(
                $proxyClassData,
                $argumentDef,
                self::$constructorReferenceMap[$this->currentId][$parameter->getPosition()]
            );

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

    private function getProxyDefinition(ProxyClassData $proxyClassData, Definition $argumentDefinition, string $argumentServiceId): Definition
    {
        $proxyServiceId = "proxy_cache.$proxyClassData->hash.$argumentServiceId";
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
