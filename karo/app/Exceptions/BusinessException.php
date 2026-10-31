<?php

namespace App\Exceptions;

use RuntimeException;

/** Règle de gestion violée : message destiné à l'utilisateur, en français. */
class BusinessException extends RuntimeException
{
    public function __construct(string $message, private array $details = [])
    {
        parent::__construct($message);
    }

    public function details(): array
    {
        return $this->details;
    }
}
