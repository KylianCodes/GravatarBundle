<?php

declare(strict_types=1);

namespace KylianCodes\GravatarBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Defines and validates the bundle configuration structure.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gravatar');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('api_key')
                    ->defaultNull()
                ->end()
                ->integerNode('size')
                    ->defaultValue(80)
                    ->min(1)
                    ->max(2048)
                ->end()
                ->enumNode('rating')
                    ->values(['g', 'pg', 'r', 'x'])
                    ->defaultValue('g')
                ->end()
                ->scalarNode('default')
                    ->defaultValue('mp')
                ->end()
                ->enumNode('format')
                    ->values(['url', 'base64'])
                    ->defaultValue('url')
                ->end()
                ->arrayNode('cache')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                        ->end()
                        ->integerNode('ttl')
                            ->defaultValue(3600)
                            ->min(0)
                        ->end()
                        ->scalarNode('pool')
                            ->defaultValue('cache.app')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
