<?php

namespace ZipkinBundle\Tests\Unit\Samplers;

use PHPUnit\Framework\TestCase;
use ZipkinBundle\Samplers\RouteSampler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RouteSamplerTest extends TestCase
{
    const TEST_ROUTE = 'my_route';

    public function testAnIncludedRouteSuccess()
    {
        $request = new Request();
        $request->request->set('_route', self::TEST_ROUTE);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $sampler = new RouteSampler($requestStack, [self::TEST_ROUTE]);
        $this->assertTrue($sampler->isSampled(1));
    }

    public function testAnExcludedRouteSuccess()
    {
        $request = new Request();
        $request->request->set('_route', null, self::TEST_ROUTE);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $sampler = new RouteSampler($requestStack, [self::TEST_ROUTE]);
        $this->assertFalse($sampler->isSampled(1));
    }
}
