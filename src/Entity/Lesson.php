<?php

namespace App\Entity;

use App\Repository\LessonRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=LessonRepository::class)
 */
class Lesson
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="Course", inversedBy="lessons")
     * @ORM\JoinColumn(name="course", referencedColumnName="id", nullable=false)
     */
    private $course;

    /**
     * @ORM\Column(type="string", length=255)
     *
     * @var string | null
     */
    private $name;

    /**
     * @ORM\Column(type="text")
     *
     * @var string | null
     */
    private $content;

    /**
     * @ORM\Column(type="decimal", nullable=true)
     * @Assert\LessThanOrEqual(10000)
     */
    private $indexNumber;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getCourse()
    {
        return $this->course;
    }

    /**
     * @param mixed $course
     */
    public function setCourse($course): void
    {
        $this->course = $course;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string|null $name
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     */
    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    /**
     * @return mixed
     */
    public function getIndexNumber()
    {
        return $this->indexNumber;
    }

    /**
     * @param mixed $indexNumber
     */
    public function setIndexNumber($indexNumber): void
    {
        $this->indexNumber = $indexNumber;
    }
}
