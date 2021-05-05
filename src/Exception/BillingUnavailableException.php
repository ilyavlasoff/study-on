<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

class BillingUnavailableException extends HttpException
{
    public function __construct(int $statusCode = 500, string $message = null, \Throwable $previous = null, array $headers = [], ?int $code = 0)
    {
        parent::__construct($statusCode, $message, $previous, $headers, $code);
    }
}
