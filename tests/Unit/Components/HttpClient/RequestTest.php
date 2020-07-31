<?php

namespace ZipkinBundle\Tests\Unit\Components\HttpClient;

use ZipkinTests\Unit\Instrumentation\Http\Client\BaseRequestTest;
use ZipkinBundle\Components\HttpClient\Request;

final class RequestTest extends BaseRequestTest
{
    public static function createRequest(
        string $method,
        string $uri,
        $headers = [],
        $body = null
    ): array {
        $delegateRequest = [$method, $uri, [
            'headers' => $headers,
            'body' => $body ?? '',
        ]];
        $request = new Request($delegateRequest);
        return [$request, $delegateRequest];
    }
}
