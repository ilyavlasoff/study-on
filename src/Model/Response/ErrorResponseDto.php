<?php

namespace App\Model\Response;

use JMS\Serializer\Annotation as Serializer;

class ErrorResponseDto
{
    /**
     * @Serializer\Type("string")
     */
    private $error;

    /**
     * @Serializer\Type("int")
     */
    private $code;

    /**
     * @Serializer\Type("string")
     */
    private $message;

    /**
     * @Serializer\Type("array")
     */
    private $details;

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code): void
    {
        $this->code = $code;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param mixed $error
     */
    public function setError($error): void
    {
        $this->error = $error;
    }

    /**
     * @return mixed
     */
    public function getDetails()
    {
        return $this->details;
    }

    /**
     * @param mixed $details
     */
    public function setDetails($details): void
    {
        $this->details = $details;
    }
    
}
