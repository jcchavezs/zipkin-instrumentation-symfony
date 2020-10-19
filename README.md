# Zipkin Instrumentation for Symfony

[![Build Status](https://travis-ci.org/jcchavezs/zipkin-instrumentation-symfony.svg?branch=master)](https://travis-ci.org/jcchavezs/zipkin-instrumentation-symfony)
[![CircleCI](https://circleci.com/gh/jcchavezs/zipkin-instrumentation-symfony/tree/master.svg?style=svg)](https://circleci.com/gh/jcchavezs/zipkin-instrumentation-symfony/tree/master)
[![Latest Stable Version](https://poser.pugx.org/jcchavezs/zipkin-instrumentation-symfony/v/stable)](https://packagist.org/packages/jcchavezs/zipkin-instrumentation-symfony)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.1-8892BF.svg)](https://php.net/)
[![Total Downloads](https://poser.pugx.org/jcchavezs/zipkin-instrumentation-symfony/downloads)](https://packagist.org/packages/jcchavezs/zipkin-instrumentation-symfony)
[![License](https://poser.pugx.org/jcchavezs/zipkin-instrumentation-symfony/license)](https://packagist.org/packages/jcchavezs/zipkin-instrumentation-symfony)

A Zipkin instrumentation for Symfony applications

## Installation

```bash
composer require jcchavezs/zipkin-instrumentation-symfony
```

## Getting started

This Symfony bundle provides a kernel listener that can be used to trace
HTTP requests. In order to use it, it is important that you declare 
the listener by adding this to your `app/config/services.yml` or any other
[dependency injection](https://symfony.com/doc/current/components/dependency_injection.html) declaration.

```yaml
services:
  tracing_kernel_listener:
    class: ZipkinBundle\KernelListener
    arguments:
      - "@zipkin.default_http_server_tracing"
      - "@zipkin.route_mapper"
      - "@logger"
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 2560 }
      - { name: kernel.event_listener, event: kernel.response, priority: -2560 }
      - { name: kernel.event_listener, event: kernel.exception }
      - { name: kernel.event_listener, event: kernel.terminate }
```

`@zipkin.default_tracing` is a `Zipkin\DefaultTracing` instance which is being 
built based on the configuration (add this to `app/config/config.yml`):

```yaml
zipkin:
  noop: false # if set to true, no request will be traced
  service_name: my_service # the name of the service
  sampler:
    type: percentage
    percentage: 0.1
```

## Samplers

Besides `always`, and `never` there are three other sampling strategies: **by path**, **by route** and **by percentage**, however it is also possible yo use your own sampler.

It is important to mention that the sampling decision is made on two situations: a) when a new trace is being started, b) when the extracted context does not include a sampling decision.

### By path

This is for those cases where you want to make a sampling decision based on the
url path:

```yaml
zipkin:
  ...
  sampler:
    type: path
    path:
      included_paths:
        - "/my/resource/[0-9]"
      excluded_paths:
        - "/another/path/"
```

This sampler uses the `Symfony\Component\HttpFoundation\RequestStack` meaning that it won't work in event loop enviroments. For event loop environments, use a `requestSampler` in the HTTP Server Tracing.

### By route

This is for those cases where you want to make a sampling decision based on the
symfony route:

```yaml
zipkin:
  ...
  sampler:
    type: route
    route:
      included_routes:
        - "my_route"
      excluded_routes:
        - "another_route"
```

This sampler uses the `Symfony\Component\HttpFoundation\RequestStack` meaning that it won't work in event loop enviroments. For event loop environments, use a `requestSampler` in the HTTP Server Tracing.

### By percentage

This one is for those cases where you want to sample only a percentage of the 
requests (a.k.a "Sampling rate")

```yaml
zipkin:
  ...
  sampler:
    type: percentage
    percentage: 0.1
```

### Custom samplers

You can pass a custom sampler as long as it implements the `Zipkin\Sampler` interface. You just need to use the service `id` declared in the service container.

```yaml
zipkin:
  ...
  sampler:
    type: custom
    custom: my_service_name
```

## Reporters

By default, the bundle reports to `Log` reporter which wraps `@logger`.

### HTTP reporter

This is the most common use case, it reports to a HTTP backend of Zipkin

```yaml
zipkin:
  ...
  reporter:
    type: http
    http:
      endpoint_url: http://zipkin:9411/api/v2/spans
      timeout: ~
```

## Default tags

You can add tags to every span being created by the tracer. This functionality is
useful when you need to add tags like instance name.

```yaml
services:
  tracing_kernel_listener:
    class: ZipkinBundle\KernelListener
    arguments:
      - "@zipkin.default_http_server_tracing"
      - "@zipkin.route_mapper"
      - "@logger"
      - { instance: %instance_name% }
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 2560 }
      - { name: kernel.event_listener, event: kernel.response, priority: -2560 }
      - { name: kernel.event_listener, event: kernel.exception }
      - { name: kernel.event_listener, event: kernel.terminate }
```

## Custom Tracing

Although this bundle provides a tracer based on the configuration parameters
under the `zipkin` node, you can inject your own `tracing component` to the 
kernel listener as long as it implements the `Zipkin\Tracing` interface:

```yaml
services:
  tracing_kernel_listener:
    class: ZipkinBundle\KernelListener
    arguments:
      - "@my_own_http_server_tracing"
      - "@zipkin.route_mapper"
      - "@logger"
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 2560 }
      - { name: kernel.event_listener, event: kernel.response, priority: -2560 }
      - { name: kernel.event_listener, event: kernel.exception }
      - { name: kernel.event_listener, event: kernel.terminate }
```

## Span customization

By default spans include usual HTTP information like method, path or status code but there are cases where user wants to add more information in the spans based on the request (e.g. `request_id` or a query parameter). In such cases one can extend the `HttpServerParser` to have access to the request and tag the span:

```yaml
services:
  search.http_server_tracing:
    class: Zipkin\Instrumentation\Http\Server\HttpServerTracing
    arguments:
      - "@zipkin.default_tracing"
      - "@zipkin.route_mapper"
      - "@search_http_parser" # my own parser

  tracing_kernel_listener:
    class: ZipkinBundle\KernelListener
    arguments:
      - "@search.http_server_tracing"
      - "@logger"
      - { instance: %instance_name% }
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 2560 }
      - { name: kernel.event_listener, event: kernel.response, priority: -2560 }
      - { name: kernel.event_listener, event: kernel.exception }
      - { name: kernel.event_listener, event: kernel.terminate }

  search_http_parser:
    class: My\Search\HttpServerParser
```

and the parser would look like:

```php
namespace My\Search;

use Zipkin\Instrumentation\Http\Server\DefaultHttpServerParser;
use Zipkin\Instrumentation\Http\Server\Response;
use Zipkin\Instrumentation\Http\Server\Request;
use Zipkin\Propagation\TraceContext;
use Zipkin\SpanCustomizer;

final class HttpServerParser extends DefaultHttpServerParser {
    public function request(Request $request, TraceContext $context, SpanCustomizer $span): void {
        parent::request($request, $context, $span);
        if (null !== ($searchKey = $request->getHeader('search_key'))) {
            $span->tag('search_key', $searchKey);
        }
    }
}
```


## HTTP Client

This bundle includes an adapter for HTTP Client. For more details, read [this doc](src/ZipkinBundle/Components/HttpClient/README.md).

## Contributing

All contributions and feedback are welcome.

### Unit testing

Run the unit tests with:

```bash
composer test
```

### E2E testing

On every build we run a end to end (E2E) test against a symfony application.

This test run in our CI tests but it can be also [reproduced in local](./tests/E2E/README.md).
