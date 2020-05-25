<?php

namespace ZipkinBundle\Tests\Unit\Components\Messenger;

use ZipkinBundle\Components\Messenger\ZipkinStamp;
use PHPUnit\Framework\TestCase;

class ZipkinStampTest extends TestCase
{
    public function testSetterAndGetter()
    {
        $sut = new ZipkinStamp();

        $sut->add('KEY1', 'VALUE1');
        $sut->add('KEY2', 'VALUE2');

        $result = $sut->getContext();

        $this->assertEquals([
            'KEY1' => 'VALUE1',
            'KEY2' => 'VALUE2'
        ], $result);
    }
}
