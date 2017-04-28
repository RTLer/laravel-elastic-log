<?php

namespace Rtler\Logger;

use Barryvdh\Debugbar\DataCollector\AuthCollector;
use Barryvdh\Debugbar\DataCollector\EventCollector;
use Barryvdh\Debugbar\DataCollector\LaravelCollector;
use Barryvdh\Debugbar\DataCollector\MultiAuthCollector;
use Barryvdh\Debugbar\DataCollector\ViewCollector;
use Barryvdh\Debugbar\LaravelDebugbar as parentCollector;
use Carbon\Carbon;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\DataCollector\TimeDataCollector;
use Exception;
use Illuminate\Contracts\Foundation\Application;

/**
 * Debug bar subclass which adds all without Request and with LaravelCollector.
 * Rest is added in Service Provider
 */
class LaravelLogger extends parentCollector
{
    /**
     * Boot the debugbar (add collectors, renderer and listener)
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        /** @var \Barryvdh\Debugbar\LaravelDebugbar $debugbar */
        $debugbar = $this;

        /** @var Application $app */
        $app = $this->app;

        $this->selectStorage($debugbar);

        if ($this->shouldCollect('phpinfo', true)) {
            $this->addCollector(new PhpInfoCollector());
        }

        if ($this->shouldCollect('time', true)) {
            $this->addCollector(new TimeDataCollector());

            if (!$this->isLumen()) {
                $this->app->booted(
                    function () use ($debugbar) {
                        $startTime = $this->app['request']->server('REQUEST_TIME_FLOAT');
                        if ($startTime) {
                            $debugbar['time']->addMeasure('Booting', $startTime, microtime(true));
                        }
                    }
                );
            }

            $debugbar->startMeasure('application', 'Application');
        }

        if ($this->shouldCollect('memory', true)) {
            $this->addCollector(new MemoryCollector());
        }

        if ($this->shouldCollect('exceptions', true)) {
            try {
                $exceptionCollector = new ExceptionsCollector();
                $exceptionCollector->setChainExceptions(
                    $this->app['config']->get('debugbar.options.exceptions.chain', true)
                );
                $this->addCollector($exceptionCollector);
            } catch (\Exception $e) {
            }
        }

        if ($this->shouldCollect('laravel', false)) {
            $this->addCollector(new LaravelCollector($this->app));
        }

        if ($this->shouldCollect('events', false) && isset($this->app['events'])) {
            try {
                $startTime = $this->app['request']->server('REQUEST_TIME_FLOAT');
                $eventCollector = new EventCollector($startTime);
                $this->addCollector($eventCollector);
                $this->app['events']->subscribe($eventCollector);

            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add EventCollector to Laravel Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        if ($this->shouldCollect('views', true) && isset($this->app['events'])) {
            try {
                $collectData = $this->app['config']->get('debugbar.options.views.data', true);
                $this->addCollector(new ViewCollector($collectData));
                $this->app['events']->listen(
                    'composing:*',
                    function ($view, $data = []) use ($debugbar) {
                        if ($data) {
                            $view = $data[0]; // For Laravel >= 5.4
                        }
                        $debugbar['views']->addView($view);
                    }
                );
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add ViewCollector to Laravel Debugbar: ' . $e->getMessage(), $e->getCode(), $e
                    )
                );
            }
        }

        if (!$this->isLumen() && $this->shouldCollect('route')) {
            try {
                $this->addCollector($this->app->make('Barryvdh\Debugbar\DataCollector\IlluminateRouteCollector'));
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add RouteCollector to Laravel Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }

        if (!$this->isLumen() && $this->shouldCollect('log', true)) {
            try {
                $this->addCollector(new MonologCollector($this->app['log']->getMonolog()));
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add LogsCollector to Laravel Debugbar: ' . $e->getMessage(), $e->getCode(), $e
                    )
                );
            }
        }

        if ($this->shouldCollect('db', true) && isset($this->app['db'])) {
            $db = $this->app['db'];
            if ($debugbar->hasCollector('time') && $this->app['config']->get(
                    'debugbar.options.db.timeline',
                    false
                )
            ) {
                $timeCollector = $debugbar->getCollector('time');
            } else {
                $timeCollector = null;
            }
            $queryCollector = new QueryCollector($timeCollector);

            if ($this->app['config']->get('debugbar.options.db.with_params')) {
                $queryCollector->setRenderSqlWithParams(true);
            }

            if ($this->app['config']->get('debugbar.options.db.backtrace')) {
                $queryCollector->setFindSource(true);
            }

            if ($this->app['config']->get('debugbar.options.db.explain.enabled')) {
                $types = $this->app['config']->get('debugbar.options.db.explain.types');
                $queryCollector->setExplainSource(true, $types);
            }

            if ($this->app['config']->get('debugbar.options.db.hints', true)) {
                $queryCollector->setShowHints(true);
            }

            $this->addCollector($queryCollector);

            try {
                $db->listen(
                    function ($query, $bindings = null, $time = null, $connectionName = null) use ($db, $queryCollector) {
                        // Laravel 5.2 changed the way some core events worked. We must account for
                        // the first argument being an "event object", where arguments are passed
                        // via object properties, instead of individual arguments.
                        if ($query instanceof \Illuminate\Database\Events\QueryExecuted) {
                            $bindings = $query->bindings;
                            $time = $query->time;
                            $connection = $query->connection;

                            $query = $query->sql;
                        } else {
                            $connection = $db->connection($connectionName);
                        }

                        $queryCollector->addQuery((string)$query, $bindings, $time, $connection);
                    }
                );
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add listen to Queries for Laravel Debugbar: ' . $e->getMessage(),
                        $e->getCode(),
                        $e
                    )
                );
            }
        }


        if ($this->shouldCollect('auth', false)) {
            try {
                if ($this->checkVersion('5.2')) {
                    // fix for compatibility with Laravel 5.2.*
                    $guards = array_keys($this->app['config']->get('auth.guards'));
                    $authCollector = new MultiAuthCollector($app['auth'], $guards);
                } else {
                    $authCollector = new AuthCollector($app['auth']);
                }

                $authCollector->setShowName(
                    $this->app['config']->get('debugbar.options.auth.show_name')
                );
                $this->addCollector($authCollector);
            } catch (\Exception $e) {
                $this->addThrowable(
                    new Exception(
                        'Cannot add AuthCollector to Laravel Debugbar: ' . $e->getMessage(), $e->getCode(), $e
                    )
                );
            }
        }


        $renderer = $this->getJavascriptRenderer();
        $renderer->setIncludeVendors($this->app['config']->get('debugbar.include_vendors', true));
        $renderer->setBindAjaxHandlerToXHR($app['config']->get('debugbar.capture_ajax', true));

        $this->booted = true;
    }

    /**
     * @param $request
     * @return array
     */
    public function getReport($request)
    {
        $collectorData = [];
        foreach ($this->getCollectors() as $collector) {
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collectorData[$collector->getName()] = $collector->collect();
        }
        $user = [];
        if (auth()->check()) {
            $user = [
                "id" => auth()->id(),
                "name" => auth()->user()->name,
                "email" => auth()->user()->email,
            ];
        }
        $logs = [];
        foreach ($collectorData['monolog']['records'] as $record) {
            $logs[] = [
                "content" => $record['message'],
                "level" => $record['label'],
                "type" => 'log',
                "start_at" => $record['time']->toAtomString(),
                "interval_time" => 0.0,
            ];
        }
        foreach ($collectorData['event']['measures'] as $record) {
            $logs[] = [
                "content" => $record['label'],
                "type" => 'event',
                "start_at" => Carbon::createFromTimestamp($record['start'])->toAtomString(),
                "interval_time" => $record['duration'] * 1000,
            ];
        }

        foreach ($collectorData['queries']['statements'] as $record) {
            $logs[] = [
                "content" => $record['sql'],
                "type" => 'query',
                "start_at" => Carbon::createFromTimestamp($record['start_at'])->toAtomString(),
                "interval_time" => $record['duration'] * 1000,
            ];
        }

        $exceptions = [];
        foreach ($collectorData['exceptions']['exceptions'] as $exception) {
            $stackTrace = [];
            $sequence = 1;
            foreach ($exception['stackTrace'] as $trace) {
                $traceItem = [
                    "sequence" => $sequence++,
                    "file" => $trace['file'],
                    "line" => $trace['line'],
                ];
                if (array_has($trace, 'class')) {
                    $traceItem['class'] = $trace['class'];
                }
                if (array_has($trace, 'function')) {
                    $traceItem['function'] = $trace['function'];
                }
                $stackTrace[] = $traceItem;
            }

            $exceptions[] = [
                "exception" => $exception['type'],
                "file" => $exception['file'],
                "message" => $exception['message'],
                "line" => $exception['line'],
                "code" => implode('', $exception['surrounding_lines']),
                "stacktrace" => $stackTrace,
            ];
        }

        $report = [
            "api_key" => "2e737ab2-54c7-4892-9902-325258f904f1",
            "datetime" => Carbon::now()->toAtomString(),
            "process" => [
                "stage" => array_get($collectorData, 'laravel.environment'),
                "interval" => array_get($collectorData, 'time.duration') * 1000,
                "request_id" => array_get($collectorData, 'route.controller', array_get($collectorData, 'route.file')),
                "sessions" => [
                    "user" => $user,
                    "request" => $request->except(['pass', 'password']),
                ],
                "system" => array_merge(
                    array_dot(array_only($collectorData, 'php')),
                    array_dot(array_only($collectorData, 'laravel')),
                    [
                        "Memory_peak" => array_get($collectorData, 'memory.peak_usage'),
                    ]
                ),
            ],
            "logs" => $logs,
            "exceptions" => $exceptions,
        ];
        return $report;
    }
}
