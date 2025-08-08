<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Marwa\Logging\ErrorHandler;
use Marwa\Logging\Config\Settings;
use Marwa\Logging\Contracts\Reportable as ReportableContract;
use Psr\Log\LoggerInterface;

/**
 * 1) Bootstrap the error handler in one call.
 *    - JSON logs
 *    - Size + date rotation
 *    - Dev/Prod behavior auto from 'env'
 */
$handler = ErrorHandler::bootstrap([
    'app_name'       => 'myapp',
    'env'            => 'development',              // change to 'production' in prod
    'log_path'       => __DIR__ . '/storage/logs',
    'max_log_bytes'  => '10MB',
    'sensitive_keys' => ['password', 'token', 'authorization'],
]);

/**
 * 2) Enable the ExceptionReporter and configure it (Laravel-style)
 */
$handler->enableExceptionReporter();
$reporter = $handler->getReporter();

// (Optional) don't report these exception classes at all
$reporter->dontReport([
    InvalidArgumentException::class,
]);

// (Optional) custom reporter for a specific type (or a base class)
$reporter->reportable(RuntimeException::class, function (RuntimeException $e, LoggerInterface $logger) {
    $logger->critical('RuntimeException handled by custom reportable', [
        '_origin' => 'system',
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    // Returning void; default in our reporter treats this as "handled"
});

// (Optional) fallback reporter if nothing else matched
$reporter->fallback(function (Throwable $e, LoggerInterface $logger) {
    $logger->alert('Unhandled exception reached fallback', [
        '_origin' => 'system',
        'type'    => $e::class,
        'message' => $e->getMessage(),
    ]);
});

/**
 * 3) Example custom exception implementing Reportable
 *    - Can decide its own log level, context, and whether it's fully handled.
 */
final class PaymentDeclined extends RuntimeException implements ReportableContract
{
    public function __construct(
        public readonly string $txnId,
        public readonly float $amount,
        public readonly string $reason
    ) {
        parent::__construct("Payment declined: {$reason}");
    }

    // If you return true, default logging is skipped
    public function report(LoggerInterface $logger): bool
    {
        $logger->warning('payment_declined', [
            '_origin' => 'system',
            'txn_id'  => $this->txnId,
            'amount'  => $this->amount,
            'reason'  => $this->reason,
        ]);
        return true; // handled (no default "uncaught_exception" entry)
    }

    public function context(): array
    {
        return ['txn_id' => $this->txnId, 'amount' => $this->amount, 'reason' => $this->reason];
    }

    public function level(): ?string
    {
        return 'warning';
    }
}

/**
 * 4) Manually log something (PSR-3 levels)
 *    - In development: all logs are written (user + system)
 *    - In production: only logs with ['_origin' => 'system'] are written
 */
$logger = $handler->getLogger(); // add a getter in ErrorHandler if you haven’t already
$logger->info('User clicked button', ['user_id' => 42]); // dev only (user-origin)
$logger->error('cache_miss', ['_origin' => 'system', 'key' => 'users:42']); // always in prod

/**
 * 5) Throw a few exceptions to demonstrate behavior
 *    We catch them here just to keep this script running and let the reporter handle logging.
 */

// A) Custom Reportable exception (uses its own report() and level())
try {
    throw new PaymentDeclined('TXN-1234', 199.99, 'insufficient_funds');
} catch (Throwable $e) {
    $reporter->report($e);
}

// B) Exception in dontReport list — will be ignored
try {
    throw new InvalidArgumentException('Bad input');
} catch (Throwable $e) {
    $reporter->report($e);
}

// C) Matches the RuntimeException reportable handler
try {
    throw new RuntimeException('Something went bang');
} catch (Throwable $e) {
    $reporter->report($e);
}

// D) Not handled by custom rules -> default error log + fallback alert
try {
    throw new LogicException('Unhandled type will hit fallback');
} catch (Throwable $e) {
    $reporter->report($e);
}

echo "Done. Check logs in {$__DIR__}/storage/logs\n";
