<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Tracer;
use Zipkin\Tracing;
use Zipkin\SpanCustomizer;
use Zipkin\Propagation\Map;
use const Zipkin\Tags\ERROR;
use Zipkin\SpanCustomizerShield;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $span = $this->tracer->nextSpan();
        $span->start();

        $spanCustomizer = new SpanCustomizerShield($span);
        $this->httpParser->request(strtolower($method), $url, $options, $spanCustomizer);

        $headers = [];
        ($this->injector)($span->getContext(), $headers);

        try {
            $options['headers'] = $headers + ($options['headers'] ?? []);
            $options['on_progress'] = self::buildOnProgress(
                $options['on_progress'] ?? null,
                [$this->httpParser, 'response'],
                $spanCustomizer,
                [$span, 'finish']
            );
            return $this->delegate->request($method, $url, $options);
        } catch (TransportExceptionInterface $e) {
            // Since response is an asynchronus operation, according to
            // HttpClientInterface::request, this exception can only happen
            // if an unsopported option is passed.
            $span->setName(strtolower($method));
            $span->tag(ERROR, $e->getMessage());
            $span->finish();
            throw $e;
        }
    }

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
                ($delegateOnProgress)($dlNow, $dlSize, $info);
            }

            if ($info['canceled'] || $info['error'] !== null || $info['http_code'] > 199) {
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
        return $this->delegate->stream($responses, $timeout);
    }
}