<?php

namespace App\Exception;

use App\Model\Response\ErrorResponseDto;
use Throwable;

class FailureResponseException extends \Exception
{
    private $error;

    public function __construct(ErrorResponseDto $error, $code = 0, Throwable $previous = null)
    {
        $this->error = $error;
        parent::__construct('Wrong response was received', $code, $previous);
    }

    public function getError(): ErrorResponseDto
    {
        return $this->error;
    }
}
