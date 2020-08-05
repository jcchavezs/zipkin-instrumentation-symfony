<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class PropagationStamp implements StampInterface
{
    /**
     * @var string[string]
     */
    private $context = [];

    public function get(string $key): ?string
    {
        return $this->context[$key] ?? null;
    }

    public function put(string $key, string $value): void
    {
        $this->context[$key] = $value;
    }
}
