<?php

namespace App\DependencyInjection\Compiler;

use App\DependencyInjection\Attribute\Cache;
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
                dump('cache attribute found');

                /** @var Definition $argumentDef */
                $argumentDef = $processValue->getArgument($parameter->getPosition());
                dump($argumentDef->getClass());
                $proxyClassString = <<<PROXY
namespace <namespace>;

class <className> extends \<originalClass> {
    private array \$_cachedData = [];
    
    <publicMethods>
}
PROXY;

                $generatedClassNamespace = 'ProxyCache\\Generated';
                $proxyClassStringComplete = strtr($proxyClassString, [
                    '<namespace>' => $generatedClassNamespace,
                    '<originalClass>' => $argumentDef->getClass(),
                    '<publicMethods>' => $this->generatePublicMethods(
                        $this->container->getReflectionClass($argumentDef->getClass())
                    ),
                ]);

                $classHash = substr(md5($proxyClassStringComplete), 0, 16);
                $generatedClassName = 'GeneratedProxyClass'.$classHash;
                $generatedClassFCQN = $generatedClassNamespace.'\\'.$generatedClassName;
                $proxyClassStringComplete = strtr($proxyClassStringComplete, ['<className>' => $generatedClassName]);

                eval($proxyClassStringComplete);

                $proxyDef = $this->container
                    ->register("proxy_cache.$classHash", $generatedClassFCQN)
                    ->setArguments($argumentDef->getArguments());
                try {
                    $processValue->replaceArgument('$'.$parameter->getName(), $proxyDef);
                } catch (OutOfBoundsException) {
                    $processValue->replaceArgument($parameter->getPosition(), $proxyDef);
                }

                //todo: should move this to a cache warmer,
                // remember $generatedClassName & $proxyClassStringComplete pair
                $this->getConfigCacheFactory()->cache(
                    $this->getCacheDir().'/'.$generatedClassName.'.php',
                    function (ConfigCacheInterface $cache) use ($proxyClassStringComplete) {
                        $cache->write("<?php\n\n$proxyClassStringComplete\n");
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

    private function generatePublicMethods(\ReflectionClass $reflectionClass): string
    {
        $result = '';

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (!$method->hasReturnType()
                || ($method->getReturnType() instanceof \ReflectionNamedType
                    && $method->getReturnType()->getName() === 'void')
            ) {
                continue;
            }

            $parametersWithTypes = [];
            $parametersWithoutTypes = [];
            foreach ($method->getParameters() as $parameter) {
                if ($parameter->getType() && !$parameter->getType() instanceof \ReflectionNamedType) {
                    throw new \LogicException('Composite types not supported for now');
                }

                $parametersWithTypes[] = ($parameter->allowsNull() ? '?' : '').$parameter->getType()?->getName()
                    .' $'.$parameter->getName()
                    .($parameter->isDefaultValueAvailable() ? (' = '.$parameter->getDefaultValue()) : '');
                $parametersWithoutTypes[] = '$'.$parameter->getName();
            }

            $parametersWithTypesString = implode(', ', $parametersWithTypes);
            $parametersWithoutTypesString = implode(', ', $parametersWithoutTypes);
            $returnType = $method->getReturnType() instanceof \ReflectionNamedType
                ? (': '.$method->getReturnType()->getName()) : '';

            //todo: replace 'bla' with some hash of passed arguments
            $result .= <<<METHOD
public function {$method->getName()}($parametersWithTypesString)$returnType
{
    if (isset(\$this->_cachedData['bla'])) {
        return \$this->_cachedData['bla'];
    }
    
    \$result = parent::{$method->getName()}($parametersWithoutTypesString);
    
    return \$this->_cachedData['bla'] = \$result;
}

METHOD;

        }

        return $result;
    }
}
