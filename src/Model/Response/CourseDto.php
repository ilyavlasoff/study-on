<?php

namespace App\Model\Response;

use JMS\Serializer\Annotation as JMS;

class CourseDto
{
    /**
     * @var string
     * @JMS\Type("string")
     * @JMS\Groups({"anon", "owned"})
     */
    private $code;

    /**
     * @var string
     * @JMS\Type("string")
     * @JMS\Groups({"anon", "owned"})
     */
    private $type;

    /**
     * @var string
     * @JMS\Type("string")
     * @JMS\Groups({"anon", "owned"})
     */
    private $title;

    /**
     * @var float | null
     * @JMS\Type("float")
     * @JMS\Groups({"anon", "owned"})
     */
    private $price;

    /**
     * @var bool | null
     * @JMS\Type("bool")
     * @JMS\Groups({"owned"})
     */
    private $owned;

    /**
     * @var \DateTime | null
     * @JMS\Type("DateTime<'Y-m-d\TH:i:sP'>")
     * @JMS\Groups({"owned"})
     */
    private $ownedUntil;

    /**
     * @var \DateInterval | null
     * @JMS\Type("DateInterval")
     * @JMS\Groups({"anon", "owned"})
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
     * @return float|null
     */
    public function getPrice(): ?float
    {
        return $this->price;
    }

    /**
     * @param float|null $price
     */
    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    /**
     * @return bool|null
     */
    public function getOwned(): ?bool
    {
        return $this->owned;
    }

    /**
     * @param bool|null $owned
     */
    public function setOwned(?bool $owned): void
    {
        $this->owned = $owned;
    }

    /**
     * @return \DateTime|null
     */
    public function getOwnedUntil(): ?\DateTime
    {
        return $this->ownedUntil;
    }

    /**
     * @param \DateTime|null $ownedUntil
     */
    public function setOwnedUntil(?\DateTime $ownedUntil): void
    {
        $this->ownedUntil = $ownedUntil;
    }

    /**
     * @return \DateInterval|null
     */
    public function getRentTime(): ?\DateInterval
    {
        return $this->rentTime;
    }

    /**
     * @param \DateInterval|null $rentTime
     */
    public function setRentTime(?\DateInterval $rentTime): void
    {
        $this->rentTime = $rentTime;
    }
}
