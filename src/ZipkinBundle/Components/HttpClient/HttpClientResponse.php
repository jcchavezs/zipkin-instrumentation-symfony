<?php

namespace ZipkinBundle\Components\HttpClient;

use const Zipkin\Tags\ERROR;
use Zipkin\SpanCustomizer;

use TypeError;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use SplObjectStorage;

/**
 * Response is a wrapping around a ResponseInterface that makes
 * it possible to track the cancelation in a request.
 */
final class HttpClientResponse implements ResponseInterface
{
    /**
     * @var ResponseInterface
     */
    private $delegate;

    /**
     * @var SpanCustomizer
     */
    private $spanCustomizer;

    /**
     * @var callable
     */
    private $onCancelCloser;

    public function __construct(
        ResponseInterface $response,
        SpanCustomizer $spanCustomizer,
        callable $onCancelCloser
    ) {
        $this->delegate = $response;
        $this->spanCustomizer = $spanCustomizer;
        $this->onCancelCloser = $onCancelCloser;
    }

    /**
     * This method allows the wrapped response to be identified by the original client
     * and then make it possible the streaming. The design of the solution is borrowed
     * from the TraceableClient in the HttpClient:
     * https://github.com/symfony/symfony/blob/afc44dae16/src/Symfony/Component/HttpClient/Response/TraceableResponse.php#L112
     *
     * @internal
     */
    public static function stream(HttpClientInterface $client, iterable $responses, ?float $timeout): \Generator
    {
        $wrappedResponses = [];
        $traceableMap = new SplObjectStorage();

        foreach ($responses as $response) {
            if (!$response instanceof self) {
                throw new TypeError(sprintf(
                    '"%s::stream()" expects parameter 1 to be an iterable of %s objects, "%s" given.',
                    HttpClient::class,
                    self::class,
                    get_class($response)
                ));
            }

            $traceableMap[$response->delegate] = $response;
            $wrappedResponses[] = $response->delegate;
        }

        foreach ($client->stream($wrappedResponses, $timeout) as $response => $chunk) {
            yield $traceableMap[$response] => $chunk;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->delegate->getStatusCode();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders(bool $throw = true): array
    {
        return $this->delegate->getHeaders($throw);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(bool $throw = true): string
    {
        return $this->delegate->getContent($throw);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(bool $throw = true): array
    {
        return $this->delegate->toArray($throw);
    }

    /**
     * {@inheritdoc}
     *
     * cancel needs to be overriden to detect a request cancelation
     * because the `on_progress` callback is not being called in such
     * cases.
     */
    public function cancel(): void
    {
        $this->spanCustomizer->tag(ERROR, 'Response has been canceled.');
        ($this->onCancelCloser)();
        $this->delegate->cancel();
    }

    /**
     * {@inheritdoc}
     */
    public function getInfo(string $type = null)
    {
        if ($type === null) {
            return $this->delegate->getInfo();
        } else {
            return $this->delegate->getInfo($type);
        }
    }
}
