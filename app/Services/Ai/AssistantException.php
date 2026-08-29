<?php

namespace App\Services\Ai;

use RuntimeException;

/** A failure the user can be told about without leaking provider internals. */
class AssistantException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 502)
    {
        parent::__construct($message);
    }
}
