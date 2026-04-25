<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\DependencyInjection;

use KylianCodes\GravatarBundle\DataCollector\GravatarDataCollector;
use KylianCodes\GravatarBundle\Service\GravatarService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Loads and manages the bundle configuration.
 */
class GravatarExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.yaml');

        $definition = $container->getDefinition(GravatarService::class);
        $definition->setArgument('$size', $config['size']);
        $definition->setArgument('$rating', $config['rating']);
        $definition->setArgument('$default', $config['default']);
        $definition->setArgument('$format', $config['format']);
        $definition->setArgument('$cacheTtl', $config['cache']['ttl']);
        $definition->setArgument('$apiKey', $config['api_key']);

        if ($config['cache']['enabled']) {
            $definition->setArgument('$cache', new Reference($config['cache']['pool']));
        }

        if ($container->hasParameter('kernel.debug') && $container->getParameter('kernel.debug')) {
            $definition->setArgument('$collector', new Reference(GravatarDataCollector::class));
        }
    }
}
