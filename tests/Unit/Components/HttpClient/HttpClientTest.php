<?php

namespace ZipkinBundle\Tests\Unit\Components\HttpClient;

use Zipkin\Sampler;
use Zipkin\TracingBuilder;
use Zipkin\Reporters\InMemory;
use PHPUnit\Framework\TestCase;
use Zipkin\Samplers\BinarySampler;
use Symfony\Component\HttpClient\MockHttpClient;
use ZipkinBundle\Components\HttpClient\HttpClient;
use Symfony\Component\HttpClient\TraceableHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Exception\ClientException;

final class HttpClientTest extends TestCase
{
    /**
     * @dataProvider requestInfoForRequestHeaders
     */
    public function testPropagationHeadersAreInjectedDespiteSampling(array $requestOptions, array $expectedRequestHeaders)
    {
        $this->testPropagationHeadersAreInjected(BinarySampler::createAsAlwaysSample(), $requestOptions, $expectedHeaders);
        $this->testPropagationHeadersAreInjected(BinarySampler::createAsNeverSample(), $requestOptions, $expectedHeaders);
    }

    private function testPropagationHeadersAreInjected(Sampler $sampler, array $requestOptions, array $expectedRequestHeaders)
    {
        $response = new MockResponse('', ['http_code' => 200]);
        $client = new TraceableHttpClient(new MockHttpClient($response));
        $inMemory = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler($sampler)
            ->havingReporter($inMemory)
            ->build();
        $tracedClient = new HttpClient($client, $tracing);
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
        $inMemory = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($inMemory)
            ->build();
        $tracedClient = new HttpClient($client, $tracing);
        $response = $tracedClient->request('GET', 'http://test.com');
        $tracing->getTracer()->flush();
        $spans = $inMemory->flush();
        $this->assertCount(1, $spans);
        $span = $spans[0]->toArray();
        $this->assertEquals('http/get', $span['name']);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHttpCallFails()
    {
        $response = new MockResponse('', ['http_code' => 403]);
        $client = new MockHttpClient($response);
        $inMemory = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($inMemory)
            ->build();
        $tracedClient = new HttpClient($client, $tracing);
        $response = $tracedClient->request('GET', 'http://test.com');
        try {
            $response->getContent();
            $this->fail("ClientException should be thrown");
        } catch (ClientException $e) {
        }

        $tracing->getTracer()->flush();
        $spans = $inMemory->flush();
        $this->assertCount(1, $spans);
        $span = $spans[0]->toArray();
        $this->assertEquals('http/get', $span['name']);
        $this->assertEquals('403', $span['tags']['error']);
    }
}
