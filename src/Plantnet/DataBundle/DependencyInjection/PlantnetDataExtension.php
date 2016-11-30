<?php

namespace Plantnet\DataBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\DependencyInjection\Definition;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class PlantnetDataExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

       // $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
       // $loader->load('services.xml');

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');


        $definition = new Definition('Plantnet\DataBundle\Twig\Extension\TwigPregExtension');
        // this is the most important part. Later in the startup process TwigBundle
        // searches through the container and registres all services taged as twig.extension.
        $definition->addTag('twig.extension');
        $container->setDefinition('twig_preg_extension', $definition);
    }

    public function getAlias() {
        return 'plantnet_data'; // that's how we'll call this extension in configuration files
    }

}
