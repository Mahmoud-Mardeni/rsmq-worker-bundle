<?php

namespace Redis\RSMQWorkerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('rsmq_worker');

        $rootNode->children()
            ->scalarNode('process_name')->defaultValue('')->end()
            ->scalarNode('host')->defaultValue('')->end()
            ->scalarNode('port')->defaultValue('')->end()
            ->scalarNode('protocol')->defaultValue('')->end()
            ->arrayNode('allowedServers')
            ->canBeUnset()->prototype('scalar')->end()->end()
            ->end();

        return $treeBuilder;
    }
}
