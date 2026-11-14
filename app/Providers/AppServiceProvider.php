<?php

namespace App\Providers;

use App\Core\Site\Domains\DnsRecordResolver;
use App\Core\Site\Domains\NativeDnsRecordResolver;
use App\Core\Site\Domains\NativeTlsCertificateInspector;
use App\Core\Site\Domains\TlsCertificateInspector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DnsRecordResolver::class, NativeDnsRecordResolver::class);
        $this->app->singleton(TlsCertificateInspector::class, NativeTlsCertificateInspector::class);
    }

    public function boot(): void
    {
        //
    }
}
