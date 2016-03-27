<?php namespace PharmIT\RequestLog;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application;
use PharmIT\RequestLog\Middleware\RequestLogMiddleware;

class RequestLogServiceProvider extends ServiceProvider
{

    private static $configPath = __DIR__ . '/../config/config.php';

    public $defer = true;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [RequestLogMiddleware::class];
    }

    public function register()
    {
        $this->app->singleton(RequestLogMiddleware::class, function ($app) {
            return new RequestLogMiddleware($app['config']->get('requestLogger.connection.uri'));
        });

        $this->mergeConfigFrom(self::$configPath, 'requestLogger');
    }

    public function boot()
    {
        if (!$this->app instanceof Application || !$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([self::$configPath => config_path('requestLogger.php')]);
    }
}
