<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class LessonControllerTest extends AbstractTest
{
    private $urlBase = '/lessons/';

    private function getUrl(array $urlPart)
    {
        return $this->urlBase . implode('/', $urlPart);
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testLessonsHttpStatus(): void
    {
        self::getClient()->request('GET', $this->getUrl([]));
        self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());
    }

    public function testRedirectMainToCourses(): void
    {
        self::getClient()->request('GET', '/');
        self::assertInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
        self::assertEquals('/courses/', self::getClient()->getResponse()->headers->get('location'));
    }

    public function testCreateNewLesson(): void
    {
        $crawler = self::getClient()->request('GET', '/courses/');
        self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());
        // Count of courses available on /courses page
        $coursesCount = $crawler->filterXPath('//body[1]/table[1]/tbody[1]/tr')->count();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        self::assertCount($coursesCount, $em->getRepository(Course::class)->findAll());

        // Follow all courses links to create lesson in each of them
        for ($i = 0; $i !== $coursesCount; ++$i) {
            $link = $crawler->filterXPath('//body[1]/table[1]/tbody[1]/tr[' . ($i + 1) .']/td[1]/a')->link();
            $crawler = self::getClient()->click($link);
            // To lessons list page
            self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());

            //Save previous count of lessons in course
            $lessonsCount = $crawler->filterXPath('//body[1]/ol[1]/li')->count();

            $link = $crawler->filterXPath('//body[1]/a[3]')->link();
            $crawler = self::getClient()->click($link);
            // To create new lesson page
            self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());

            $form = $crawler->filterXPath('//body[1]/form[1]')->form();
            $form['lesson[name]'] = 'TestLesson';
            $form['lesson[content]'] = 'This is content of lesson';
            $form['lesson[indexNumber]'] = '101';
            $crawler = self::getClient()->submit($form);

            // Expected redirect to course page
            self::assertInstanceOf(RedirectResponse::class, self::getClient()->getResponse());
            $crawler = self::getClient()->followRedirect();
            self::assertEquals($lessonsCount + 1, $crawler->filterXPath('//body[1]/ol[1]/li')->count());

            $crawler = self::getClient()->request('GET', '/courses/');
        }
    }

    public function testGetShowLessonPage(): void
    {
        foreach ($this->lessons as $lesson) {
            self::getClient()->request('GET', $this->getUrl([(string)$lesson->getId()]));
            self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());
        }
    }

    public function testGetEditLessonPage(): void
    {
        foreach ($this->lessons as $lesson) {
            self::getClient()->request('GET', $this->getUrl([(string)$lesson->getId(), 'edit']));
            self::assertEquals(200, self::getClient()->getResponse()->getStatusCode());
        }
    }
}
