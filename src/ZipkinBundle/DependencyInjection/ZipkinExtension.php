<?php

namespace ZipkinBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;

final class ZipkinExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter(
            'zipkin.noop',
            $config['noop']
        );

        $container->setParameter(
            'zipkin.service_name',
            $config['service_name']
        );

        $container->setParameter(
            'zipkin.sampler.type',
            $config['sampler']['type']
        );

        $container->setParameter(
            'zipkin.sampler.percentage',
            $config['sampler']['percentage']
        );

        $container->setParameter(
            'zipkin.sampler.route.included_routes',
            $config['sampler']['route']['included_routes']
        );

        $container->setParameter(
            'zipkin.sampler.route.excluded_routes',
            $config['sampler']['route']['excluded_routes']
        );

        $container->setParameter(
            'zipkin.sampler.path.included_paths',
            $config['sampler']['path']['included_paths']
        );

        $container->setParameter(
            'zipkin.sampler.path.excluded_paths',
            $config['sampler']['path']['excluded_paths']
        );

        $container->setParameter(
            'zipkin.reporter.type',
            $config['reporter']['type']
        );

        $container->setParameter(
            'zipkin.reporter.metrics',
            $config['reporter']['metrics']
        );

        $container->setParameter(
            'zipkin.reporter.http',
            $config['reporter']['http']
        );
    }
}
