<?php


namespace ZipkinBundle;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Zipkin\Propagation\Map;
use Zipkin\Span;
use Zipkin\Tracing;

class Tracer
{
    /**
     * @var Tracing
     */
    private $tracer;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var callable
     */
    private $extractor;
    /**
     * @var callable
     */
    private $scopeCloserKey;

    public function __construct(Tracing $tracing, LoggerInterface $logger)
    {
        $this->tracer = $tracing->getTracer();
        $this->extractor = $tracing->getPropagation()->getExtractor(new Map());
        $this->logger = $logger;
    }

    public function prepareSpan(array $headers, string $name, string $kind): void
    {
        try {
            $spanContext = ($this->extractor)($headers);
        } catch (Exception $e) {
            $this->logger->error(
                sprintf('Error when starting the span: %s', $e->getMessage())
            );
            return;
        }

        $span = $this->tracer->nextSpan($spanContext);
        $span->start();

        $span->setName($name);
        $span->setKind($kind);

        $this->scopeCloserKey = $this->tracer->openScope($span);
    }

    public function flushTracer(): void
    {
        try {
            $this->tracer->flush();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    public function spanExist(): bool
    {
        return null !== $this->tracer->getCurrentSpan();
    }


    public function addTag($key, $value): void
    {
        $span = $this->tracer->getCurrentSpan();
        if (null === $span) {
            return;
        }

        $span->tag($key, $value);
    }

    public function runCustomizers(array $spanCustomizer, Request $request): void
    {
        $span = $this->tracer->getCurrentSpan();
        if (null === $span) {
            return;
        }

        foreach ($spanCustomizer as $customizer) {
            $customizer($request, $span);
        }
    }

    public function finishSpan()
    {
        $span = $this->tracer->getCurrentSpan();
        if (null === $span) {
            return;
        }

        $span->finish();

        if ($this->scopeCloserKey) {
            ($this->scopeCloserKey)();
            $this->scopeCloserKey = null;
        }
    }
}
