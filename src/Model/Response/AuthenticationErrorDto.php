<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;

class AuthenticationErrorDto
{
    /**
     * @Serializer\Type("int")
     */
    private $code;

    /**
     * @Serializer\Type("string")
     */
    private $message;

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
}