<?php

namespace ZipkinBundle\Tests\Unit\SpanCustomizers\ByPathNamer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Config\Loader\LoaderInterface;
use ZipkinBundle\SpanCustomizers\ByPathNamer\CacheWarmer;

final class CacheWarmerTest extends TestCase
{
    public function testCacheWarmerSuccess()
    {
        $fileLoader = $this->prophesize(LoaderInterface::class);
        $fileLoader->load(null, null)->willReturn(new RouteCollection());
        $router = new Router($fileLoader->reveal(), null);
        $router->getRouteCollection()->add('test_name', new Route('test_path'));

        $cacheWarmer = new CacheWarmer($router);
        $cacheWarmer->warmUp(sys_get_temp_dir());

        $file = CacheWarmer::buildOutputFilename(sys_get_temp_dir());

        $routeMap = require $file;

        $this->assertEquals(['test_name' => '/test_path'], $routeMap);
    }
}
