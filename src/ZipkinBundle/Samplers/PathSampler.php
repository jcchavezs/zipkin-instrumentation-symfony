<?php

namespace ZipkinBundle\Samplers;

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
     * @param string $traceId
     * @return bool
     * @throws InvalidArgumentException
     */
    public function isSampled(string $traceId): bool
    {
        $masterRequest = $this->requestStack->getMasterRequest();
        if ($masterRequest === null) {
            return false;
        }

        $requestPath = $masterRequest->getRequestUri();

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
