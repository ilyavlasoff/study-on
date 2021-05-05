<?php

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ValidationException extends HttpException
{
    /**
     * @var string[]
     */
    private $details;

    public function __construct($details, $message = 'Validation exception occurred', $code = 0, Throwable $previous = null)
    {
        $this->details = $details;
        parent::__construct(400, $message, $previous, [], $code);
    }

    /**
     * @return string[]
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
