<?php

namespace ZipkinBundle\Tests\Unit\SpanCustomizers\ByPathNamer;

use ZipkinBundle\RouteMapper\RouteMapper;
use ZipkinBundle\RouteMapper\CacheWarmer;
use Symfony\Component\HttpFoundation\Request;
use PHPUnit\Framework\TestCase;

final class RouteMapperTest extends TestCase
{
    const ROUTE_NAME = 'route';
    const ROUTE_PATH = '/path';
    const METHOD = 'POST';

    public function testMapForNoopReturnsNull()
    {
        $mapper = RouteMapper::createAsNoop();
        $this->assertNull($mapper->mapToPath(new Request()));
    }

    public function testGetPathForNonExistingRouteSuccess()
    {
        $mapper = RouteMapper::createFromCache(__DIR__);
        $this->assertEquals('not_found', $mapper->mapToPath(new Request()));
    }

    public function testGetPathForExistingRouteSuccess()
    {
        $mapper = RouteMapper::createFromMap([self::ROUTE_NAME => self::ROUTE_PATH]);

        $request = new Request(
            [],
            [],
            ['_route' => self::ROUTE_NAME],
            [],
            [],
            ['REQUEST_METHOD' => self::METHOD]
        );

        $this->assertEquals(self::ROUTE_PATH, $mapper->mapToPath($request));
    }

    public function testGetNameForExistingRouteSuccess()
    {
        $cacheDir = sys_get_temp_dir();
        $filename = CacheWarmer::buildOutputFilename($cacheDir);
        file_put_contents(
            $filename,
            sprintf('<?php return ["%s" => "%s"];', self::ROUTE_NAME, self::ROUTE_PATH)
        );

        $request = new Request(
            [],
            [],
            ['_route' => self::ROUTE_NAME],
            [],
            [],
            ['REQUEST_METHOD' => self::METHOD]
        );

        $mapper = RouteMapper::createFromCache($cacheDir);
        $this->assertEquals(self::ROUTE_PATH, $mapper->mapToPath($request));
        unlink($filename);
    }
}
