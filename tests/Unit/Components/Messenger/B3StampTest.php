<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use ZipkinBundle\Components\Messenger\B3Stamp;
use PHPUnit\Framework\TestCase;

class B3StampTest extends TestCase
{
    public function testSetterAndGetter()
    {
        $sut = new B3Stamp();

        $sut->add('KEY1', 'VALUE1');
        $sut->add('KEY2', 'VALUE2');

        $this->assertEquals('VALUE1', $sut->get('KEY1'));
        $this->assertEquals('VALUE2', $sut->get('KEY2'));
    }
}
