<?php namespace Sergomet\Sms;

use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('sms_providers.php'),
        ], 'sms_providers');
        
        $this->loadViewsFrom(__DIR__.'/views', 'sms');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Sergomet\Sms\SMS::class, function ($app) {
            return new SMS();
        });
    }
}
