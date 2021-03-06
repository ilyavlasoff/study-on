<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $courseNames = ['Математическое программирование', 'Теория принятия решений',
            'Теория автоматизированного управления', 'Базы данных', 'Тестирование ПО'];
        $lessonCountMin = 3;
        $lessonCountMax = 5;

        foreach ($courseNames as $courseName) {
            $course = new Course();
            $course->setName($courseName);
            $course->setDescription('Этот курс содержит лекции и семинары по дисциплине ' . $courseName);
            $course->setCode(random_int(1, 100));
            $manager->persist($course);

            $lessonCount = random_int($lessonCountMin, $lessonCountMax);
            for ($i=0; $i!==$lessonCount; ++$i) {
                $lesson = new Lesson();
                $lesson->setName('Урок №' . $i . ' по дисциплине ' . $courseName);
                $lesson->setContent('Это контент урока №' . $i . ' по курсу ' . $courseName);
                $lesson->setIndexNumber($i);
                $lesson->setCourse($course);
                $manager->persist($lesson);
            }
        }

        $manager->flush();
    }
}
