<?php

namespace ZipkinBundle\RouteMapper;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

final class CacheWarmer implements CacheWarmerInterface
{
    private const TARGET_FILENAME = 'zipkin_bundle_route_mapper.php';

    /**
     * @var RouterInterface
     */
    private $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public static function buildOutputFilename($cacheDir)
    {
        return $cacheDir . '/' . self::TARGET_FILENAME;
    }

    public function warmUp($cacheDir)
    {
        $routes = "<?php return [";

        foreach ($this->router->getRouteCollection()->all() as $key => $route) {
            $routes .= sprintf('"%s" => "%s", ', $key, $route->getPath());
        }
        $routes .= "];";

        file_put_contents(
            self::buildOutputFilename($cacheDir),
            $routes
        );
    }

    public function isOptional()
    {
        return true;
    }
}
