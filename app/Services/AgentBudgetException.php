<?php
namespace App\Services;

/** Local execution stopped before another action or provider transfer could start. */
final class AgentBudgetException extends \RuntimeException
{
    public function __construct(public readonly string $reason, string $message) {
        parent::__construct($message);
    }
}
