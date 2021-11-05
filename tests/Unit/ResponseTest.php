<?php

namespace ZipkinBundle\Tests\Unit;

use Zipkin\Instrumentation\Http\Server\Request as ServerRequest;
use ZipkinTests\Unit\Instrumentation\Http\Server\BaseResponseTest;
use ZipkinBundle\Response;
use ZipkinBundle\Request;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

final class ResponseTest extends BaseResponseTest
{
    /**
     * {@inheritdoc}
     */
    public static function createResponse(
        int $statusCode,
        $headers = [],
        $body = null,
        ServerRequest $request = null,
        string $route = null
    ): array {
        $delegateResponse = new HttpFoundationResponse($body, $statusCode, $headers);
        $response = new Response($delegateResponse, $request, $route);
        return [$response, $delegateResponse, $request, $route];
    }

    /**
     * {@inheritdoc}
     */
    public static function requestsProvider(): array
    {
        return [
            [null],
            [new Request(HttpFoundationRequest::create('http://test.com', 'GET'))],
        ];
    }
}
