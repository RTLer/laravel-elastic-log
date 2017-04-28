<?php
namespace Rtler\Logger;

use DebugBar\DataCollector\ExceptionsCollector as parentCollector;


/**
 * Collects info about exceptions
 */
class ExceptionsCollector extends parentCollector
{
    /**
     * Returns Throwable data as an array
     *
     * @param \Throwable $e
     * @return array
     */
    public function formatThrowableData($e)
    {
        $filePath = $e->getFile();
        if ($filePath && file_exists($filePath)) {
            $lines = file($filePath);
            $start = $e->getLine() - 4;
            $lines = array_slice($lines, $start < 0 ? 0 : $start, 7);
        } else {
            $lines = array("Cannot open the file ($filePath) in which the exception occurred ");
        }

        return array(
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $filePath,
            'line' => $e->getLine(),
            'stackTrace' => $e->getTrace(),
            'surrounding_lines' => $lines
        );
    }
}
