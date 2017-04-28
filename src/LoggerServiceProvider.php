<?php

namespace Rtler\Logger;

use DebugBar\DataFormatter\DataFormatter;
use DebugBar\DataFormatter\DataFormatterInterface;
use Illuminate\Support\ServiceProvider;

class LoggerServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['config']->set('reporter', array_merge(
            require __DIR__ . '/config/debugbar.php',
            require __DIR__ . '/config/reporter.php'
        ));

        $this->app->alias(
            DataFormatter::class,
            DataFormatterInterface::class
        );

        $this->app->singleton('reporter', function ($app) {
            $reporter = new LaravelLogger($app);
            return $reporter;
        }
        );

        $this->app->alias('reporter', LaravelLogger::class);
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $app = $this->app;

        $configPath = __DIR__ . '/config/reporter.php';
        $this->publishes([$configPath => $this->getConfigPath()], 'config');

        $enabled = $this->app['config']->get('reporter.enabled');

        if (!$enabled) {
            return;
        }


        if ($app->runningInConsole() || $app->environment('testing')) {
            return;
        }

        /** @var LaravelLogger $reporter */
        $reporter = $this->app['reporter'];
        $reporter->enable();
        $reporter->boot();

        $this->registerMiddleware(LoggerMiddleware::class);
    }

    /**
     * Get the config path
     *
     * @return string
     */
    protected function getConfigPath()
    {
        return config_path('reporter.php');
    }

    /**
     * Register the Reporter Middleware
     *
     * @param  string $middleware
     */
    protected function registerMiddleware($middleware)
    {
        $kernel = $this->app['Illuminate\Contracts\Http\Kernel'];
        $kernel->pushMiddleware($middleware);
    }
}
