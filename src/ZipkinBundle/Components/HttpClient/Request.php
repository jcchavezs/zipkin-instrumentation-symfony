<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Instrumentation\Http\Client\Request as ClientRequest;

final class Request extends ClientRequest
{
    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $path;

    /**
     * @var array
     */
    private $options;

    public function __construct(array $request)
    {
        list($this->method, $this->url, $this->options) = $request;
        if (false !== ($pieces = parse_url($this->url))) {
            $this->path = $pieces['path'] ?? '/';
        }
    }

    /**
     * {@inhertidoc}
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * {@inhertidoc}
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * {@inhertidoc}
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * {@inhertidoc}
     */
    public function getHeader(string $name): ?string
    {
        if (array_key_exists('headers', $this->options) && array_key_exists($name, $this->options['headers'])) {
            return $this->options['headers'][$name];
        }

        return null;
    }

    /**
     * @return array
     */
    public function unwrap()
    {
        return [$this->method, $this->url, $this->options];
    }
}
