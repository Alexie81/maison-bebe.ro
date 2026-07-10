<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(private readonly int $httpStatus, string $message)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->httpStatus;
    }
}

