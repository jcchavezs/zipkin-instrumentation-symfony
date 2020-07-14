<?php

namespace ZipkinBundle\Tests\Unit\Components\HttpClient;

use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Sampler;
use Zipkin\Reporters\InMemory;
use Zipkin\Instrumentation\Http\Client\HttpClientTracing;
use ZipkinBundle\Components\HttpClient\HttpClient;
use ZipkinBundle\Components\HttpClient\DefaultParser;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Exception\ClientException;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    private static function createHttpTracing(Sampler $sampler = null): array
    {
        $reporter = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler($sampler ?? BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();

        $httpTracing = new HttpClientTracing($tracing, new DefaultParser);

        return [$httpTracing, static function () use ($tracing, $reporter): array {
            $tracing->getTracer()->flush();
            return $reporter->flush();
        }];
    }

    /**
     * @dataProvider requestInfoForRequestHeaders
     */
    public function testPropagationHeadersAreInjectedDespiteSampling(
        array $requestOptions,
        array $expectedRequestHeaders
    ) {
        $this->checkPropagationHeadersAreInjected(
            BinarySampler::createAsAlwaysSample(),
            $requestOptions,
            $expectedRequestHeaders
        );

        $this->checkPropagationHeadersAreInjected(
            BinarySampler::createAsNeverSample(),
            $requestOptions,
            $expectedRequestHeaders
        );
    }

    public function checkPropagationHeadersAreInjected(
        Sampler $sampler,
        array $requestOptions,
        array $expectedRequestHeaders
    ) {
        $response = new MockResponse('', ['http_code' => 200]);
        $client = new TraceableHttpClient(new MockHttpClient($response));
        list($httpTracing) = self::createHttpTracing($sampler);
        $tracedClient = new HttpClient($client, $httpTracing);
        $tracedClient->request('GET', 'http://test.com', $requestOptions);
        $requestHeaders = $client->getTracedRequests()[0]['options']['headers'];
        foreach ($expectedRequestHeaders as $header) {
            $this->assertArrayHasKey($header, $requestHeaders);
        }
    }

    public function requestInfoForRequestHeaders(): array
    {
        return [
            'with no headers' => [
                [],
                ['x-b3-traceid', 'x-b3-spanid', 'x-b3-sampled'],
            ],
            'with headers' => [
                ['headers' => ['request_id' => 'abc123']],
                ['x-b3-traceid', 'x-b3-spanid', 'x-b3-sampled', 'request_id'],
            ]
        ];
    }

    public function testHttpCallSuccess()
    {
        $response = new MockResponse('', ['http_code' => 200]);
        $client = new MockHttpClient($response);
        list($httpTracing, $flusher) = self::createHttpTracing();
        $tracedClient = new HttpClient($client, $httpTracing);
        $response = $tracedClient->request('GET', 'http://test.com');
        $spans = $flusher();
        $this->assertCount(1, $spans);
        $span = $spans[0]->toArray();
        $this->assertEquals('http/get', $span['name']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHttpCallFails()
    {
        $response = new MockResponse('', ['http_code' => 403]);
        $client = new MockHttpClient($response);
        list($httpTracing, $flusher) = self::createHttpTracing();
        $tracedClient = new HttpClient($client, $httpTracing);
        $response = $tracedClient->request('GET', 'http://test.com');
        try {
            $response->getContent();
            $this->fail("ClientException should be thrown");
        } catch (ClientException $e) {
        }

        $spans = $flusher();
        $this->assertCount(1, $spans);
        $span = $spans[0]->toArray();
        $this->assertEquals('http/get', $span['name']);
        $this->assertEquals('403', $span['tags']['error']);
    }
}
