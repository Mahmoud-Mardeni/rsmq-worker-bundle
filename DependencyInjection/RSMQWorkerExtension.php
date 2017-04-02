<?php

namespace Redis\RSMQWorkerBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class RSMQWorkerExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $container->setParameter('rsmq_worker_config', $config);
    }

    /**
     * Allow an extension to prepend the extension configurations.
     *
     * @param ContainerBuilder $container
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['GuzzleBundle'])) {
            throw new LogicException(sprintf('GuzzleBundle is not registered'));
        }

        $configs = $container->getExtensionConfig($this->getAlias());
        $port = $configs[0]['port'];

        if ($port) {
            $base_url = $configs[0]['protocol'] . '://' . $configs[0]['host'] . ':' . $configs[0]['port'];
        } else {
            $base_url = $configs[0]['protocol'] . '://' . $configs[0]['host'];
        }

        $config = array('clients' =>
            array('rsmq_worker_api' =>
                array('base_url' => $base_url)
            )
        );

        $container->prependExtensionConfig('guzzle', $config);
    }
}
