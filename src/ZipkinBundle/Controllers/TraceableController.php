<?php

namespace ZipkinBundle\Controllers;

interface TraceableController
{
    public function operationName($action = null);
}
