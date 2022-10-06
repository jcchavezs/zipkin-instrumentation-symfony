<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Instrumentation\Http\Client\Parser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpClient implements HttpClientInterface
{
    use HttpClientTrait;

    /**
     * We don't manage options in the HttpClient so just skip it
     */
    public function withOptions(array $options): static
    {
        return clone $this;
    }
}
