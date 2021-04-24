<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use App\Model\Response\CourseDto;
use App\Service\CoursesQueryClient;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    private $coursesQueryClient;

    public function __construct(CoursesQueryClient $coursesQueryClient)
    {
        $this->coursesQueryClient = $coursesQueryClient;
    }

    public function load(ObjectManager $manager): void
    {

        /** @var CourseDto[] $courses */
        $courses = $this->coursesQueryClient->getCoursesList();

        $lessonCountMin = 3;
        $lessonCountMax = 5;

        foreach ($courses as $loadedCourse) {
            $course = new Course();
            $course->setName($loadedCourse->getTitle());
            $course->setDescription('Этот курс содержит лекции и семинары по дисциплине ' . $loadedCourse->getTitle());
            $course->setCode($loadedCourse->getCode());
            $manager->persist($course);

            $lessonCount = random_int($lessonCountMin, $lessonCountMax);
            for ($i = 0; $i !== $lessonCount; ++$i) {
                $lesson = new Lesson();
                $lesson->setName('Урок №' . $i . ' по дисциплине ' . $loadedCourse->getTitle());
                $lesson->setContent('Это контент урока №' . $i . ' по курсу ' . $loadedCourse->getTitle());
                $lesson->setIndexNumber($i);
                $lesson->setCourse($course);
                $manager->persist($lesson);
            }
        }

        $manager->flush();
    }
}
