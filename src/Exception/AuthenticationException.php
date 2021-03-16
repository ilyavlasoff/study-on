<?php

namespace App\Exception;

use App\Model\AuthenticationErrorDto;
use Throwable;

class AuthenticationException extends \Exception
{
    private $authenticationError;

    public function __construct(AuthenticationErrorDto $authenticationError, $code = 0, Throwable $previous = null)
    {
        $this->authenticationError = $authenticationError;
        parent::__construct($this->authenticationError->getMessage(), $code, $previous);
    }

    public function __toString()
    {
        /** @var string $message */
        $message = $this->authenticationError->getMessage();
        return $message;
    }
}