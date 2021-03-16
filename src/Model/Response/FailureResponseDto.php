<?php

namespace App\Model;

use JMS\Serializer\Annotation as Serializer;

class FailureResponseDto
{
    /**
     * @Serializer\Type("string")
     */
    private $success;

    /**
     * @Serializer\Type("array")
     */
    private $error;

    /**
     * @return mixed
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * @param mixed $success
     */
    public function setSuccess($success): void
    {
        $this->success = $success;
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


}