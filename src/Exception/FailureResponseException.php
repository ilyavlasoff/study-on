<?php

namespace App\Exception;

use App\Model\Response\ErrorResponseDto;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class FailureResponseException extends HttpException
{
    private $error;

    public function __construct(ErrorResponseDto $error, $code = 0, Throwable $previous = null)
    {
        $this->error = $error;
        parent::__construct(500, 'Wrong response was received', $previous, [], $code);
    }

    public function getError(): ErrorResponseDto
    {
        return $this->error;
    }
}
