<?php

namespace ZipkinBundle\Propagation;

use Zipkin\Propagation\Getter;
use Symfony\Component\HttpFoundation\Request;

class RequestHeaders implements Getter
{
    /**
     * @param Request $carrier
     */
    public function get($carrier, string $key): ?string
    {
        return $carrier->headers->get($key);
    }
}
