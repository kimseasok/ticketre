<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Illuminate\Support\Str;
use Monolog\LogRecord;

class CorrelationIdProcessor
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(function ($record) {
            $correlation = null;

            if (function_exists('request') && app()->bound('request')) {
                $correlation = request()->headers->get('X-Correlation-ID');
            }

            $value = $correlation ?? Str::uuid()->toString();

            if ($record instanceof LogRecord) {
                $record->extra['correlation_id'] = $value;

                return $record;
            }

            if (is_array($record)) {
                $record['extra']['correlation_id'] = $value;
            }

            return $record;
        });
    }
}
