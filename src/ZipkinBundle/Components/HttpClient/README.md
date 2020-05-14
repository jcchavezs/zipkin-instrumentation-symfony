# Zipkin Instrumentation for Symfony HTTP Client

This bundle provides an implementation of Symfony's [HTTP Client component](https://symfony.com/doc/current/components/http_client.html) that enables tracing.

## Using the HTTP Client

You can wrap an existing HTTP Client:

```php
<?php

use Symfony\Component\HttpClient\HttpClient;
use ZipkinBundle\Components\HttpClient as ZipkinHttpClient;

$client = new ZipkinHttpClient(HttpClient::create(), $tracing);
$response = $client->request('GET', 'https://api.github.com/repos/symfony/symfony-docs');
```

Or declare it as a service:

```yaml
services:
  zipkin.http_client:
    class: ZipkinBundle\Components\HttpClient\HttpClient
    arguments:
      - "@http_client"
      - "@zipkin.default_tracing"
```

Notice that `ZipkinBundle\Components\HttpClient\HttpClient` is just a wrapper around an existing HTTP Client, hence if you want to configure its settings by passing options you can do that with the underlaying client or [setting the options in the default one](https://symfony.com/doc/current/reference/configuration/framework.html#http-client).

Once declared the zipkin http client as a service you can inject it using the Symfony DI or using the autowiring alias. See [this documentation](https://symfony.com/doc/current/components/http_client.html#injecting-the-http-client-into-services) for more details.

## Customizing spans

This client provides a `ZipkinBundle\Components\HttpClient\HttpClientParser` interface which is used for customizing the tags being added to a span based on the request and the response data.

```yaml
services:
  search.http_client:
    class: ZipkinBundle\Components\HttpClient\HttpClient
    arguments:
      - "@http_client"
      - "@zipkin.default_tracing"
      - "@search_http_parser" # the custom parser
```

We also provide a default parser `ZipkinBundle\Components\HttpClient\DefaultHttpClientParser` which covers the standard cases for HTTP tracing but it can be easily extended to fullfill more advanced cases:

```php
<?php

use ZipkinBundle\Components\HttpClient\DefaultHttpClientParser;

final class SearchHttpClientParser extends DefaultHttpClientParser {
    // Additionally to the request standard tags, we add the
    // search key (from the query string with key query) as 
    // a span tag.
    public function request(
        string $method,
        string $url,
        array $options,
        SpanCustomizer $span
    ): void {
        parent::request($method, $url, $options, $span);
        if (array_key_exists('search', $options['query'])) {
            $span->tag('search_key', $options['query']['search']);
        }
    }

    // Additionally to the request standard tags, we add the
    // value of the header x-search-id from the response as 
    // a span tag.
    public function response(
        int $responseSize,
        array $info,
        SpanCustomizer $span
    ): void {
        parent::response($responseSize, $info, $span);
        $search_id = $info['response_headers']['x-search-id'][0];
        $span->tag('search_id', $search_id);
    }
}
```
