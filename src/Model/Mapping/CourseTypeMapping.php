<?php

namespace App\Model\Mapping;

use App\Entity\Course;
use App\Model\Response\CourseDto;

class CourseTypeMapping
{
    private $code;

    private $name;

    private $type;

    private $price;

    private $rentTime;

    private $description;

    public static function fromExistingCourseData(CourseDto $billingCourse, Course $course): self
    {
        $courseMapping = new self();
        $courseMapping->setName($course->getName());
        $courseMapping->setDescription($course->getDescription());
        $courseMapping->setCode($course->getCode());
        $courseMapping->setType($billingCourse->getType());
        $courseMapping->setPrice($billingCourse->getPrice());
        $courseMapping->setRentTime($billingCourse->getRentTime());

        return $courseMapping;
    }

    public function getBillingCourse(): CourseDto
    {
        $billingCourse = new CourseDto();
        $billingCourse->setCode($this->getCode());
        $billingCourse->setType($this->getType());
        $billingCourse->setTitle($this->getName());

        if ('rent' === $this->type || 'buy' === $this->type) {
            $billingCourse->setPrice($this->getPrice());
        }
        if ('rent' === $this->type) {
            $billingCourse->setRentTime($this->getRentTime());
        }

        return $billingCourse;
    }

    public function updateCourseContent(Course $updatedCourse): Course
    {
        $updatedCourse->setCode($this->getCode());
        $updatedCourse->setDescription($this->getDescription());
        $updatedCourse->setName($this->getName());

        return $updatedCourse;
    }

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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
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
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param mixed $price
     */
    public function setPrice($price): void
    {
        $this->price = $price;
    }

    /**
     * @return mixed
     */
    public function getRentTime()
    {
        return $this->rentTime;
    }

    /**
     * @param mixed $rentTime
     */
    public function setRentTime($rentTime): void
    {
        $this->rentTime = $rentTime;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): void
    {
        $this->description = $description;
    }

}
