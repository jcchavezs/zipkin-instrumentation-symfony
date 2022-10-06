<?php

namespace ZipkinBundle\Samplers;

use Symfony\Component\HttpKernel\Kernel;
use Zipkin\Sampler;
use Symfony\Component\HttpFoundation\RequestStack;
use InvalidArgumentException;

final class PathSampler implements Sampler
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var array
     */
    private $includedPaths;

    /**
     * @var array
     */
    private $excludedPaths;

    public function __construct(RequestStack $requestStack, array $includedPaths = [], array $excludedPaths = [])
    {
        $this->requestStack = $requestStack;
        $this->includedPaths = $includedPaths;
        $this->excludedPaths = $excludedPaths;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isSampled(string $traceId): bool
    {
        $mainRequest = $this->requestStack->getMainRequest();

        if ($mainRequest === null) {
            return false;
        }

        $requestPath = $mainRequest->getRequestUri();

        if ([] !== $this->includedPaths) {
            foreach ($this->includedPaths as $pathPattern) {
                if (1 === preg_match('#' . str_replace('/', '\/', $pathPattern) . '#', $requestPath)) {
                    return true;
                }
            }

            return false;
        }

        if ([] !== $this->excludedPaths) {
            foreach ($this->excludedPaths as $pathPattern) {
                if (1 === preg_match('#' . str_replace('/', '\/', $pathPattern) . '#', $requestPath)) {
                    return false;
                }
            }

            return true;
        }

        throw new InvalidArgumentException('Either includedPaths or excludedPaths should be not empty');
    }
}
