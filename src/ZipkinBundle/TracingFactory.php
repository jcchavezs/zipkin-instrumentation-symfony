<?php

namespace ZipkinBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Zipkin\Endpoint;
use Zipkin\PercentageSampler;
use Zipkin\Reporter;
use Zipkin\Reporters\Http;
use Zipkin\Reporters\Log;
use Zipkin\Reporters\Noop;
use Zipkin\Sampler;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Tracing;
use Zipkin\TracingBuilder;

class TracingFactory
{
    /**
     * @param ContainerInterface $container
     * @return Tracing
     */
    public function build(ContainerInterface $container)
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
        $logger = $container->get('logger');

        switch ($reporterName) {
            default:
                return new Noop();
            case 'noop':
                return new Log($logger);
                break;
            case 'http':
                return new Http(null, $logger, $container->getParameter('zipkin.reporter.http'));
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
     */
    private static function buildSampler(ContainerInterface $container)
    {
        $samplerType = $container->getParameter('zipkin.sampler.type');

        switch ($samplerType) {
            case 'never':
                return BinarySampler::createAsNeverSample();
                break;
            case 'path':
                return $container->get('zipkin.sampler.path');
                break;
            case 'route':
                return $container->get('zipkin.sampler.route');
                break;
            case 'percentage':
                return PercentageSampler::create(
                    (float) $container->getParameter('zipkin.sampler.percentage.rate')
                );
                break;
            default:
                return BinarySampler::createAsAlwaysSample();
                break;
        }
    }
}
