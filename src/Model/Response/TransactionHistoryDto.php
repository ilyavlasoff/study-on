<?php

namespace App\Model\Response;

use App\Entity\Course;
use JMS\Serializer\Annotation as JMS;

class TransactionHistoryDto
{
    /**
     * @JMS\Type("datetime")
     */
    private $createdAt;

    /**
     * @JMS\Type("string")
     */
    private $type;

    /**
     * @JMS\Type("string")
     */
    private $courseCode;

    /**
     * @JMS\Type("float")
     */
    private $amount;

    /**
     * @var Course
     */
    private $localCourse;

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getCourseCode()
    {
        return $this->courseCode;
    }

    /**
     * @param mixed $courseCode
     */
    public function setCourseCode($courseCode): void
    {
        $this->courseCode = $courseCode;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount($amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return mixed
     */
    public function getLocalCourse()
    {
        return $this->localCourse;
    }

    /**
     * @param mixed $localCourse
     */
    public function setLocalCourse($localCourse): void
    {
        $this->localCourse = $localCourse;
    }
}
