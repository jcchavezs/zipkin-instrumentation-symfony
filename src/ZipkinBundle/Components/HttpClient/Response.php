<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\SpanCustomizer;
use Symfony\Contracts\HttpClient\ResponseInterface;

use const Zipkin\Tags\ERROR;

final class Response implements ResponseInterface
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

    public function unwrapResponse(): ResponseInterface
    {
        return $this->delegate;
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
