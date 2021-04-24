<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TestFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $coursesProperties = [
            [
                'code' => 'c1',
                'title' => 'course1',
            ],
            [
                'code' => 'c2',
                'title' => 'course2',
            ],
            [
                'code' => 'c3',
                'title' => 'course3',
            ],
            [
                'code' => 'c4',
                'title' => 'course4',
            ],
            [
                'code' => 'c5',
                'title' => 'course5',
            ],
        ];

        foreach ($coursesProperties as $coursesProperty) {
            $course = new Course();
            $course->setCode($coursesProperty['code']);
            $course->setName($coursesProperty['title']);
            $course->setDescription("Test description for course {$coursesProperty['title']}");
            $manager->persist($course);

            $lessonCount = random_int(1, 20);
            for($i = 0; $i !== $lessonCount; ++$i) {
                $lesson = new Lesson();
                $lesson->setName("Lesson $i for course {$course->getName()}");
                $lesson->setContent("Test lesson content: lesson $i, course {$course->getName()}");
                $lesson->setCourse($course);
                $lesson->setIndexNumber($i);
                $manager->persist($lesson);
            }
        }

        $manager->flush();
    }
}