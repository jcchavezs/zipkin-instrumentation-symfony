<?php

namespace ZipkinBundle\Components\HttpClient;

use Zipkin\SpanCustomizer;

interface HttpClientHandler
{
    /**
     * sampleRequest decides whether an unsampled request should be reconsidered
     * as sampled or not.
     *
     * @return true means request is sampled
     * @return false means request remains unsampled (or not decided)
     * @return null means no changes in sampling
     */
    public function sampleRequest(string $method, string $url, array $options): ?bool;

    /**
     * parseRequest parses the incoming data related to a request in order to add
     * relevant information to the span under the SpanCustomizer interface.
     *
     * Basic data being tagged is HTTP method, HTTP path but other information
     * such as query parameters can be added to enrich the span information.
     */
    public function parseRequest(
        string $method,
        string $url,
        array $options,
        SpanCustomizer $span
    ): void;

    /**
     * parseResponse parses the response data in order to add relevant information
     * to the span under the SpanCustomizer interface. The following information
     * is available under $info:
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
     * Basic data being tagged is HTTP status code but other information such
     * as any response header or redirect_count can be added.
     */
    public function parseResponse(int $responseSize, array $info, SpanCustomizer $span): void;
}
