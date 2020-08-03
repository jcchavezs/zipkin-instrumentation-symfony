# Zipkin Instrumentation for Symfony HTTP Client

This bundle provides an implementation of Symfony's [HTTP Client component](https://symfony.com/doc/current/components/http_client.html) that enables tracing.

## Using the HTTP Client

You can wrap an existing HTTP Client:

```php
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
      - "@zipkin.default_http_client_tracing"
```

Note: You can also include the `src/ZipkinBundle/Components/HttpClient/Resources/config/services.yml` in the services declaration.

Be aware that `ZipkinBundle\Components\HttpClient\HttpClient` is just a wrapper around an existing HTTP Client, hence if you want to configure its settings by passing options you can do that with the underlaying client or [setting the options in the default one](https://symfony.com/doc/current/reference/configuration/framework.html#http-client).

Once declared the zipkin http client as a service you can inject it using the Symfony DI or using the autowiring alias. See [this documentation](https://symfony.com/doc/current/components/http_client.html#injecting-the-http-client-into-services) for more details.

## Customizing spans

Zipkin client instrumentation provides a `Zipkin\Instrumentation\Http\Client\HttpClientParser` interface which is used for customizing the tags being added to a span based on the request and the response data, the default parser `Zipkin\Instrumentation\Http\Client\DefaultHttpClientParser` provides the usual tags and span name but you can use your own to get more accurate information.

```yaml
services:
  search.http_client_tracing:
    class: Zipkin\Instrumentation\Http\Client\HttpClientTracing
    arguments:
      - "@zipkin.default_tracing"
      - "@search_http_parser" # my own parser

  search.http_client:
    class: ZipkinBundle\Components\HttpClient\HttpClient
    arguments:
      - "@http_client"
      - "@search.http_client_tracing"

  search_http_parser:
    class: My\Search\HttpClientParser
```

and the parser would look like:

```php
namespace My\Search;

use Zipkin\Instrumentation\Http\Client\DefaultHttpClientParser;
use Zipkin\Instrumentation\Http\Client\Response;
use Zipkin\Instrumentation\Http\Client\Request;
use Zipkin\Propagation\TraceContext;
use Zipkin\SpanCustomizer;

final class HttpClientParser extends DefaultHttpClientParser {
    public function request(Request $request, TraceContext $context, SpanCustomizer $span): void {
        parent::request($request, $context, $span);
        if (null !== ($searchKey = $request->getHeader('search_key'))) {
            $span->tag('search_key', $searchKey);
        }
    }
}
```
