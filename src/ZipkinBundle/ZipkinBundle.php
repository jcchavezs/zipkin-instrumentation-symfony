<?php

namespace ZipkinBundle;

use ZipkinBundle\DependencyInjection\ZipkinExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ZipkinBundle extends Bundle
{
    public function getContainerExtensionClass()
    {
        return ZipkinExtension::class;
    }
}
