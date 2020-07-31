<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Tracer;
use Zipkin\SpanCustomizerShield;
use Zipkin\SpanCustomizer;
use Zipkin\Span;
use Zipkin\Propagation\TraceContext;
use Zipkin\Propagation\Map;
use Zipkin\Instrumentation\Http\Client\Parser;
use Zipkin\Instrumentation\Http\Client\HttpClientTracing;
use TypeError;
use Throwable;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpClient\Response\ResponseStream;

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
     * @var Parser
     */
    private $httpParser;

    public function __construct(
        HttpClientInterface $client,
        HttpClientTracing $httpTracing
    ) {
        $this->delegate = $client;
        $this->tracer = $httpTracing->getTracing()->getTracer();
        $this->injector = $httpTracing->getTracing()->getPropagation()->getInjector(new Map());
        $this->httpParser = $httpTracing->getParser();
    }

    /**
     * {@inheritdoc}
     *
     * For more infor about the $options, check {@see HttpClientInterface::DEFAULT_OPTIONS}
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $span = $this->tracer->nextSpan();

        $headers = $options['headers'] ?? [];
        ($this->injector)($span->getContext(), $headers);
        $options['headers'] = $headers;

        if ($span->isNoop()) {
            // If the span is NO-OP, there is no reason to keep decorating
            // the request process with tracing data, instead we delegate
            // the call directly to the actual client but including trace
            // headers.
            return $this->delegate->request($method, $url, $options);
        }

        $span->start();
        $spanCustomizer = new SpanCustomizerShield($span);
        $parseRequest = new Request([$method, $url, $options]);
        $this->httpParser->request($parseRequest, $span->getContext(), $spanCustomizer);

        try {
            $options['on_progress'] = self::buildOnProgress(
                $options['on_progress'] ?? null,
                $parseRequest,
                [$this->httpParser, 'response'],
                $span,
                $spanCustomizer
            );

            // Since the cancel event is not being catched by the on_progress
            // callback we need to manually track it by wrapping it.
            return new HttpClientResponse(
                $this->delegate->request($method, $url, $options),
                $spanCustomizer,
                [$span, 'finish']
            );
        } catch (TransportExceptionInterface $e) {
            // Since response is an asynchronus operation, according to
            // HttpClientInterface::request, this exception can only happen
            // if an unsopported option is passed.
            $span->setError($e);
            $span->finish();
            throw $e;
        }
    }

    /**
     * buildOnProgress wraps an existing on_progress to listen to the request
     * completion. on_progress callback will be called on DNS resolution, on
     * arrival of headers and on completion; it will also be called on upload/download
     * of data and at least 1/s
     *
     * @var (callable(int,int,array):void)|null $delegateOnProgress
     * @var Request $parseRequest
     * @var callable(Response,TraceContext,SpanCustomizer):void $httpParserResponse
     * @var Span $span
     * @var SpanCustomizer $spanCustomizer
     */
    private static function buildOnProgress(
        ?callable $delegateOnProgress,
        Request $parseRequest,
        callable $httpParserResponse,
        Span $span,
        SpanCustomizer $spanCustomizer
    ): callable {
        return static function (
            int $dlNow,
            int $dlSize,
            array $info
        ) use (
            $delegateOnProgress,
            $parseRequest,
            $httpParserResponse,
            $span,
            $spanCustomizer
        ): void {
            if ($delegateOnProgress !== null) {
                try {
                    ($delegateOnProgress)($dlNow, $dlSize, $info);
                } catch (Throwable $e) {
                    // According to HttpClientInterface, throwing any exceptions
                    // MUST abort the request, hence we finish the span in here.
                    $span->setError($e);
                    $span->finish();
                    throw $e;
                }
            }

            if ($info['error'] !== null || $info['http_code'] > 199) {
                // any of these three conditions represent the finalization of the request
                ($httpParserResponse)(
                    new Response([$dlSize, $info], $parseRequest),
                    $span->getContext(),
                    $spanCustomizer
                );
                $span->finish();
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof HttpClientResponse) {
            $responses = [$responses];
        } elseif (!is_iterable($responses)) {
            throw new TypeError(sprintf(
                '"%s()" expects parameter 1 to be an iterable of %s objects, "%s" given.',
                __METHOD__,
                Response::class,
                get_class($responses)
            ));
        }

        return new ResponseStream(HttpClientResponse::stream($this->delegate, $responses, $timeout));
    }
}
