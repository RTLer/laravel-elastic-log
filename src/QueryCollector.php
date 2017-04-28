<?php

namespace Rtler\Logger;

use Barryvdh\Debugbar\DataCollector\QueryCollector as parentCollector;


class QueryCollector extends parentCollector
{
    /**
     *
     * @param string $query
     * @param array $bindings
     * @param float $time
     * @param \Illuminate\Database\Connection $connection
     */
    public function addQuery($query, $bindings, $time, $connection)
    {
        $explainResults = [];
        $time = $time / 1000;
        $endTime = microtime(true);
        $startTime = $endTime - $time;
        $hints = $this->performQueryAnalysis($query);

        $pdo = $connection->getPdo();
        $bindings = $connection->prepareBindings($bindings);

        // Run EXPLAIN on this query (if needed)
        if ($this->explainQuery && preg_match('/^(' . implode($this->explainTypes) . ') /i', $query)) {
            $statement = $pdo->prepare('EXPLAIN ' . $query);
            $statement->execute($bindings);
            $explainResults = $statement->fetchAll(\PDO::FETCH_CLASS);
        }

        $bindings = $this->checkBindings($bindings);
        if (!empty($bindings) && $this->renderSqlWithParams) {
            foreach ($bindings as $key => $binding) {
                // This regex matches placeholders only, not the question marks,
                // nested in quotes, while we iterate through the bindings
                // and substitute placeholders by suitable values.
                $regex = is_numeric($key)
                    ? "/\?(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/"
                    : "/:{$key}(?=(?:[^'\\\']*'[^'\\\']*')*[^'\\\']*$)/";
                $query = preg_replace($regex, $pdo->quote($binding), $query, 1);
            }
        }

        $source = null;
        if ($this->findSource) {
            try {
                $source = $this->findSource();
            } catch (\Exception $e) {
            }
        }
        $this->queries[] = [
            'query' => $query,
            'bindings' => $this->escapeBindings($bindings),
            'start_at' => $startTime,
            'time' => $time,
            'source' => $source,
            'explain' => $explainResults,
            'driver' => $connection->getDriverName(),
            'hints' => $this->showHints ? $hints : null,
        ];

        if ($this->timeCollector !== null) {
            $this->timeCollector->addMeasure($query, $startTime, $endTime);
        }
    }


    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        $totalTime = 0;
        $queries = $this->queries;

        $statements = [];
        foreach ($queries as $query) {
            $totalTime += $query['time'];

            $bindings = $query['bindings'];
            if($query['hints']){
                $bindings['hints'] = $query['hints'];
            }

            $statements[] = [
                'sql' => $this->formatSql($query['query']),
//                'params' => (object) $bindings,
                'start_at' => $query['start_at'],
                'duration' => $query['time'],
                'stmt_id' => $query['source'],
                'driver' => $query['driver'],
            ];

            //Add the results from the explain as new rows
            foreach($query['explain'] as $explain){
                $statements[] = [
                    'sql' => ' - EXPLAIN #' . $explain->id . ': `' . $explain->table . '` (' . $explain->select_type . ')',
                    'params' => $explain,
                    'row_count' => $explain->rows,
                    'stmt_id' => $explain->id,
                ];
            }
        }

        $data = [
            'nb_statements' => count($queries),
            'nb_failed_statements' => 0,
            'accumulated_duration' => $totalTime,
            'statements' => $statements
        ];
        return $data;
    }
}
