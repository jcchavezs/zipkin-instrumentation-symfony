<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\SpanCustomizer;
use const Zipkin\Tags\HTTP_PATH;
use const Zipkin\Tags\HTTP_METHOD;
use const Zipkin\Tags\HTTP_STATUS_CODE;
use const Zipkin\Tags\ERROR;
use const Zipkin\Tags\HTTP_RESPONSE_SIZE;

class DefaultHttpParser implements HttpParser
{
    public function request(string $method, string $url, array $options, SpanCustomizer $span): void
    {
        $span->tag(HTTP_METHOD, $method);
        if (false === ($pieces = parse_url($url))) {
            $span->setName($method);
        } else {
            $span->setName($pieces['scheme'] . '/' . $method);
            $span->tag(HTTP_PATH, $pieces['path'] ?? '/');
        }
    }

    public function response(int $responseSize, array $info, SpanCustomizer $span): void
    {
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
}
