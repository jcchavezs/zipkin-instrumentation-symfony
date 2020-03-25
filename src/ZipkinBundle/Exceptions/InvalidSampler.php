<?php

namespace ZipkinBundle\Exceptions;

use RuntimeException;

final class InvalidSampler extends RuntimeException
{
    /**
     * @param string $type
     */
    public static function forInvalidType($type)
    {
        return new self(
            sprintf('Unkown sampler type: "%s"', $type)
        );
    }

    /**
     * @param string $className
     */
    public static function forInvalidCustomSampler($className)
    {
        return new self(
            sprintf('Object of class "%s" is not a valid sampler', $className)
        );
    }

    /**
     * @param string $id
     */
    public static function forUnkownService($id)
    {
        return new self(
            sprintf('Unknown service with id: "%s"', $id)
        );
    }
}
