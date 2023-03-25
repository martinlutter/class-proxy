<?php

namespace App\Proxy;

use App\Proxy\Model\ProxyClassData;
use Symfony\Contracts\Service\Attribute\Required;

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
            if ($method->isConstructor() || $method->getAttributes(Required::class)
                || ($method->getReturnType() instanceof \ReflectionNamedType
                    && $method->getReturnType()->getName() === 'void')
            ) {
                continue;
            }

            $parametersWithTypes = [];
            $parametersWithoutTypes = [];
            foreach ($method->getParameters() as $parameter) {
                $parametersWithTypes[] = self::stringifyTypes($parameter->getType()).' '
                    .($parameter->isPassedByReference() ? '&' : '')
                    .($parameter->isVariadic() ? '...' : '')
                    .'$'.$parameter->getName()
                    .($parameter->isDefaultValueAvailable() ? (' = '.$parameter->getDefaultValue()) : '');
                $parametersWithoutTypes[] = '$'.$parameter->getName();
            }

            $parametersWithTypesString = implode(', ', $parametersWithTypes);
            $parametersWithoutTypesString = implode(', ', $parametersWithoutTypes);
            $returnType = $method->getReturnType() ? (': '.self::stringifyTypes($method->getReturnType())) : '';

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

    private static function stringifyTypes(?\ReflectionType $reflectionType): string
    {
        if (!$reflectionType) {
            return '';
        }

        $types = [];
        if ($reflectionType instanceof \ReflectionIntersectionType || $reflectionType instanceof \ReflectionUnionType) {
            foreach ($reflectionType->getTypes() as $type) {
                $types[] = self::stringifyTypes($type);
            }

            return implode(
                match ($reflectionType::class) {
                    \ReflectionIntersectionType::class => '&',
                    \ReflectionUnionType::class => '|',
                },
                $types
            );
        }

        if ($reflectionType instanceof \ReflectionNamedType) {
            return (!$reflectionType->isBuiltin() ? '\\' : '').$reflectionType;
        }

        throw new \InvalidArgumentException(get_class($reflectionType).' not supported');
    }
}
