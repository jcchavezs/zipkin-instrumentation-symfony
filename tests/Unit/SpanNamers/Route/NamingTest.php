<?php

namespace ZipkinBundle\Tests\Unit\SpanNamers\Route;

use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use ZipkinBundle\SpanNamers\Route\CacheWarmer;
use ZipkinBundle\SpanNamers\Route\SpanNamer;

final class NamingTest extends PHPUnit_Framework_TestCase
{
    const ROUTE_NAME = 'route';
    const ROUTE_PATH = '/path';
    const METHOD = 'POST';

    public function testGetNameForNonExistingRouteSuccess()
    {
        $naming = SpanNamer::create(__DIR__);
        $name = $naming->getName(new Request());
        $this->assertEquals('GET not_found', $name);
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
        $name = $naming->getName($request);
        $this->assertEquals(self::METHOD . ' ' . self::ROUTE_PATH, $name);
        unlink($filename);
    }
}
