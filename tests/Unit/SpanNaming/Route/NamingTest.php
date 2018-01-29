<?php

namespace ZipkinBundle\Tests\Unit\SpanNaming\Route;

use PHPUnit_Framework_TestCase;
use Symfony\Component\HttpFoundation\Request;
use ZipkinBundle\SpanNaming\Route\Naming;

final class NamingTest extends PHPUnit_Framework_TestCase
{
    public function testGetNameSuccess()
    {
        $naming = Naming::create(__DIR__);
        $name = $naming->getName(new Request());
        $this->assertEquals('GET', $name);
    }
}
