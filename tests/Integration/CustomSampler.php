<?php

namespace App\Sampler;

use Zipkin\Sampler;

final class CustomSampler implements Sampler
{
    public function isSampled($traceId): bool
    {
        $out = fopen('php://stdout', 'w');
        fputs($out, "Using custom sampler :)\n");
        fclose($out);
        return true;
    }
}
