<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('oee:pull-node-red')
    ->everyFiveSeconds()
    ->withoutOverlapping()
    ->when(fn (): bool => filled(config('oee.node_red_url')));
