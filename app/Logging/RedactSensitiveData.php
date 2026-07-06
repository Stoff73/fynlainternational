<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Handler\ProcessableHandlerInterface;

/**
 * Monolog "tap" that attaches the RedactionProcessor to every handler on a
 * channel. Wired via `'tap' => [RedactSensitiveData::class]` in
 * config/logging.php (Test Gauntlet G-5 H-2).
 */
final class RedactSensitiveData
{
    public function __invoke(Logger $logger): void
    {
        $processor = new RedactionProcessor;

        foreach ($logger->getHandlers() as $handler) {
            if ($handler instanceof ProcessableHandlerInterface) {
                $handler->pushProcessor($processor);
            }
        }
    }
}
