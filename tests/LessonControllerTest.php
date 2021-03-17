<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;

class LessonControllerTest extends AbstractTest
{
    private $incorrectLessonData;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->incorrectLessonData = [
            [
                'name' => '',
                'indexNumber' => '101',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Name can not be blank']]
            ],
            [
                'name' => bin2hex(random_bytes(1000)),
                'indexNumber' => '101',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Name max length is 255 symbols']]
            ],
            [
                'name' => bin2hex(random_bytes(100)),
                'indexNumber' => 'qwerty',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Index value must be numeric']]
            ],
            [
                'name' => '',
                'indexNumber' => '101',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => '']]
            ],
        ];
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testLessonDisplayInfo(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $coursesCount = $crawler->filter('a.card-link')->count();
        for ($i = 0; $i !== $coursesCount; ++$i) {
            $courseLink = $crawler->filter('a.card-link')->eq($i)->link();
            $crawler = $client->click($courseLink);
            self::assertEquals(
                200,
                $client->getResponse()->getStatusCode(),
                'Course main page - wrong http response code'
            );

            /** @var EntityManagerInterface $em */
            $em = self::getEntityManager();
            $courseTitle = $crawler->filter('h1')->eq(0)->text();
            $startNamePos = strpos($courseTitle, '"') + 1;
            $endNamePos = strrpos($courseTitle, '"');
            $courseTitle = substr($courseTitle, $startNamePos, $endNamePos - $startNamePos);
            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy(['name' => $courseTitle]);
            self::assertNotNull($course, 'Course was not found by page title');

            $lessonsCount = $crawler->filter('table tbody a')->count();
            /** @var Lesson[] $lessons */
            $lessons = $em->getRepository(Lesson::class)->findBy(
                ['course' => $course->getId()],
                ['indexNumber' => 'ASC']
            );
            self::assertCount($lessonsCount, $lessons, 'Some lessons was not displayed');

            for ($j = 0; $j !== $lessonsCount; ++$j) {
                $lessonLink = $crawler->filter('table tbody a')->eq($j)->link();
                $crawler = $client->click($lessonLink);
                self::assertEquals(
                    200,
                    $client->getResponse()->getStatusCode(),
                    'Lesson page - wrong http response code'
                );

                $lessonContent = $crawler->filter('p')->eq(0)->text();
                self::assertEquals(
                    $lessonContent,
                    $lessons[$j]->getContent(),
                    'Lesson content displayed incorrectly'
                );

                $lessonTitle = $crawler->filter('h1')->eq(0)->text();
                self::assertEquals($lessonTitle, $lessons[$j]->getName(), 'Lesson title displayed incorrectly');

                $crawler = $client->request('GET', '/courses/' . $course->getId());
                self::assertEquals(200, $client->getResponse()->getStatusCode());
            }

            $crawler = $client->request('GET', '/courses/');
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testCreateNewLesson(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        self::assertEquals(
            200,
            $client->getResponse()->getStatusCode(),
            'Courses list page - wrong http response code'
        );

        $coursesCount = $crawler->filter('div.card')->count();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();
        self::assertCount(
            $coursesCount,
            $em->getRepository(Course::class)->findAll(),
            'Wrong courses count displayed'
        );

        for ($i = 0; $i !== $coursesCount; ++$i) {
            $link = $crawler->filter('a.card-link')->eq($i)->link();
            $crawler = $client->click($link);
            self::assertEquals(
                200,
                $client->getResponse()->getStatusCode(),
                'Course full page - wrong http response code'
            );

            $previousLessonsCount = $crawler->filter('table tbody tr')->count();

            $link = $crawler->filter('a#add-lesson')->link();
            $crawler = $client->click($link);
            self::assertEquals(
                200,
                $client->getResponse()->getStatusCode(),
                'Add lesson page - wrong http response code'
            );

            $form = $crawler->filter('form')->eq(0)->form();
            $form['lesson[name]'] = 'TestLesson';
            $form['lesson[content]'] = 'This is content of lesson';
            $form['lesson[indexNumber]'] = '101';
            $client->submit($form);

            self::assertInstanceOf(
                RedirectResponse::class,
                $client->getResponse(),
                'Redirect error'
            );
            $crawler = $client->followRedirect();
            self::assertEquals(
                $previousLessonsCount + 1,
                $crawler->filter('table tbody tr')->count(),
                'Added lesson not displayed'
            );

            $crawler = $client->request('GET', '/courses/');
        }
    }

    public function testEditLesson(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $cardLink = $crawler->filter('a.card-link')->eq(0)->link();
        $crawler = $client->click($cardLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $lessonLink = $crawler->filter('table tbody tr a')->eq(0)->link();
        $crawler = $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $oldLessonName = $crawler->filter('h1')->eq(0)->text();
        $oldLessonContent = $crawler->filter('p')->eq(0)->text();

        $editLink = $crawler->filter('a.btn')->link();
        $crawler = $client->click($editLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $editLessonForm = $crawler->filter('form')->eq(0)->form();
        self::assertEquals($oldLessonName, $editLessonForm['lesson[name]']->getValue());
        self::assertEquals($oldLessonContent, $editLessonForm['lesson[content]']->getValue());

        $newLessonName = 'test name';
        $newLessonContent = 'Test lesson content';
        $editLessonForm['lesson[name]']->setValue($newLessonName);
        $editLessonForm['lesson[indexNumber]']->setValue((string)random_int(1, 1000));
        $editLessonForm['lesson[content]']->setValue($newLessonContent);
        $client->submit($editLessonForm);

        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        $crawler = $client->followRedirect();

        self::assertEquals($newLessonName, $crawler->filter('h1')->eq(0)->text());
        self::assertEquals($newLessonContent, $crawler->filter('p')->eq(0)->text());
    }

    public function testRemoveLesson(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $cardLink = $crawler->filter('a.card-link')->eq(0)->link();
        $crawler = $client->click($cardLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $lessonLink = $crawler->filter('table tbody tr a')->eq(0)->link();
        $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $deleteLink = $crawler->filter('form .btn')->eq(0)->link();
        $client->click($deleteLink);

        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        $client->followRedirect();
    }

    public function testEditIncorrectLesson(): void
    {
        $client = self::getClient();
        $crawler = $client->request('GET', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $cardLink = $crawler->filter('a.card-link')->eq(0)->link();
        $crawler = $client->click($cardLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $lessonLink = $crawler->filter('table tbody tr a')->eq(0)->link();
        $crawler = $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $oldLessonName = $crawler->filter('h1')->eq(0)->text();
        $oldLessonContent = $crawler->filter('p')->eq(0)->text();

        $editLink = $crawler->filter('a.btn')->link();
        $crawler = $client->click($editLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $editLessonForm = $crawler->filter('form')->eq(0)->form();

        foreach ($this->incorrectLessonData as $incorrectLesson) {
            $editLessonForm['lesson[name]']->setValue($incorrectLesson['name']);
            $editLessonForm['lesson[indexNumber]']->setValue($incorrectLesson['indexNumber']);
            $editLessonForm['lesson[content]']->setValue($incorrectLesson['content']);
            $client->submit($editLessonForm);

            self::assertEquals(302, $client->getResponse()->getStatusCode());
            $crawler = $client->followRedirect();

            foreach ($incorrectLesson['message'] as $errorMessage) {
                $errorLabel = $crawler->filter($errorMessage['element'])->eq($errorMessage['index']);
                self::assertNotNull($errorLabel);
                self::assertEquals($errorMessage['text'], $errorLabel->text());
            }
        }
    }
}
