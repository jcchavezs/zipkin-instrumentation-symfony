<?php

namespace ZipkinBundle\Tests\Unit\Components\HttpClient;

use Zipkin\Recorder;
use Zipkin\TracingBuilder;
use Zipkin\Reporters\InMemory;
use PHPUnit\Framework\TestCase;
use Zipkin\Samplers\BinarySampler;
use Symfony\Component\HttpClient\MockHttpClient;
use ZipkinBundle\Components\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpClientTest extends TestCase
{
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
}
