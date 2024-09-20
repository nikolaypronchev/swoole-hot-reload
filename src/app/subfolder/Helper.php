<?php

namespace nikolaypronchev\SwooleHotReload\app\subfolder;

class Helper
{
    public function greet(int $workerId): string
    {
        return "Hello from App #$workerId! (Made by Helper class)";
    }
}
