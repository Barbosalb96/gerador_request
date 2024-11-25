<?php

namespace Lucas\Pacote;

use barbosalb96\Request\GenerateRequestCommand;
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