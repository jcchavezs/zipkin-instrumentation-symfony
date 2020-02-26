<?php

namespace ZipkinBundle;

use Zipkin\Sampler;
use Zipkin\Tracing;
use Zipkin\Endpoint;
use Zipkin\Reporter;
use Zipkin\Reporters\Log;
use Zipkin\Reporters\Http;
use Zipkin\Reporters\Noop;
use Zipkin\TracingBuilder;
use ZipkinBundle\InvalidSampler;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Samplers\PercentageSampler;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class TracingFactory
{
    /**
     * @param ContainerInterface $container
     * @return Tracing
     */
    public static function build(ContainerInterface $container)
    {
        $isNoop = (bool) $container->getParameter('zipkin.noop');

        return TracingBuilder::create()
                ->havingLocalServiceName(self::buildServiceName($container))
                ->havingSampler(self::buildSampler($container))
                ->havingReporter(self::buildReporter($container))
                ->havingLocalEndpoint(self::buildEndpoint($container))
                ->beingNoop($isNoop)
                ->build();
    }

    /**
     * @param ContainerInterface $container
     * @return Endpoint
     */
    private static function buildEndpoint(ContainerInterface $container)
    {
        return Endpoint::createFromGlobals()->withServiceName(self::buildServiceName($container));
    }

    /**
     * @param ContainerInterface $container
     * @return Reporter
     */
    private static function buildReporter(ContainerInterface $container)
    {
        $reporterName = $container->getParameter('zipkin.reporter.type');
        $metricsName = $container->getParameter('zipkin.reporter.metrics');

        switch ($reporterName) {
            default:
            case 'log':
                $logger = $container->get('logger');
                return new Log($logger);
            case 'noop':
                return new Noop();
                break;
            case 'http':
                return new Http(
                    null,
                    $container->getParameter('zipkin.reporter.http'),
                    $metricsName === null ? null : $container->get($metricsName)
                );
        }
    }

    /**
     * @param ContainerInterface $container
     * @return string
     */
    private static function buildServiceName(ContainerInterface $container)
    {
        return $container->getParameter('zipkin.service_name') ?: PHP_SAPI;
    }

    /**
     * @param ContainerInterface $container
     * @return Sampler
     * @throws InvalidSampler when cannot resolve a sampler
     */
    private static function buildSampler(ContainerInterface $container)
    {
        if (!$container->hasParameter('zipkin.sampler.type')) {
            return BinarySampler::createAsAlwaysSample();
        }

        $samplerType = $container->getParameter('zipkin.sampler.type');

        switch ($samplerType) {
            case 'always':
                return BinarySampler::createAsAlwaysSample();
            case 'never':
                return BinarySampler::createAsNeverSample();
            case 'path':
                return $container->get('zipkin.sampler.path');
            case 'route':
                return $container->get('zipkin.sampler.route');
            case 'percentage':
                return PercentageSampler::create(
                    (float) $container->getParameter('zipkin.sampler.percentage')
                );
            case 'custom':
                $serviceId = $container->getParameter('zipkin.sampler.custom');
                if (!$container->has($serviceId)) {
                    throw InvalidSampler::forUnkownService($serviceId);
                }

                $sampler = $container->get($serviceId);
                if ($sampler instanceof Sampler) {
                    return $sampler;
                }

                throw InvalidSampler::forInvalidCustomSampler(get_class($sampler));
        }

        throw InvalidSampler::forInvalidSamplerType($samplerType);
    }
}
