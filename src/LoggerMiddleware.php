<?php

namespace Rtler\Logger;

use Error;
use Closure;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
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

        // Modify the response to add the Debugbar
        try {
            $httpClient = new Client(['base_uri' => 'http://127.0.0.1:8080/api/']);
            $httpClient->post('report', [
                'json' => $this->reporter->getReport($request),
            ]);
        } catch (RequestException $exception) {
            $exception->getResponse();
        } catch (TransferException $exception) {
//            $exception;
        }
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
}
