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
     * @var bool
     * @JMS\Type("bool")
     */
    private $owned;

    /**
     * @var \DateTime
     * @JMS\Type("datetime")
     */
    private $ownedUntil;

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

    /**
     * @return bool
     */
    public function isOwned(): bool
    {
        return $this->owned;
    }

    /**
     * @param bool $owned
     */
    public function setOwned(bool $owned): void
    {
        $this->owned = $owned;
    }

    /**
     * @return \DateTime
     */
    public function getOwnedUntil(): \DateTime
    {
        return $this->ownedUntil;
    }

    /**
     * @param \DateTime $ownedUntil
     */
    public function setOwnedUntil(\DateTime $ownedUntil): void
    {
        $this->ownedUntil = $ownedUntil;
    }


}