<?php

namespace App\Exception;

use App\Model\FailureResponseDto;
use Throwable;

class FailureResponseException extends \Exception
{
    private $failureResponse;

    public function __construct(FailureResponseDto $failureResponse, $code = 0, Throwable $previous = null)
    {
        $this->failureResponse = $failureResponse;
        parent::__construct('', $code, $previous);
    }

    public function getFailureErrors(): array
    {
        return $this->failureResponse->getError();
    }
}
