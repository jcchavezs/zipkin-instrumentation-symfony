<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\SpanCustomizer;
use const Zipkin\Tags\HTTP_PATH;
use const Zipkin\Tags\HTTP_METHOD;
use const Zipkin\Tags\HTTP_STATUS_CODE;
use const Zipkin\Tags\ERROR;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DefaultHttpParser implements HttpParser
{
    public function request(string $method, string $url, array $options = [], SpanCustomizer $span)
    {
        $span->tag(HTTP_METHOD, $method);
        if (false === ($pieces = parse_url($url))) {
            $span->setName($method);
        } else {
            $span->setName($pieces['schema'] + '/' + $method);
            $span->tag(HTTP_PATH, $pieces['path']);
        }
    }

    public function response(ResponseInterface $response, SpanCustomizer $span)
    {
        $statusCode = $response->getStatusCode();
        if ($statusCode > 299) {
            $span->tag(HTTP_STATUS_CODE, (string) $statusCode);
            if ($statusCode > 399) {
                $span->tag(ERROR, (string) $statusCode);
            }
        }
    }
}
