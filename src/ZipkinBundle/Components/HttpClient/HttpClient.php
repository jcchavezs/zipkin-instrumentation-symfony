<?php

namespace ZipkinBundle\Components\HttpClient;

use Throwable;
use Zipkin\Tracer;
use Zipkin\Tracing;
use Zipkin\Propagation\Map;
use const Zipkin\Tags\ERROR;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Zipkin\SpanCustomizerShield;
use ZipkinBundle\Components\HttpClient\ContextRetrievers\InScope;

final class HttpClient implements HttpClientInterface
{
    /**
     * @var HttpClientInterface
     */
    private $delegate;

    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @var callable
     */
    private $injector;

    /**
     * @var HttpParser
     */
    private $httpParser;

    /**
     * @var ContextRetriever
     */
    private $contextRetriever;

    public function __construct(
        HttpClientInterface $client,
        Tracing $tracing,
        ContextRetriever $contextRetriever = null,
        HttpParser $httpParser = null
    ) {
        $this->delegate = $client;
        $this->tracer = $tracing->getTracer();
        $this->injector = $tracing->getPropagation()->getInjector(new Map());
        $this->httpParser = $httpParser ?? new DefaultHttpParser();
        $this->contextRetriever = $contextRetriever ?: new InScope($this->tracer);
    }

    /**
     * {@inheritdoc}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if ($this->contextRetriever === null) {
            $span = $this->tracer->nextSpan();
        } else {
            $context = ($this->contextRetriever)($options);
            $span = $this->tracer->nextSpan($context);
        }

        $spanCustomizer = new SpanCustomizerShield($span);
        $this->httpParser->request($method, $url, $options, $spanCustomizer);

        try {
            $headers = [];
            ($this->injector)($span->getContext(), $headers);
            $options['headers'] = $headers + $options['headers'];
            $response = $this->delegate->request($method, $url, $options);
            $this->httpParser->response($response, $spanCustomizer);
            return $response;
        } catch (Throwable $e) {
            $span->tag(ERROR, $e->getMessage());
            throw $e;
        } finally {
            $span->finish();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->delegate->stream($responses, $timeout);
    }
}
