<?php

namespace App\Support\WhatsApp;

use RuntimeException;

/** A WhatsApp send that failed; `retryable` is false for problems a retry cannot fix (bad number, template, token). */
class WhatsAppException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable, public readonly ?int $errorCode = null)
    {
        parent::__construct($message);
    }
}
