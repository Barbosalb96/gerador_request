<?php

namespace barbosalb96;

use Illuminate\Support\ServiceProvider;

class GenerateRequestServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateRequestCommand::class,
            ]);
        }
    }
}