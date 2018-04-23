<?php

namespace ZipkinBundle\Tests\Unit\SpanNamers\Route;

use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use Zipkin\Span;
use ZipkinBundle\SpanNamers\Route\CacheWarmer;
use ZipkinBundle\SpanNamers\Route\SpanNamer;

final class SpanNamerTest extends PHPUnit_Framework_TestCase
{
    const ROUTE_NAME = 'route';
    const ROUTE_PATH = '/path';
    const METHOD = 'POST';

    public function testGetNameForNonExistingRouteSuccess()
    {
        $span = $this->prophesize(Span::class);
        $span->setName('GET not_found')->shouldBeCalled();
        $naming = SpanNamer::create(__DIR__);
        $naming(new Request(), $span->reveal());
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

        $naming = SpanNamer::create($cacheDir);

        $span = $this->prophesize(Span::class);
        $span->setName(self::METHOD . ' ' . self::ROUTE_PATH)->shouldBeCalled();
        $naming($request, $span->reveal());
        unlink($filename);
    }
}
