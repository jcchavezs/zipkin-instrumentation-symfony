<?php

namespace ZipkinBundle\Samplers;

use Zipkin\Sampler;
use Symfony\Component\HttpFoundation\RequestStack;
use InvalidArgumentException;

final class RouteSampler implements Sampler
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var array
     */
    private $includedRoutes;

    /**
     * @var array
     */
    private $excludedRoutes;

    public function __construct(RequestStack $requestStack, array $includedRoutes = [], array $excludedRoutes = [])
    {
        $this->requestStack = $requestStack;
        $this->includedRoutes = $includedRoutes;
        $this->excludedRoutes = $excludedRoutes;
    }

    /**
     * @param int $traceId
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function isSampled($traceId): bool
    {
        $masterRequest = $this->requestStack->getMasterRequest();
        if ($masterRequest === null) {
            return false;
        }

        $route = $masterRequest->get('_route');

        if ([] !== $this->includedRoutes) {
            if (in_array($route, $this->includedRoutes)) {
                return true;
            }

            return false;
        }

        if ([] !== $this->excludedRoutes) {
            if (in_array($route, $this->excludedRoutes)) {
                return false;
            }

            return true;
        }

        throw new InvalidArgumentException('Either includedRoutes or excludedRoutes should be not empty.');
    }
}
