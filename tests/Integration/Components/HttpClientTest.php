<?php

namespace ZipkinTests\Integration\Reporters\Http;

use Zipkin\TracingBuilder;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Reporters\InMemory;
use Zipkin\Instrumentation\Http\Client\HttpClientTracing;
use ZipkinBundle\Components\HttpClient\HttpClient;
use ZipkinBundle\Components\HttpClient\DefaultParser;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use RingCentral\Psr7\BufferStream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;
use PHPUnit\Framework\TestCase;
use HttpTest\HttpTestServer;

final class HttpClientTest extends TestCase
{
    private function createHttpTracing(): array
    {
        $reporter = new InMemory();
        $tracing = TracingBuilder::create()
            ->havingSampler(BinarySampler::createAsAlwaysSample())
            ->havingReporter($reporter)
            ->build();

        $httpTracing = new HttpClientTracing($tracing, new DefaultParser);

        return [$httpTracing, static function () use ($tracing, $reporter): array {
            $tracing->getTracer()->flush();
            return $reporter->flush();
        }];
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
            list($httpClientTracing, $flusher) = $this->createHttpTracing();
            $tracedClient = new HttpClient(new CurlHttpClient(), $httpClientTracing);
            $response = $tracedClient->request("GET", $server->getUrl(), [
                'headers' => [
                    'x-header-key' => 'header-value',
                ],
            ]);
            $this->assertEquals(202, $response->getStatusCode());

            $spans = $flusher();
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
            list($httpClientTracing, $flusher) = $this->createHttpTracing();

            $tracedClient = new HttpClient(new CurlHttpClient(), $httpClientTracing);
            $response = $tracedClient->request("GET", $server->getUrl());
            $response->cancel();

            $spans = $flusher();
            $this->assertCount(1, $spans);
            $span = $spans[0]->toArray();
            $this->assertEquals('http/get', $span['name']);
            $this->assertEquals('Response has been canceled.', $span['tags']['error']);
        } finally {
            $server->stop();
        }
    }

    public function testStreamingRequestsSuccess()
    {
        $t = $this;
        $server = HttpTestServer::create(
            function (RequestInterface $request, ResponseInterface &$response) use ($t) {
                $t->assertEquals('GET', $request->getMethod());
                $stream = new BufferStream();
                $response = $response->withBody($stream);
                $stream->write('Zipkin');
                sleep(1);
                $stream->write(' is');
                sleep(1);
                $stream->write(' awesome!');
                $response = $response->withStatus(202);
            }
        );

        $server->start();
        try {
            list($httpClientTracing, $flusher) = $this->createHttpTracing();

            $httpClient = new HttpClient(new NativeHttpClient(), $httpClientTracing);
            $response = $httpClient->request('GET', $server->getUrl());
            $chunks = [];
            foreach ($httpClient->stream($response) as $r => $chunk) {
                $chunks[] = $chunk->getContent();
            }
            $this->assertSame($response, $r);
            $this->assertSame('Zipkin is awesome!', implode('', $chunks));

            $spans = $flusher();
            $this->assertCount(1, $spans);
            $span = $spans[0]->toArray();
            $this->assertEquals('http/get', $span['name']);
        } finally {
            $server->stop();
        }
    }
}
