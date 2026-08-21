<?php

namespace App\Exception;

final class UwuLogsException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
