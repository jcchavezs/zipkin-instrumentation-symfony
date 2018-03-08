<?php

namespace ZipkinBundle\Tests\Unit;

use PHPUnit_Framework_TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Zipkin\Tracing;
use ZipkinBundle\TracingFactory;

final class TracingFactoryTest extends PHPUnit_Framework_TestCase
{
    const DEFAULT_PARAMETER_BAG = [
        'zipkin.noop' => false,
        'zipkin.service_name' => 'symfony',
        'zipkin.sampler.type' => 'always',
        'zipkin.reporter.type' => 'log',
        'zipkin.reporter.metrics' => null
    ];

    /**
     * @dataProvider getTracingFactoryValues
     */
    public function testTracingFactorySuccess($isNoop)
    {
        $parameterBag = new ParameterBag(array_merge(self::DEFAULT_PARAMETER_BAG, [
            'zipkin.noop' => $isNoop,
        ]));

        $container = new Container($parameterBag);
        $container->set('logger', new NullLogger());
        $tracing = TracingFactory::build($container);
        $this->assertInstanceOf(Tracing::class, $tracing);
        $this->assertSame($isNoop, $tracing->isNoop());
    }

    public function getTracingFactoryValues()
    {
        return [
            [true],
            [false],
        ];
    }
}
