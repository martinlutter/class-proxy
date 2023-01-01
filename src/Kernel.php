<?php

namespace App;

use App\DependencyInjection\Compiler\ProxyPass;
use App\Util\Autoloader;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new ProxyPass(), PassConfig::TYPE_REMOVE);
    }

    public function boot()
    {
        parent::boot();

        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir').'/ProxyCache';
        Autoloader::register($cacheDir, 'ProxyCache\\Generated');
    }
}
