<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;

class CourseControllerTest extends AbstractTest
{
    private $incorrectControlsData;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->incorrectControlsData = [
            [
                'code' => '',
                'name' => 'Name',
                'description' => 'Description',
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Code can not be empty']],
            ],
            [
                'code' => bin2hex(random_bytes(501)),
                'name' => 'Name',
                'description' => 'Description',
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Maximum code length is 255 symbols']],
            ],
            [
                'code' => bin2hex(random_bytes(100)),
                'name' => '',
                'description' => 'Description',
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Name can not be empty']],
            ],
            [
                'code' => bin2hex(random_bytes(100)),
                'name' => bin2hex(random_bytes(128)),
                'description' => 'Description',
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Maximum name length is 255 symbols']],
            ],
            [
                'code' => bin2hex(random_bytes(100)),
                'name' => 'Name',
                'description' => bin2hex(random_bytes(501)),
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Maximum description length is 1000 symbols']],
            ],
        ];
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    public function testCourseAvailabilityByDirectLink(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            $courseId = $course->getId();
            $client->request('get', "/courses/$courseId");
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testCourseAvailabilityFromListPage(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');

        $crawler->filter('a.card-link')->each(static function (Crawler $cardLink) use ($client) {
            $cardLink = $cardLink->link();
            $client->click($cardLink);
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        });
    }

    public function testListPageAvailabilityFromCoursePage(): void
    {
        $client = self::getClient();
        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();
        foreach ($courses as $course) {
            $courseId = $course->getId();
            $crawler = $client->request('get', "/courses/$courseId");
            $backLink = $crawler->filter('.btn')->eq(0)->link();
            $client->click($backLink);
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testCourseAddPageAvailabilityFromListPage(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');
        $addPageLink = $crawler->filter('.btn')->eq(0)->link();
        $client->click($addPageLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testNotExistingPage(): void
    {
        $maxCourse = self::getEntityManager()->createQueryBuilder()
            ->select('MAX(c.id)')
            ->from(Course::class, 'c')
            ->getQuery()
            ->getSingleScalarResult();
        $notExistCourse = $maxCourse + 1;
        $client = self::getClient();
        $client->request('get', "/courses/$notExistCourse");

        // проверка статуса ответа 404 при обращении к несуществующему курсу
        self::assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testShowCoursesList(): void
    {
        $client = self::getClient();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();

        $crawler = $client->request('get', '/courses/');
        $courseCards = $crawler->filter('div .card');

        // проверка соответствия количества отображаемых курсов с количеством курсов в БД
        self::assertCount($courseCards->count(), $courses);

        foreach ($courses as $course) {
            $courseCode = $course->getCode();
            $currentCourseCard = $crawler->filter("div .card[data-code = '$courseCode']");
            // проверка наличия курса в списке
            self::assertEquals(1, $currentCourseCard->count());

            $cardLink = $currentCourseCard->filter('a');
            // проверка наличия ссылки
            self::assertEquals(1, $cardLink->count());

            $cardHref = $cardLink->eq(0)->attr('href');
            $courseId = $course->getId();
            // проверка корректности ссылки на курс
            self::assertEquals("/courses/$courseId", $cardHref);

            $courseCardTitle = $currentCourseCard->filter('h5');
            self::assertEquals(1, $courseCardTitle->count());
            // проверка корректности заголовка курса в списке
            self::assertEquals($course->getName(), $courseCardTitle->eq(0)->text());

            $courseDescriptionText = $currentCourseCard->filter('p');
            self::assertEquals(1, $courseDescriptionText->count());
            // проверка корректности описания курса в списке
            self::assertEquals($course->getDescription(), $courseDescriptionText->eq(0)->text());
        }
    }

    public function testAddCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/new');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $addForm = $crawler->filter('form')->eq(0)->form();
        $testCourseCode = '1234';
        $testCourseName = 'Test course';
        $testCourseDescription = 'Test description';

        $addForm['course[code]'] = $testCourseCode;
        $addForm['course[name]'] = $testCourseName;
        $addForm['course[description]'] = $testCourseDescription;
        $client->submit($addForm);

        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        $crawler = $client->followRedirect();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course $addedCourse */
        $addedCourse = $em->getRepository(Course::class)->findOneBy([
            'code' => $testCourseCode,
        ]);

        // проверка добавления курса в БД
        self::assertNotNull($addedCourse);

        $addedCourseId = $addedCourse->getId();
        self::assertEquals("/courses/$addedCourseId", $client->getRequest()->getUri());
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // проверка заполнения полей курса
        self::assertEquals($testCourseDescription, $addedCourse->getDescription());
        self::assertEquals($testCourseName, $addedCourse->getName());

        $crawler = $client->request('get', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $coursesCount = $crawler->filter(".card-title[data-code = '$testCourseCode']")->count();
        // проверка отображения добавленного курса на странице списка курсов
        self::assertEquals(1, $coursesCount);
    }

    public function testAddIncorrectCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('course_index'));
        $addLink = $crawler->filter('.btn')->first()->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $existingCourse = [
            'code' => self::getEntityManager()->createQueryBuilder()
                ->select('c.code')
                ->from(Course::class, 'c')
                ->orderBy('c.code DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult()
                ->getCode(),
            'name' => 'Name',
            'description' => 'Description',
            'messages' => [['element' => '', 'text' => 'This course is already exists']],
        ];

        $incorrectCourseData = $this->incorrectControlsData;
        $incorrectCourseData[] = $existingCourse;

        foreach ($incorrectCourseData as $data) {
            // выбор формы добавления
            $addForm = $crawler->filter('form')->first()->form();
            $addForm['course[name]'] = $data['name'];
            $addForm['course[code]'] = $data['code'];
            $addForm['course[description]'] = $data['description'];
            $client->submit($addForm);

            self::assertEquals(302, $client->getResponse()->getStatusCode());
            $crawler = $client->followRedirect();

            self::assertEquals(
                $client->getContainer()->get('router')->generate('course_new', [], UrlGenerator::ABSOLUTE_URL),
                $client->getRequest()->getUri()
            );
        }
    }

    public function testCancelAddCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // количество курсов до перехода на страницу добавления
        $previousCoursesCount = $crawler->filter('.card')->count();

        $addLink = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $link = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($link);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // проверка соответствия количества курсов
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

            /** @var Lesson[] $courseLesson */
            $courseLessons = $em->getRepository(Lesson::class)->findBy([
                'course' => $course,
            ], [
                'indexNumber' => 'ASC'
            ]);

            $courseId = $course->getId();
            $crawler = $client->request('get', "/courses/$courseId");
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $displayedCourseName = $crawler->filter('p')->eq(0)->text();
            // проверка отображения имени курса
            self::assertEquals($course->getName(), $displayedCourseName);

            $displayedCourseDescription = $crawler->filter('p')->eq(1)->text();
            // проверка отображения описания курса
            self::assertEquals($displayedCourseDescription, $course->getDescription());

            $displayedLessonsCount = $crawler->filter('td a')->count();
            self::assertCount($displayedLessonsCount, $courseLessons);

            $crawler->filter('td a')->each(static function (Crawler $node, $i) use ($courseLessons) {
                // проверка отображения текста уроков
                self::assertEquals($node->text(), $courseLessons[$i]->getName());

                //проверка ссылки на урок
                $lessonId = $courseLessons[$i]->getId();
                self::assertEquals("/lessons/$lessonId", $node->attr('href'));
            });

            $backLink = $crawler->filter('a.btn')->first()->link();
            $crawler = $client->click($backLink);

            // проверка редиректа на список курсов
            self::assertEquals('/courses/', $client->getRequest()->getUri());
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testEditCourse(): void
    {
        $client = self::getClient();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        /** @var Course[] $courses */
        $courses = $em->getRepository(Course::class)->findAll();

        foreach ($courses as $course) {
            $courseId = $course->getId();

            $crawler = $client->request('get', "/courses/$courseId/edit");
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $form = $crawler->filter('form')->first()->form();

            // проверка корректности отображения существующего курса в формах
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

            // проверка редиректа
            self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
            $crawler = $client->followRedirect();

            // проверка url редиректа на страницу просмотра курса
            self::assertEquals("/courses/$courseId", $client->getRequest()->getUri());
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $em->refresh($course);

            // проверка изменения значения полей в сущности после обновления
            self::assertEquals($testCourseName, $course->getName());
            self::assertEquals($testCourseCode, $course->getCode());
            self::assertEquals($testCourseDescription, $course->getDescription());
        }
    }

    public function testIncorrectEditCourse(): void
    {
        $client = self::getClient();
        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('course_edit'));
        $addLink = $crawler->filter('.btn')->first()->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        foreach ($this->incorrectControlsData as $data) {
            // выбор формы добавления
            $addForm = $crawler->filter('form')->first()->form();
            $addForm['course[name]'] = $data['name'];
            $addForm['course[code]'] = $data['code'];
            $addForm['course[description]'] = $data['description'];
            $client->submit($addForm);

            self::assertEquals(302, $client->getResponse()->getStatusCode());
            $crawler = $client->followRedirect();

            self::assertEquals(
                $client->getContainer()->get('router')->generate('course_edit', [], UrlGenerator::ABSOLUTE_URL),
                $client->getRequest()->getUri()
            );
        }
    }

    public function testDeleteCourse(): void
    {
        $client = self::getClient();

        /** @var EntityManagerInterface $em */
        $em = self::getEntityManager();

        $courses = $em->createQueryBuilder()
            ->select('c.id')
            ->from(Course::class, 'c')
            ->getQuery()
            ->getScalarResult();

        $crawler = $client->request('get', '/courses/');
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $courseCount = $crawler->filter('.card')->count();

        foreach ($courses as $course) {
            $courseId = $course['id'];
            $crawler = $client->request('get', "/courses/$courseId");

            $deleteButton = $crawler->filter('button.btn-danger')->form();
            $client->submit($deleteButton);
            self::assertInstanceOf(RedirectResponse::class, $client->getResponse(), "No deleted: $courseId");
            $client->followRedirect();

            // проверка url после редиректа
            self::assertEquals('/courses/', $client->getRequest()->getUri());
            self::assertEquals(200, $client->getResponse()->getStatusCode());
            $currentCourseCount = $crawler->filter('.card')->count();

            // проверка уменьшения количества курсов в списке
            self::assertEquals($courseCount - 1, $currentCourseCount);

            // проверка удаления курса в БД
            $deletedCourse = $em->getRepository(Course::class)->find($courseId);
            self::assertNull($deletedCourse);
        }
    }
}
