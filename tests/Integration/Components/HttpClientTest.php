<?php

namespace ZipkinTests\Integration\Reporters\Http;

use Zipkin\TracingBuilder;
use HttpTest\HttpTestServer;
use Zipkin\Reporters\InMemory;
use PHPUnit\Framework\TestCase;
use Zipkin\Samplers\BinarySampler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\CurlHttpClient;
use ZipkinBundle\Components\HttpClient\HttpClient;

final class HttpClientTest extends TestCase
{
    private function createTracing()
    {
        $inMemory = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($inMemory)
            ->build();

        return [$tracing, $inMemory];
    }

    public function testHttpRequestContainsPropagationHeaders()
    {
        $t = $this;

        $server = HttpTestServer::create(
            function (RequestInterface $request, ResponseInterface &$response) use ($t) {
                $t->assertEquals('GET', $request->getMethod());
                $t->assertEquals('header-value', $request->getHeader('x-header-key')[0]);
                $t->assertTrue($request->hasHeader('x-b3-traceid'));
                sleep(3);
                $response = $response->withStatus(202);
            }
        );

        $server->start();
        try {
            list($tracing, $inMemory) = $this->createTracing();
            $httpClient = new CurlHttpClient();
            $tracedClient = new HttpClient($httpClient, $tracing);
            $response = $tracedClient->request("GET", $server->getUrl(), [
                'headers' => [
                    'x-header-key' => 'header-value',
                ],
            ]);
            $this->assertEquals(202, $response->getStatusCode());

            $tracing->getTracer()->flush();
            $spans = $inMemory->flush();
            $this->assertCount(1, $spans);

            $span = $spans[0]->toArray();
            $this->assertEquals('http/get', $span['name']);
        } finally {
            $server->stop();
        }
    }

    public function testHttpCanceledRequestIsTaggedWithError()
    {
        $t = $this;

        $server = HttpTestServer::create(
            function (RequestInterface $request, ResponseInterface &$response) use ($t) {
                $t->assertEquals('GET', $request->getMethod());
                sleep(2);
                $response = $response->withStatus(202);
            }
        );

        $server->start();
        try {
            list($tracing, $inMemory) = $this->createTracing();

            $httpClient = new CurlHttpClient();
            $tracedClient = new HttpClient($httpClient, $tracing);
            $response = $tracedClient->request("GET", $server->getUrl());
            $response->cancel();

            $tracing->getTracer()->flush();
            $spans = $inMemory->flush();
            $this->assertCount(1, $spans);
            $span = $spans[0]->toArray();
            $this->assertEquals('http/get', $span['name']);
            $this->assertEquals('Response has been canceled.', $span['tags']['error']);
        } finally {
            $server->stop();
        }
    }
}
