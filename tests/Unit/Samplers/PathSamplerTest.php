<?php

namespace ZipkinBundle\Tests\Unit\Samplers;

use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use ZipkinBundle\Samplers\PathSampler;

final class PathSamplerTest extends PHPUnit_Framework_TestCase
{
    const TEST_PATH = '/my/route/123456';
    const TEST_PATH_REGEX = '/my/route/[0-9]{6}';

    public function testAnIncludedPathSuccess()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => self::TEST_PATH]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $sampler = new PathSampler($requestStack, [self::TEST_PATH_REGEX]);
        $this->assertTrue($sampler->isSampled(1));
    }

    public function testAnExcludedPathSuccess()
    {
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => self::TEST_PATH]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $sampler = new PathSampler($requestStack, [], [self::TEST_PATH_REGEX]);
        $this->assertFalse($sampler->isSampled(1));
    }
}
