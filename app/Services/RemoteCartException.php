<?php
declare(strict_types=1);
namespace App\Services;

/** Safe public commerce failure; messages never contain capabilities or private records. */
final class RemoteCartException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 400,
        public readonly int $retryAfter = 0,
    ) { parent::__construct($message); }
}
