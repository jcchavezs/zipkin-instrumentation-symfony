<?php

namespace ZipkinBundle\Tests\Unit;

use ZipkinTests\Unit\Instrumentation\Http\Server\BaseRequestTest;
use ZipkinBundle\Request;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;

final class RequestTest extends BaseRequestTest
{
    /**
     * {@inheritdoc}
     */
    public static function createRequest(
        string $method,
        string $uri,
        $headers = [],
        $body = null
    ): array {
        $delegateRequest = HttpFoundationRequest::create($uri, $method);
        $delegateRequest->headers->add($headers);
        $request = new Request($delegateRequest, null);
        return [$request, $delegateRequest];
    }
}
