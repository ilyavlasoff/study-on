<?php

namespace App\Exception;

use Throwable;

class ValidationException extends \Exception
{
    /**
     * @var string[]
     */
    private $details;

    public function __construct($details, $message = 'Validation exception occurred', $code = 0, Throwable $previous = null)
    {
        $this->details = $details;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string[]
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
