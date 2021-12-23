<?php

namespace ZipkinBundle\Tests\Unit;

use stdClass;
use Zipkin\Tracing;
use Zipkin\Samplers\BinarySampler;
use ZipkinBundle\TracingFactory;
use ZipkinBundle\Exceptions\InvalidSampler;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Container;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class TracingFactoryTest extends TestCase
{
    private const DEFAULT_PARAMETER_BAG = [
        'zipkin.noop' => false,
        'zipkin.service_name' => 'symfony',
        'zipkin.sampler.type' => 'always',
        'zipkin.reporter.type' => 'log',
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

    public function testTracingCustomSamplerFailsForUnkownService()
    {
        $parameterBag = new ParameterBag(array_merge(self::DEFAULT_PARAMETER_BAG, [
            'zipkin.sampler.type' => 'custom',
            'zipkin.sampler.custom' => 'my_service'
        ]));

        $container = new Container($parameterBag);
        $container->set('logger', new NullLogger());
        $this->expectException(InvalidSampler::class, 'Unknown service with id: "my_service"');
        TracingFactory::build($container);
    }

    public function testTracingCustomSamplerFailsForInvalidSampler()
    {
        $parameterBag = new ParameterBag(array_merge(self::DEFAULT_PARAMETER_BAG, [
            'zipkin.sampler.type' => 'custom',
            'zipkin.sampler.custom' => 'my_service'
        ]));

        $container = new Container($parameterBag);
        $container->set('logger', new NullLogger());
        $container->set('my_service', new stdClass());
        $this->expectException(InvalidSampler::class, 'Object of class "stdClass" is not a valid sampler');
        TracingFactory::build($container);
    }

    public function testTracingCustomSamplerSuccess()
    {
        $parameterBag = new ParameterBag(array_merge(self::DEFAULT_PARAMETER_BAG, [
            'zipkin.sampler.type' => 'custom',
            'zipkin.sampler.custom' => 'my_service'
        ]));

        $container = new Container($parameterBag);
        $container->set('logger', new NullLogger());
        $container->set('my_service', BinarySampler::createAsNeverSample());
        $tracing = TracingFactory::build($container);
        $this->assertInstanceOf(Tracing::class, $tracing);
    }
}
