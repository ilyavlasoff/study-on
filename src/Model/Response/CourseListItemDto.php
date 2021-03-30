<?php

namespace App\Model;

use JMS\Serializer\Annotation as JMS;

class CourseListItemDto
{
    /**
     * @var string
     * @JMS\Type("string")
     */
    private $code;

    /**
     * @var string
     * @JMS\Type("string")
     */
    private $type;

    /**
     * @var string
     * @JMS\Type("string")
     */
    private $title;

    /**
     * @var float
     * @JMS\Type("float")
     */
    private $price;

    /**
     * @var \DateInterval
     * @JMS\Type("dateinterval")
     */
    private $rentTime;

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    /**
     * @return \DateInterval
     */
    public function getRentTime(): \DateInterval
    {
        return $this->rentTime;
    }

    /**
     * @param \DateInterval $rentTime
     */
    public function setRentTime(\DateInterval $rentTime): void
    {
        $this->rentTime = $rentTime;
    }


}