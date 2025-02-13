<?php

declare(strict_types=1);

namespace ClassProxy;

use ClassProxy\DependencyInjection\Compiler\ProxyPass;
use ClassProxy\Proxy\Autoloader;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @psalm-suppress UnusedClass
 */
class ClassProxyBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ProxyPass());
        $container->addCompilerPass(new ProxyPass(), PassConfig::TYPE_REMOVE);
    }

    public function boot(): void
    {
        $cacheDir = $this->container?->getParameter('kernel.cache_dir').'/ProxyCache';
        Autoloader::register($cacheDir, 'ProxyCache\\Generated');
    }
}
