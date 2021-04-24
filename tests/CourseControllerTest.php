<?php

namespace App\Tests;

use App\DataFixtures\TestFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Model\Response\CourseDto;
use App\Security\User;
use App\Service\AuthenticationClient;
use App\Service\CoursesQueryClient;
use App\Service\PaymentQueryClient;
use App\Service\PersonalQueryClient;
use App\Tests\Mocks\AuthenticationClientMock;
use App\Tests\Mocks\CoursesQueryClientMock;
use App\Tests\Mocks\DataMock;
use App\Tests\Mocks\PaymentQueryClientMock;
use App\Tests\Mocks\PersonalQueryClientMock;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class CourseControllerTest extends AbstractTest
{
    /**
     * @var array[]
     */
    private $correctControlsData;

    /**
     * @var array[]
     */
    private $incorrectControlsData;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = self::$container->get('jms_serializer');
        $this->tokenStorage = self::$container->get('security.token_storage');
        $this->entityManager = self::$container->get('doctrine.orm.entity_manager');
    }

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->correctControlsData = [
            [
                'course[code]' => 'ca1',
                'course[name]' => 'Added1',
                'course[description]' => '1description1',
                'course[type]' => 'buy',
                'course[price]' => '312',
            ],
            [
                'course[code]' => 'ca2',
                'course[name]' => 'Added2',
                'course[description]' => '2description2',
                'course[type]' => 'rent',
                'course[price]' => '123',
                'course[rentTime][months]' => '2',
                'course[rentTime][days]' => '3',
                'course[rentTime][hours]' => '4',
            ],
            [
                'course[code]' => 'ca3',
                'course[name]' => 'Added3',
                'course[description]' => '3description3',
                'course[type]' => 'free',
            ],
        ];

        $this->incorrectControlsData = [
            [
                // некорректный код курса - запрещена пустая строка
                'data' => [
                    'course[code]' => ' ',
                    'course[name]' => 'Name',
                    'course[description]' => 'Description',
                    'course[type]' => 'rent',
                    'course[price]' => '123',
                    'course[rentTime][months]' => '2',
                    'course[rentTime][days]' => '3',
                    'course[rentTime][hours]' => '4',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Code can not be empty']],
            ],
            [
                // некорректный код курса - запрещено более 255 символов
                'data' => [
                    'course[code]' => bin2hex(random_bytes(501)),
                    'course[name]' => 'Name',
                    'course[description]' => 'Description',
                    'course[type]' => 'rent',
                    'course[price]' => '123',
                    'course[rentTime][months]' => '2',
                    'course[rentTime][days]' => '3',
                    'course[rentTime][hours]' => '4',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Maximum code length is 255 symbols']],
            ],
            [
                // некорректное имя курса - запрещена пустая строка
                'data' => [
                    'course[code]' => bin2hex(random_bytes(10)),
                    'course[name]' => ' ',
                    'course[description]' => 'Description',
                    'course[type]' => 'rent',
                    'course[price]' => '123',
                    'course[rentTime][months]' => '2',
                    'course[rentTime][days]' => '3',
                    'course[rentTime][hours]' => '4',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Name can not be empty']],
            ],
            [
                // некорректное имя курса - запрещено более 255 символов
                'data' => [
                    'course[code]' => bin2hex(random_bytes(10)),
                    'course[name]' => bin2hex(random_bytes(128)),
                    'course[description]' => 'Description',
                    'course[type]' => 'rent',
                    'course[price]' => '123',
                    'course[rentTime][months]' => '2',
                    'course[rentTime][days]' => '3',
                    'course[rentTime][hours]' => '4',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Maximum name length is 255 symbols']],
            ],
            [
                // некорректное описание - запрещено более 255 символов
                'data' => [
                    'course[code]' => bin2hex(random_bytes(10)),
                    'course[name]' => bin2hex(random_bytes(10)),
                    'course[description]' => bin2hex(random_bytes(501)),
                    'course[type]' => 'rent',
                    'course[price]' => '123',
                    'course[rentTime][months]' => '2',
                    'course[rentTime][days]' => '3',
                    'course[rentTime][hours]' => '4',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Maximum description length is 1000 symbols']],
            ],
            [
                // некорректное значение цены
                'data' => [
                    'course[code]' => 'code',
                    'course[name]' => 'name',
                    'course[description]' => 'description',
                    'course[type]' => 'buy',
                    'course[price]' => 'qwerty',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Expected type numeric, got "qwerty"']],
            ],
            [
                // некорректное значение цены
                'data' => [
                    'course[code]' => 'code',
                    'course[name]' => 'name',
                    'course[description]' => 'description',
                    'course[type]' => 'rent',
                    'course[price]' => '220',
                    'course[rentTime][months]' => '2',
                    'course[rentTime][days]' => '',
                    'course[rentTime][hours]' => '4',
                ],
                'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'This value is not valid.']],
            ],
        ];
    }

    private function setUpMock()
    {
        $client = self::getClient();

        $client->disableReboot();

        $dataMock = new DataMock();
        $client->getContainer()->set(AuthenticationClient::class, new AuthenticationClientMock($dataMock));
        $client->getContainer()->set(CoursesQueryClient::class, new CoursesQueryClientMock($dataMock));
        $client->getContainer()->set(PaymentQueryClient::class, new PaymentQueryClientMock($dataMock));
        $client->getContainer()->set(PersonalQueryClient::class, new PersonalQueryClientMock($dataMock, $this->entityManager));

        return $client;
    }

    private function logInAdmin(): User
    {
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setApiToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlYXQiOjE2MTkxNzE4MzksImV4cCI6MTYyMTc2MzgzOSwicm9sZXMiOlsiUk9MRV9TVVBFUl9BRE1JTiJdLCJ1c2VybmFtZSI6ImFkbWluQHRlc3QuY29tIn0.mJPYf0U9u4BjzRGIDwUNvCCJueUcftbYJ1V5pGMSJmI');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $this->logIn($admin);

        return $admin;
    }

    private function logInUser(): User
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $user->setApiToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlYXQiOjE2MTkxNzE3MzYsImV4cCI6MTYyMTc2MzczNiwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidXNlckB0ZXN0LmNvbSJ9.tGn61X1VS9cnI90NB_pTRyDFAVTqCstx4YIXAbPxSuM');
        $user->setRoles(['ROLE_USER']);
        $this->logIn($user);

        return $user;
    }

    private function logIn(User $user)
    {
        $providerKey = 'main';
        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->tokenStorage->setToken($token);
        self::$container->get('session')->set('_security_' . $providerKey, serialize($token));
    }

    protected function getFixtures(): array
    {
        return [TestFixtures::class];
    }

    public function testCourseAvailabilityByDirectLink(): void
    {
        $client = $this->setUpMock();

        $this->logInAdmin();

        $user = $this->tokenStorage->getToken()->getUsername();

        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

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
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $crawler = $client->request('get', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $crawler->filter('a.card-link')->each(static function (Crawler $cardLink) use ($client) {
            $cardLink = $cardLink->link();
            $client->click($cardLink);
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        });
    }

    public function testListPageAvailabilityFromCoursePage(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $em = self::getEntityManager();

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
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $crawler = $client->request('get', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $addPageLink = $crawler->filter('.btn')->eq(0)->link();
        $client->click($addPageLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testNotExistingPage(): void
    {
        $maxCourse = self::getEntityManager()->createQueryBuilder()
            ->select('c.id')
            ->from(Course::class, 'c')
            ->orderBy('c.id', 'DESC')
            ->getQuery()
            ->setMaxResults(1)
            ->getSingleScalarResult();
        $notExistCourse = $maxCourse + 1;

        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $client->request('get', "/courses/$notExistCourse");

        // проверка статуса ответа 404 при обращении к несуществующему курсу
        self::assertEquals(404, $client->getResponse()->getStatusCode());
    }

    public function testShowCoursesList(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $em = self::getEntityManager();

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
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        foreach ($this->correctControlsData as $courseData) {
            $crawler = $client->request('get', '/courses/new/');
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $addForm = $crawler->filter('form')->form();

            foreach ($courseData as $inputKey => $inputValue) {
                $addForm[$inputKey] = $inputValue;
            }
            $crawler = $client->submit($addForm);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $em = self::getEntityManager();

            $addedCourse = $em->getRepository(Course::class)->findOneBy([
                'code' => $courseData['course[code]'],
            ]);

            // проверка добавления курса в БД
            self::assertNotNull($addedCourse);

            $addedCourseId = $addedCourse->getId();
            self::assertEquals("/courses/$addedCourseId", $client->getRequest()->getPathInfo());
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            // проверка заполнения полей курса
            self::assertEquals($courseData['course[description]'], $addedCourse->getDescription());
            self::assertEquals($courseData['course[name]'], $addedCourse->getName());

            $crawler = $client->request('get', '/courses');
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $code = $courseData['course[code]'];
            $codeSelector = ".card[data-code=\"$code\"]";
            $coursesCount = $crawler->filter($codeSelector)->count();
            // проверка отображения добавленного курса на странице списка курсов
            self::assertEquals(1, $coursesCount);
        }
    }

    public function testAddIncorrectCourse(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $crawler = $client->request('get', '/courses');
        $addLink = $crawler->filter('.btn')->first()->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // Добавление уже существующего курса, ограничение по уникальности кода
        $existingCourse = [
            'data' => [
                'course[code]' => self::getEntityManager()->createQueryBuilder()
                    ->select('c.code')
                    ->from(Course::class, 'c')
                    ->orderBy('c.code', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getSingleScalarResult(),
                'course[name]' => 'Name',
                'course[description]' => 'Description',
                'course[type]' => 'rent',
                'course[price]' => '123',
                'course[rentTime][months]' => '2',
                'course[rentTime][days]' => '3',
                'course[rentTime][hours]' => '4',
            ],
            'messages' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'This course is already exists']],
        ];

        $incorrectCourseData = $this->incorrectControlsData;
        $incorrectCourseData[] = $existingCourse;

        foreach ($incorrectCourseData as $data) {
            // выбор формы добавления
            $addForm = $crawler->filter('form')->first()->form();
            foreach ($data['data'] as $inputKey => $inputValue) {
                $addForm[$inputKey]->setValue($inputValue);
            }
            $crawler = $client->submit($addForm);

            foreach ($data['messages'] as $errorMessage) {
                $errorText = $crawler->filter($errorMessage['element'])->eq($errorMessage['index'])->text();
                self::assertEquals($errorMessage['text'], $errorText);
            }

            self::assertEquals(
                $client->getContainer()->get('router')->generate('course_new', [], UrlGenerator::ABSOLUTE_URL),
                $client->getRequest()->getUri()
            );
        }
    }

    public function testCancelAddCourse(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $crawler = $client->request('get', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // количество курсов до перехода на страницу добавления
        $previousCoursesCount = $crawler->filter('.card')->count();

        $addLink = $crawler->filter('.btn')->first()->link();
        $crawler = $client->click($addLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $link = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($link);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // проверка соответствия количества курсов
        self::assertEquals($previousCoursesCount, $crawler->filter('.card')->count());
    }

    public function testShowCourseContents(): void
    {
        $client = $this->setUpMock();
        $user = $this->logInAdmin();
        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($username);
        self::assertEquals('admin@test.com', $username);
        $client->followRedirects(true);

        $em = self::getEntityManager();

        $courses = $em->getRepository(Course::class)->findAll();

        foreach ($courses as $course) {
            $courseLessons = $em->getRepository(Lesson::class)->findBy(
                ['course' => $course], ['indexNumber' => 'ASC']
            );

            $coursesQueryClient = self::$container->get('App\Service\CoursesQueryClient');
            /** @var CourseDto $billingCourse */
            $billingCourse = $coursesQueryClient->getCourseByCode($course, $user);

            $courseId = $course->getId();
            $crawler = $client->request('get', "/courses/$courseId/");
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $displayedCourseName = $crawler->filter('p.course-name')->eq(0)->text();
            // проверка отображения имени курса
            $courseName = $course->getName();
            self::assertEquals($courseName, $displayedCourseName);

            $displayedCourseDescription = $crawler->filter('p.course-description')->eq(0)->text();
            // проверка отображения описания курса
            self::assertEquals($displayedCourseDescription, $course->getDescription());

            if ($billingCourse->getOwned()) {
                // Получение ссылок на уроки
                $displayedLessonsCount = $crawler->filter('td a')->count();
                self::assertCount($displayedLessonsCount, $courseLessons);

                $crawler->filter('td a')->each(static function (Crawler $node, $i) use ($courseLessons) {
                    // проверка отображения текста уроков
                    self::assertEquals($node->text(), $courseLessons[$i]->getName());

                    //проверка ссылки на урок
                    $lessonId = $courseLessons[$i]->getId();
                    self::assertEquals("/lessons/$lessonId", $node->attr('href'));
                });
            }

            $backLink = $crawler->filter('.back-course-list')->eq(0)->link();
            $crawler = $client->click($backLink);

            // проверка редиректа на список курсов
            self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testCourseBoughtStatus()
    {
        $client = $this->setUpMock();
        $loggedUser = $this->logInUser();

        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('user@test.com', $user);
        $client->followRedirects(true);

        /** @var CoursesQueryClient $cqc */
        $cqc = $this->serializer = self::$container->get('App\Service\CoursesQueryClient');

        $crawler = $client->request('get', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        /** @var CourseDto[] $ownedCourses */
        $ownedCourses = $cqc->getCoursesList($loggedUser);
        $displayedCourses = $crawler->filter('.card');
        self::assertEquals(count($ownedCourses), $displayedCourses->count());

        $displayedCourses->each(function (Crawler $node, $i) use ($ownedCourses, $client) {
            $code = $node->attr('data-code');

            $foundedCourses = array_values(array_filter($ownedCourses, function ($course) use ($code) {
                return $course->getCode() == $code;
            }));

            $displayedCourse = $foundedCourses[0];

            $status = $node->filter('.own-status')->eq(0)->text();
            $link = $node->filter('a')->eq(0)->link();

            if (true === $displayedCourse->getOwned()) {
                if ($courseType = 'rent' === $displayedCourse->getType()) {
                    self::assertEquals("Арендовано до {$displayedCourse->getOwnedUntil()->format('d.m')}", $status);
                } else {
                    self::assertEquals('Приобретено', $status);
                }

                $crawler = $client->click($link);
                self::assertEquals(200, $client->getResponse()->getStatusCode());
                $boughtStatusText = $crawler->filter('.course-bought-status')->text();

                if ('rent' === $courseType) {
                    $statusRentString = date_diff($displayedCourse->getOwnedUntil(), new \DateTime())->format('%m мес. %d дн.');
                    self::assertEquals($statusRentString, $boughtStatusText);
                } elseif ('buy' === $courseType) {
                    self::assertEquals('Вы приобрели этот курс. Он будет доступен Вам всегда', $boughtStatusText);
                } elseif ('free' === $courseType) {
                    self::assertEquals('Данный курс является бесплатным', $boughtStatusText);
                }
            } else {
                if ('free' === $displayedCourse->getType()) {
                    self::assertEquals('Бесплатно', $status);
                } else {
                    self::assertEquals(round($displayedCourse->getPrice(), 2) . ' Р', $status);
                }

                $crawler = $client->click($link);
                self::assertEquals(200, $client->getResponse()->getStatusCode());
                $availabilityStatusText = $crawler->filter('.availability-status')->text();

                if ('rent' === $displayedCourse->getType()) {
                    $rentTime = $displayedCourse->getRentTime()->format('%m мес. %d дн.');
                    $price = round($displayedCourse->getPrice(), 2);
                    $statusString = "Доступна аренда курса Время аренды: $rentTime Стоимость аренды: $price";
                    self::assertEquals($statusString, $availabilityStatusText);
                } elseif ('free' === $displayedCourse->getType()) {
                    self::assertEquals('Курс доступен бесплатно', $availabilityStatusText);
                } elseif ('buy' === $displayedCourse->getType()) {
                    $price = round($displayedCourse->getPrice(), 2);
                    $statusString = "Доступна покупка курса Стоимость приобретения: $price";
                    self::assertEquals($statusString, $availabilityStatusText);
                }
            }
        });
    }

    public function testEditCourse(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $em = self::getEntityManager();
        $courses = $em->getRepository(Course::class)->findAll();

        $editCourseData = [
            [
                'course[name]' => 'Added1',
                'course[description]' => '1description1',
                'course[type]' => 'buy',
                'course[price]' => '312',
            ],
            [
                'course[name]' => 'Added3',
                'course[description]' => '3description3',
                'course[type]' => 'free',
            ],
            [
                'course[name]' => 'Added2',
                'course[description]' => '2description2',
                'course[type]' => 'rent',
                'course[price]' => '123',
                'course[rentTime][months]' => '2',
                'course[rentTime][days]' => '3',
                'course[rentTime][hours]' => '4',
            ],
        ];

        $course = $em->getRepository(Course::class)->findAll()[0];

        $courseId = $course->getId();
        $courseCode = $course->getCode();

        $crawler = $client->request('get', "/courses/$courseId/edit/");
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $form = $crawler->filter('form')->first()->form();

        // проверка корректности отображения существующего курса в формах
        self::assertEquals($course->getCode(), $form['course[code]']->getValue());
        self::assertEquals($course->getName(), $form['course[name]']->getValue());
        self::assertEquals($course->getDescription(), $form['course[description]']->getValue());

        foreach ($editCourseData as $key => $courseData) {
            $crawler = $client->request('get', "/courses/$courseId/edit");
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $addForm = $crawler->filter('form')->form();

            foreach ($courseData as $inputKey => $inputValue) {
                $addForm[$inputKey] = $inputValue;
            }

            if (0 === $key % 2) {
                $randomCode = bin2hex(random_bytes(15));
                $addForm['course[code]'] = $randomCode;
                $courseCode = $randomCode;
            }

            $crawler = $client->submit($addForm);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            // проверка url редиректа на страницу просмотра курса
            self::assertEquals("/courses/$courseId", $client->getRequest()->getPathInfo());
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $course = $em->getRepository(Course::class)->findOneBy(['code' => $courseCode]);

            // проверка изменения значения полей в сущности после обновления
            self::assertEquals($courseData['course[name]'], $course->getName());
            self::assertEquals($courseCode, $course->getCode());
            self::assertEquals($courseData['course[description]'], $course->getDescription());

            $crawler = $client->request('get', '/courses');
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $codeSelector = ".card[data-code=\"$courseCode\"]";
            $coursesCount = $crawler->filter($codeSelector)->count();
            // проверка отображения добавленного курса на странице списка курсов
            self::assertEquals(1, $coursesCount);
        }
    }

    public function testIncorrectEditCourse(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $manager = self::getEntityManager();
        $courseId = $manager->createQueryBuilder()
            ->select('c.id')
            ->from(Course::class, 'c')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        $crawler = $client->request('get', "/courses/$courseId/edit/");
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        foreach ($this->incorrectControlsData as $data) {
            // выбор формы добавления
            $addForm = $crawler->filter('form')->first()->form();
            foreach ($data['data'] as $inputKey => $inputValue) {
                $addForm[$inputKey]->setValue($inputValue);
            }
            $crawler = $client->submit($addForm);

            foreach ($data['messages'] as $errorMessage) {
                $errorText = $crawler->filter($errorMessage['element'])->eq($errorMessage['index'])->text();
                self::assertEquals($errorMessage['text'], $errorText);
            }

            self::assertEquals("/courses/$courseId/edit", $client->getRequest()->getPathInfo());
        }
    }

    public function testDeleteCourse(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);

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
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $deleteButton = $crawler->filter('button.btn-danger')->form();
            $client->submit($deleteButton);
            self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
            $crawler = $client->followRedirect();

            // проверка url после редиректа
            self::assertEquals('/courses/', $client->getRequest()->getPathInfo());
            self::assertEquals(200, $client->getResponse()->getStatusCode());
            $currentCourseCount = $crawler->filter('.card')->count();

            // проверка уменьшения количества курсов в списке
            $currentCourseCount = count($em->getRepository(Course::class)->findAll());
            $displayedCourseCount = $crawler->filter('.card')->count();
            self::assertEquals($currentCourseCount, $displayedCourseCount);
            self::assertEquals($courseCount - 1, $displayedCourseCount);
            $courseCount = $currentCourseCount;

            // проверка удаления курса в БД
            $deletedCourse = $em->getRepository(Course::class)->find($courseId);
            self::assertNull($deletedCourse);
        }
    }
}
