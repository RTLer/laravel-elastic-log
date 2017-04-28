<?php
namespace Rtler\Logger;

use Carbon\Carbon;
use DebugBar\Bridge\MonologCollector as parentCollector;

class MonologCollector extends parentCollector
{
    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        $this->records[] = array(
            'message' => $record['message'],
            'label' => strtolower($record['level_name']),
            'time' => Carbon::instance($record['datetime'])
        );
    }
}
