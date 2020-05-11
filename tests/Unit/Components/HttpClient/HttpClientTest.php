<?php

namespace ZipkinBundle\Tests\Unit\Components\HttpClient;

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
     * @dataProvider requestInfoForHeaders
     */
    public function testPropagationHeadersAreInjected(array $requestInfo)
    {
        $response = new MockResponse('', $requestInfo);
        $client = new TraceableHttpClient(new MockHttpClient($response));
        $inMemory = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($inMemory)
            ->build();
        $tracedClient = new HttpClient($client, $tracing);
        $response = $tracedClient->request('GET', 'http://test.com');
        $requestHeaders = $client->getTracedRequests()[0]['options']['headers'];
        $this->assertArrayHasKey('x-b3-traceid', $requestHeaders);
        $this->assertArrayHasKey('x-b3-spanid', $requestHeaders);
        $this->assertArrayHasKey('x-b3-sampled', $requestHeaders);
    }

    public function requestInfoForHeaders(): array
    {
        return [
            [
                ['http_code' => 200, 'headers' => ['request_id' => '123abc']],
                ['x-b3-traceid', 'x-b3-spanid', 'x-b3-sampled', 'request_id']
            ],
            [
                ['http_code' => 200],
                ['x-b3-traceid', 'x-b3-spanid', 'x-b3-sampled']
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
        $span = $spans[0]->toArray();
        $this->assertCount(1, $spans);
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
        $span = $spans[0]->toArray();
        $this->assertCount(1, $spans);
        $this->assertEquals('http/get', $span['name']);
        $this->assertEquals('403', $span['tags']['error']);
    }
}
