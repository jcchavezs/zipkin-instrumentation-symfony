<?php

namespace ZipkinBundle\Components\HttpClient;

use const Zipkin\Tags\HTTP_STATUS_CODE;
use const Zipkin\Tags\HTTP_RESPONSE_SIZE;
use const Zipkin\Tags\HTTP_PATH;
use const Zipkin\Tags\HTTP_METHOD;
use const Zipkin\Tags\ERROR;
use Zipkin\SpanCustomizer;
use Zipkin\Propagation\TraceContext;
use Zipkin\Instrumentation\Http\Client\Parser;

class DefaultParser implements Parser
{
    public function spanName(/*array */$request): string
    {
        self::assertRequestType($request);
        return strtolower($request[0]);
    }

    public function request(/*array */$request, TraceContext $context, SpanCustomizer $span): void
    {
        self::assertRequestType($request);
        list($method, $url) = $request;
        $lMethod = strtolower($method);
        $span->tag(HTTP_METHOD, $lMethod);
        if (false !== ($pieces = parse_url($url))) {
            $span->setName($pieces['scheme'] . '/' . $lMethod);
            $span->tag(HTTP_PATH, $pieces['path'] ?? '/');
        }
    }

    public function response(/*array */$response, TraceContext $context, SpanCustomizer $span): void
    {
        self::assertResponseType($response);
        list($responseSize, $info) = $response;

        if ($info['error'] !== null) {
            $span->tag(ERROR, (string) $info['error']);
            return;
        }

        if ($responseSize > 0) {
            $span->tag(HTTP_RESPONSE_SIZE, (string) $responseSize);
        }
        $this->parseStatusCode($info['http_code'], $span);
    }

    protected function parseStatusCode(int $statusCode, SpanCustomizer $span): void
    {
        if ($statusCode > 299) {
            $span->tag(HTTP_STATUS_CODE, (string) $statusCode);
            if ($statusCode > 399) {
                $span->tag(ERROR, (string) $statusCode);
            }
        }
    }

    private static function assertRequestType(array $request)
    {
    }

    private static function assertResponseType(array $request)
    {
    }
}
