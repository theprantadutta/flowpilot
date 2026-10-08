<?php

namespace App\Workflows\Nodes;

use RuntimeException;

/**
 * A step could not do its job. Retryable failures (a receiving system was
 * down) are tried again; others (a record was deleted) fail the run at once.
 */
class StepFailed extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }

    public static function retryable(string $message): self
    {
        return new self($message, true);
    }

    public static function permanent(string $message): self
    {
        return new self($message, false);
    }
}
