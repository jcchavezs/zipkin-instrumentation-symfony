<?php

namespace ZipkinBundle\Components\HttpClient;

use Generator;
use Throwable;
use TypeError;
use Zipkin\Tracer;
use Zipkin\Tracing;
use Zipkin\SpanCustomizer;
use Zipkin\Propagation\Map;
use const Zipkin\Tags\ERROR;
use Zipkin\SpanCustomizerShield;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

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

    public function __construct(
        HttpClientInterface $client,
        Tracing $tracing,
        HttpParser $httpParser = null
    ) {
        $this->delegate = $client;
        $this->tracer = $tracing->getTracer();
        $this->injector = $tracing->getPropagation()->getInjector(new Map());
        $this->httpParser = $httpParser ?? new DefaultHttpParser();
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
        $this->httpParser->request(strtolower($method), $url, $options, $spanCustomizer);

        try {
            $options['on_progress'] = self::buildOnProgress(
                $options['on_progress'] ?? null,
                [$this->httpParser, 'response'],
                $spanCustomizer,
                [$span, 'finish']
            );

            // Since the cancel event is not being catched by the on_progress
            // callback we need to manually track it by wrapping it.
            return new Response(
                $this->delegate->request($method, $url, $options),
                $spanCustomizer,
                [$span, 'finish']
            );
        } catch (TransportExceptionInterface $e) {
            // Since response is an asynchronus operation, according to
            // HttpClientInterface::request, this exception can only happen
            // if an unsopported option is passed.
            $span->tag(ERROR, $e->getMessage());
            $span->finish();
            throw $e;
        }
    }

    /**
     * buildOnProgress wraps an existing on_progress to listen to the request
     * completion. on_progress callback will be called on DNS resolution, on
     * arrival of headers and on completion; it will also be called on upload/download
     * of data and at least 1/s
     */
    private static function buildOnProgress(
        ?callable $delegateOnProgress,
        callable $httpParserResponse,
        SpanCustomizer $spanCustomizer,
        callable $spanCloser
    ): callable {
        return static function (
            int $dlNow,
            int $dlSize,
            array $info
        ) use (
            $delegateOnProgress,
            $httpParserResponse,
            $spanCustomizer,
            $spanCloser
        ): void {
            if ($delegateOnProgress !== null) {
                try {
                    ($delegateOnProgress)($dlNow, $dlSize, $info);
                } catch (Throwable $e) {
                    // According to HttpClientInterface, throwing any exceptions
                    // MUST abort the request, hence we finish the span in here.
                    $spanCustomizer->tag(ERROR, $e->getMessage());
                    ($spanCloser)();
                    throw $e;
                }
            }

            if ($info['error'] !== null || $info['http_code'] > 199) {
                // any of these three conditions represent the finalization of the request
                ($httpParserResponse)($dlSize, $info, $spanCustomizer);
                ($spanCloser)();
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        if ($responses instanceof Response) {
            $responses = [$responses];
        } elseif (!is_iterable($responses)) {
            throw new TypeError(sprintf(
                '"%s()" expects parameter 1 to be an iterable of %s objects, "%s" given.',
                __METHOD__,
                Response::class,
                get_class($responses)
            ));
        }

        return new ResponseStream(Response::stream($this->delegate, $responses, $timeout));
    }
}
