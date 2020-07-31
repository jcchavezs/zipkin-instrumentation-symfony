<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\Instrumentation\Http\Client\Response as ClientResponse;
use Zipkin\Instrumentation\Http\Client\Request as ClientRequest;

final class Response extends ClientResponse
{
    /**
     * @var int
     */
    private $dlSize;

    /**
     * @var array
     */
    private $info;

    /**
     * @var Request
     */
    private $request;

    /**
     * @param array $response including the int `dlSize` and the `info` array including:
     *
     *  - canceled (bool) - true if the response was canceled using ResponseInterface::cancel(), false otherwise
     *  - error (string|null) - the error message when the transfer was aborted, null otherwise
     *  - http_code (int) - the last response code or 0 when it is not known yet
     *  - http_method (string) - the HTTP verb of the last request
     *  - redirect_count (int) - the number of redirects followed while executing the request
     *  - redirect_url (string|null) - the resolved location of redirect responses, null otherwise
     *  - response_headers (array) - an array modelled after the special $http_response_header variable
     *  - start_time (float) - the time when the request was sent or 0.0 when it's pending
     *  - url (string) - the last effective URL of the request
     *  - user_data (mixed|null) - the value of the "user_data" request option, null if not set
     *
     * @param Request $request including the originating request
     */
    public function __construct(array $response, ?Request $request)
    {
        list($this->dlSize, $this->info) = $response;
        $this->request = $request;
    }

    /**
     * {@inhertidoc}
     */
    public function getRequest(): ?ClientRequest
    {
        return $this->request;
    }

    /**
     * {@inhertidoc}
     */
    public function getStatusCode(): int
    {
        return $this->info['http_code'];
    }

    /**
     * {@inhertidoc}
     */
    public function unwrap(): array
    {
        return [$this->dlSize, $this->info];
    }
}
