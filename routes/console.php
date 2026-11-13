<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('nexora:status', function (): void {
    $this->info('NEXORA application bootstrap is available.');
})->purpose('Display NEXORA bootstrap status');
