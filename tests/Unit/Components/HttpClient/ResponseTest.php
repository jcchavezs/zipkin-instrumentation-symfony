<?php

namespace ZipkinBundle\Tests\Unit\Components\HttpClient;

use Zipkin\Instrumentation\Http\Client\Request as ClientRequest;
use ZipkinTests\Unit\Instrumentation\Http\Client\BaseResponseTest;
use ZipkinBundle\Components\HttpClient\Response;
use ZipkinBundle\Components\HttpClient\Request;

final class ResponseTest extends BaseResponseTest
{
    /**
     * {@inheritdoc}
     */
    public static function createResponse(
        int $statusCode,
        $headers = [],
        $body = null,
        ClientRequest $request = null
    ): array {
        $delegateResponse = [0, [
            'response_headers' => $headers,
            'http_code' => $statusCode,
            'error' => null,
            'canceled' => false,
        ]];
        $response = new Response($delegateResponse, $request);
        return [$response, $delegateResponse, $request];
    }

    /**
     * {@inheritdoc}
     */
    public static function requestsProvider(): array
    {
        return [
            [null],
            [new Request(['GET', 'http://test.com', []])],
        ];
    }
}
