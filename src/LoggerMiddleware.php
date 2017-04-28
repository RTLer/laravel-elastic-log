<?php

namespace Rtler\Logger;

use Closure;
use Error;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class LoggerMiddleware
{
    /**
     * The App container
     *
     * @var Container
     */
    protected $container;

    /**
     * The DebugBar instance
     *
     * @var LaravelLogger
     */
    protected $reporter;

    /**
     * Create a new middleware instance.
     *
     * @param  Container $container
     * @param  LaravelLogger $reporter
     */
    public function __construct(Container $container, LaravelLogger $reporter)
    {
        $this->container = $container;
        $this->reporter = $reporter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request $request
     * @param  Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            /** @var \Illuminate\Http\Response $response */
            $response = $next($request);
        } catch (Exception $e) {
            $response = $this->handleException($request, $e);
        } catch (Error $error) {
            $e = new FatalThrowableError($error);
            $response = $this->handleException($request, $e);
        }

        $this->send($request);

        return $response;

    }

    /**
     * Handle the given exception.
     *
     * (Copy from Illuminate\Routing\Pipeline by Taylor Otwell)
     *
     * @param $passable
     * @param  Exception $e
     * @return mixed
     * @throws Exception
     */
    protected function handleException($passable, Exception $e)
    {
        if (!$this->container->bound(ExceptionHandler::class) || !$passable instanceof Request) {
            throw $e;
        }

        $handler = $this->container->make(ExceptionHandler::class);

        $handler->report($e);

        return $handler->render($passable, $e);
    }

    /**
     * @param $request
     */
    protected function send($request)
    {
        $reporterJob = new SendToReporterJob($this->reporter->getReport($request));

        if (config('reporter.report.queue', false)) {
            dispatch($reporterJob);
            return;
        }
        $reporterJob->handle();
    }
}
