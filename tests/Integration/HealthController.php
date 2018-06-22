<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HealthController
{
   /**
    * @Route("/_health")
    */
    public function health()
    {
        return new Response();
    }
}
