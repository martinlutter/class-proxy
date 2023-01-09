<?php

namespace App\Util;

use InvalidArgumentException;

class Autoloader
{
    /**
     * Resolves proxy class name to a filename based on the following pattern.
     *
     * 1. Remove Proxy namespace from class name.
     * 2. Remove namespace separators from remaining class name.
     * 3. Return PHP filename from proxy-dir with the result from 2.
     *
     * @psalm-param class-string $className
     *
     * @throws InvalidArgumentException
     */
    public static function resolveFile(string $proxyDir, string $proxyNamespace, string $className): string
    {
        if (strpos($className, $proxyNamespace) !== 0) {
            throw new InvalidArgumentException("$className is not a proxy class from $proxyNamespace");
        }

        // remove proxy namespace from class name
        $classNameRelativeToProxyNamespace = substr($className, strlen($proxyNamespace));

        // remove namespace separators from remaining class name
        $fileName = str_replace('\\', '', $classNameRelativeToProxyNamespace);

        return $proxyDir . DIRECTORY_SEPARATOR . $fileName . '.php';
    }

    /**
     * @psalm-suppress UnresolvableInclude
     * @psalm-suppress ArgumentTypeCoercion
     *
     * @throws InvalidArgumentException
     */
    public static function register(string $proxyDir, string $proxyNamespace): \Closure
    {
        $proxyNamespace = ltrim($proxyNamespace, '\\');

        $autoloader = static function (string $className) use ($proxyDir, $proxyNamespace): void {
            if ($proxyNamespace === '') {
                return;
            }

            if (strpos($className, $proxyNamespace) !== 0) {
                return;
            }

            /** @psalm-param class-string $className */
            $file = Autoloader::resolveFile($proxyDir, $proxyNamespace, $className);

            require $file;
        };

        spl_autoload_register($autoloader);

        return $autoloader;
    }
}
