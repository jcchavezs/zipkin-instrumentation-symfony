<?php

namespace ZipkinBundle\Tests\Unit\SpanNaming\Route;

use PHPUnit_Framework_TestCase;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use ZipkinBundle\SpanNaming\Route\CacheWarmer;

final class CacheWarmerTest extends PHPUnit_Framework_TestCase
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
