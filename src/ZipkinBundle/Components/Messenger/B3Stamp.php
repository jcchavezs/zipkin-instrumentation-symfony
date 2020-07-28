<?php

namespace ZipkinBundle\Components\Messenger;

use Symfony\Component\Messenger\Stamp\StampInterface;

class B3Stamp implements StampInterface
{
    private $context = [];

    public function add(string $key, string $value): void
    {
        $this->context[$key] = $value;
    }

    public function get(string $key): ?string
    {
        return $this->context[$key] ?? null;
    }
}
