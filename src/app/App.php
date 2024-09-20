<?php

namespace nikolaypronchev\SwooleHotReload\app;

use Swoole\Http\Request;
use Swoole\Http\Response;
use nikolaypronchev\SwooleHotReload\app\subfolder\Helper;

class App
{
    public function __construct(protected int $workerId)
    {
    }

    public function resolve(Request $request, Response $response): void
    {
        $response->header("Content-Type", "text/plain");
        $response->end((new Helper())->greet($this->workerId));
    }
}
