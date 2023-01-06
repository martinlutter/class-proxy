<?php

namespace App\Util;

use App\Util\Model\ProxyClassData;

class ProxyGenerator
{
    private const BASE_PROXY_BODY = <<<PROXY
namespace <namespace>;

class <className> extends \<originalClass> {
    private array \$_cachedData = [];

    <publicMethods>
}
PROXY;

    public static function generate(string $originalClass, \ReflectionClass $originalClassReflection): ProxyClassData
    {
        $generatedClassNamespace = 'ProxyCache\\Generated';
        $proxyClassStringComplete = strtr(self::BASE_PROXY_BODY, [
            '<namespace>' => $generatedClassNamespace,
            '<originalClass>' => $originalClass,
            '<publicMethods>' => self::generatePublicMethods($originalClassReflection),
        ]);

        $classHash = substr(md5($proxyClassStringComplete), 0, 16);
        $generatedClassName = 'GeneratedProxyClass'.$classHash;
        $generatedClassFCQN = $generatedClassNamespace.'\\'.$generatedClassName;
        $proxyClassStringComplete = strtr($proxyClassStringComplete, ['<className>' => $generatedClassName]);

        return new ProxyClassData($generatedClassName, $classHash, $generatedClassFCQN, $proxyClassStringComplete);
    }

    private static function generatePublicMethods(\ReflectionClass $reflectionClass): string
    {
        $result = '';

        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() ||
                ($method->getReturnType() instanceof \ReflectionNamedType
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

            $result .= <<<METHOD
public function {$method->getName()}($parametersWithTypesString)$returnType
{
    \$methodName = '{$method->getName()}';
    \$argsHash = md5(serialize(func_get_args()));
    if (isset(\$this->_cachedData[\$methodName][\$argsHash])) {
        return \$this->_cachedData[\$methodName][\$argsHash];
    }

    \$result = parent::\$methodName($parametersWithoutTypesString);

    return \$this->_cachedData[\$methodName][\$argsHash] = \$result;
}

METHOD;

        }

        return $result;
    }
}
