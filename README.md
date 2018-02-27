# Zipkin Symfony

[![Build Status](https://travis-ci.org/jcchavezs/zipkin-symfony.svg?branch=master)](https://travis-ci.org/jcchavezs/zipkin-symfony)
[![Latest Stable Version](https://poser.pugx.org/jcchavezs/zipkin-symfony/v/stable)](https://packagist.org/packages/jcchavezs/zipkin-symfony)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)
[![Total Downloads](https://poser.pugx.org/jcchavezs/zipkin-symfony/downloads)](https://packagist.org/packages/jcchavezs/zipkin-symfony)
[![License](https://poser.pugx.org/jcchavezs/zipkin-symfony/license)](https://packagist.org/packages/jcchavezs/zipkin-symfony)


A Zipkin integration for Symfony applications

## Installation

```bash
composer require jcchavezs/zipkin-symfony
```

## Getting started

This Symfony bundle provides a middleware that can be used to trace
HTTP requests. In order to use it, it is important that you declare 
the middleware by adding this to `app/config/services.yml` or any other
[dependency injection](https://symfony.com/doc/current/components/dependency_injection.html) declaration.

```yaml
services:
  tracing_middleware:
    class: ZipkinBundle\Middleware
    arguments:
      - "@zipkin.default_tracing"
      - "@logger"
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 256 }
      - { name: kernel.event_listener, event: kernel.terminate }
      - { name: kernel.event_listener, event: kernel.exception }
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

Besides `always`, and `never` there are three other sampling strategies: **by path**, **by route** and 
**by percentage**, however it is also possible yo use your own sampler.

It is important to mention that the sampling decision is made on two situations: a) when a new trace
is being started, b) when the extracted context does not include a sampling decision.

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

### By route

This is for those cases where you want to make a sampling decision based on the
symfony route:

```yaml
zipkin:
  ...
  sampler:
    type: route
    path:
      included_paths:
        - "my_route"
      excluded_paths:
        - "another_route"
```

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

You can pass a custom sampler as long as it implements the `Zipkin\Sampler` interface.
Check the [Custom Tracing](#custom-tracing) section for more details.

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
```

## Custom Tracing

Although this bundle provides a tracer based on the configuration parameters
under the `zipkin` node, you can inject your own `tracing component` to the 
middleware as long as it implements the `Zipkin\Tracing` interface:

```yaml
services:
  tracing_middleware:
    class: ZipkinBundle\Middleware
    arguments:
      - "@my_own_tracer"
      - "@logger"
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 256 }
      - { name: kernel.event_listener, event: kernel.terminate }
      - { name: kernel.event_listener, event: kernel.exception }
```

## Span namers

By default the span name is being defined by the HTTP verb. This approach is
a not so bad option seeking for low cardinality in span naming. A more useful
approach is to use the route path: `/user/{user_id}` however including the 
`@router` in the middleware is expensive and reduces its performance thus the
best is to precompile (aka cache warmup) a map of `name => path` in cache that
can be used to resolve the path in runtime.

```yaml
  zipkin.span_namer.route:
    class: ZipkinBundle\SpanNamers\Route\SpanNamer
    factory: [ZipkinBundle\SpanNamers\Route\SpanNamer, 'create']
    arguments:
      - "%kernel.cache_dir%"

  zipkin.span_namer.cache_warmer:
    class: ZipkinBundle\SpanNamers\Route\CacheWarmer
    arguments:
      - "@router"
    tags:
      - { name: kernel.cache_warmer, priority: 0 }

services:
  tracing_middleware:
    class: ZipkinBundle\Middleware
    arguments:
      - "@zipkin.default_tracing"
      - "@logger"
      - "@zipkin.span_namer.route"
    tags:
      - { name: kernel.event_listener, event: kernel.request, priority: 256 }
      - { name: kernel.event_listener, event: kernel.terminate }
      - { name: kernel.event_listener, event: kernel.exception }
```

## Contributing

All contribution and feedback are welcome.
