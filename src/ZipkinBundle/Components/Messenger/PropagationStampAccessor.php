<?php


namespace ZipkinBundle\Components\Messenger;

use Zipkin\Propagation\RemoteSetter;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Kind;

final class PropagationStampAccessor implements RemoteSetter, Getter
{
    /**
     * @var PropagationStamp $carrier
     */
    public function get($carrier, string $key): ?string
    {
        self::validateCarrier($carrier);
        return $carrier->get($key);
    }

    public function getKind(): string
    {
        return Kind\PRODUCER;
    }

    /**
     * @param PropagationStamp $carrier
     */
    public function put(&$carrier, string $key, string $value): void
    {
        self::validateCarrier($carrier);
        $carrier->put($key, $value);
    }

    private static function validateCarrier($carrier): void
    {
        if (!$carrier instanceof PropagationStamp) {
            throw InvalidPropagationCarrier::forCarrier($carrier);
        }
    }
}
