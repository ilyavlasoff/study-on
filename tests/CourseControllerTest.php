<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CourseControllerTest extends AbstractTest
{
    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testCourseAvailabilityByDirectLink(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            $client->request('get', '/courses/' . $course->getId());
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testAddCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $previousCoursesCount = $crawler->filter('.card-title')->count();

        $addLink = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $addForm = $crawler->filter('form')->eq(0)->form();
        $testCourseName = 'Test course';
        $testCourseDescription = 'Test description';
        $addForm['course[code]'] = '1234';
        $addForm['course[name]'] = $testCourseName;
        $addForm['course[description]'] = $testCourseDescription;
        $client->submit($addForm);

        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        $crawler = $client->followRedirect();
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $coursesCount = $crawler->filter('.card-title')->count();
        self::assertEquals($previousCoursesCount + 1, $coursesCount);
        self::assertStringContainsString($testCourseName, $crawler->filter('.card-title')->last()->text());
        self::assertStringContainsString($testCourseDescription, $crawler->filter('p.card-text')->last()->text());
    }

    public function testCancelAddCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $previousCoursesCount = $crawler->filter('.card')->count();

        $addLink = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $link = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($link);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        self::assertEquals($previousCoursesCount, $crawler->filter('.card')->count());
    }

    public function testShowCourse(): void
    {
        $client = self::getClient();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            /** @var Lesson $courseLesson */
            $courseLesson = $em->getRepository(Lesson::class)->findOneBy([
                'course' => $course,
            ]);

            $crawler = $client->request('get', '/lessons/' . $courseLesson->getId());
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $courseLink = $crawler->filter('a.text-dark')->first();
            self::assertEquals($courseLink->text(), $course->getName());
            $crawler = $client->click($courseLink->link());
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $displayedCourseName = $crawler->filter('p')->eq(0)->text();
            self::assertEquals($displayedCourseName, $course->getName());

            $displayedCourseDescription = $crawler->filter('p')->eq(1)->text();
            self::assertEquals($displayedCourseDescription, $course->getDescription());
            $lessons = $course->getLessons()->toArray();

            usort($lessons, static function (Lesson $f, Lesson $s) {
                return $f->getIndexNumber() > $s->getIndexNumber();
            });

            $crawler->filter('td a')->each(static function (Crawler $node, $i) use ($lessons) {
                self::assertLessThan(count($lessons), $i);
                self::assertEquals($node->text(), $lessons[$i]->getName());
            });

            $backLink = $crawler->filter('a.btn')->first()->link();
            $crawler = $client->click($backLink);
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testEditCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        $courseCount = $crawler->filter('.card')->count();
        $courses = $em->getRepository(Course::class)->findAll();
        self::assertCount($courseCount, $courses);

        for ($i = 0; $i !== $courseCount; ++$i) {
            $courseName = $crawler->filter('.card h5.card-title')->eq($i)->text();
            $courseLink = $crawler->filter('.card a.card-link')->eq($i)->link();

            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy([
                'name' => $courseName,
            ]);
            self::assertNotNull($course, 'Course was not found');
            $crawler = $client->click($courseLink);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $editLink = $crawler->filter('.btn')->eq(1)->link();
            $crawler = $client->click($editLink);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $form = $crawler->filter('form')->first()->form();
            self::assertEquals($course->getCode(), $form['course[code]']->getValue());
            self::assertEquals($course->getName(), $form['course[name]']->getValue());
            self::assertEquals($course->getDescription(), $form['course[description]']->getValue());
            $testCourseName = 'test course';
            $testCourseCode = $course->getCode() . 'test';
            $testCourseDescription = 'test course description';
            $form['course[code]']->setValue($testCourseCode);
            $form['course[name]']->setValue($testCourseName);
            $form['course[description]']->setValue($testCourseDescription);
            $client->submit($form);
            self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
            $crawler = $client->followRedirect();
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $updatedCourseName = $crawler->filter('h5.card-title')->eq($i)->text();
            $updatedCourseDescription = $crawler->filter('p.card-text')->eq($i)->text();
            self::assertEquals($testCourseName, $updatedCourseName);
            self::assertEquals($testCourseDescription, $updatedCourseDescription);
        }
    }

    public function testDeleteCourse(): void
    {
        $client = self::getClient();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            $client->request('get', '/courses/' . $course->getId());
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }

        // ?
    }
}
