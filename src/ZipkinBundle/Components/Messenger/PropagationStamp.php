<?php


namespace ZipkinBundle\Components\Messenger;

use Zipkin\Kind;
use Zipkin\Propagation\Exceptions\InvalidPropagationCarrier;
use Zipkin\Propagation\Getter;
use Zipkin\Propagation\RemoteSetter;

class PropagationStamp implements RemoteSetter, Getter
{
    public function get($carrier, string $key): ?string
    {
        $this->validateCarrier($carrier);
        return $carrier->get($key);
    }

    public function getKind(): string
    {
        return Kind\PRODUCER;
    }

    public function put(&$carrier, string $key, string $value): void
    {
        $this->validateCarrier($carrier);
        $carrier->add($key, $value);
    }

    private function validateCarrier($carrier): void
    {
        if (!$carrier instanceof B3Stamp) {
            throw InvalidPropagationCarrier::forCarrier($carrier);
        }
    }
}
