<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\DataFixtures\TestFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Security\User;
use App\Service\AuthenticationClient;
use App\Service\BillingClient;
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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AuthenticationTest extends AbstractTest
{
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
        $this->tokenStorage = self::$container->get('security.token_storage');
        $this->entityManager = self::$container->get('doctrine.orm.entity_manager');
    }

    protected function getFixtures(): array
    {
        return [TestFixtures::class];
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

    public function testAnonymousUserRedirect(): void
    {
        // перенаправление анонимного пользователя на страницу регистрации и кода ответа страницы авторизации
        $client = $this->setUpMock();
        $client->request('get', '/');
        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        $client->followRedirect();
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals(
            $client->getContainer()->get('router')->generate('app_login', [], UrlGenerator::ABSOLUTE_URL),
            $client->getRequest()->getUri()
        );
    }

    public function testCorrectLogin(): void
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $user->setApiToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlYXQiOjE2MTkxNzE4MzksImV4cCI6MTYyMTc2MzgzOSwicm9sZXMiOlsiUk9MRV9TVVBFUl9BRE1JTiJdLCJ1c2VybmFtZSI6ImFkbWluQHRlc3QuY29tIn0.mJPYf0U9u4BjzRGIDwUNvCCJueUcftbYJ1V5pGMSJmI');
        $user->setRoles(['ROLE_USER']);

        $providerKey = 'main';
        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->tokenStorage->setToken($token);
        self::$container->get('session')->set('_security_' . $providerKey, serialize($token));

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('user@test.com', $username);
    }

    public function testIncorrectLogin(): void
    {
        $client = $this->setUpMock();

        $incorrectTests = [
            [
                'email' => 'admin@test.com',
                'password' => '1password',
                'message' => ['element' => 'div.alert', 'text' => 'Invalid credentials.'],
            ],
            [
                'email' => 'wdmin@test.com',
                'password' => 'password',
                'message' => ['element' => 'div.alert', 'text' => 'Invalid credentials.'],
            ],
        ];

        foreach ($incorrectTests as $incorrectTest) {
            $crawler = $client->request('get', '/login');
            self::assertEquals(200, $client->getResponse()->getStatusCode());
            $loginForm = $crawler->filter('form')->first()->form();
            $loginForm['email'] = $incorrectTest['email'];
            $loginForm['password'] = $incorrectTest['password'];
            $crawler = $client->submit($loginForm);

            self::assertEquals(302, $client->getResponse()->getStatusCode());
            $crawler = $client->followRedirect();
            // повторный редирект на страницу логина
            self::assertEquals('/login', $client->getRequest()->getPathInfo());

            $alertText = $crawler->filter($incorrectTest['message']['element'])->text();
            self::assertEquals($incorrectTest['message']['text'], $alertText);
        }
    }

    public function testCorrectRegister(): void
    {
        $client = $this->setUpMock();
        $crawler = $client->request('get', '/register');
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $loginForm = $crawler->filter('form')->first()->form();

        $email = 'unittest@test.com';
        $password = '!23SuperP@$$w0rd32!';
        $loginForm['register[email]'] = $email;
        $loginForm['register[password][first]'] = $password;
        $loginForm['register[password][second]'] = $password;
        $client->submit($loginForm);

        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        $crawler = $client->followRedirect();

        self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
        self::assertEquals('/', $client->getRequest()->getPathInfo());
        $crawler = $client->followRedirect();

        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals($email, $user);
    }

    public function testIncorrectRegister(): void
    {
        $client = $this->setUpMock();
        $crawler = $client->request('get', '/register');
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $loginForm = $crawler->filter('form')->first()->form();

        $incorrectTests = [
            [
                'email' => 'unittest@test.com',
                'password' => '12345',
                'doublePassword' => '12345',
                'message' => [['element' => '.form-error-message', 'text' => 'Пароль должен содержать не менее 6 символов']],
            ],
            [
                'email' => 'unittest@test.com',
                'password' => '1234567',
                'doublePassword' => '1234567',
                'message' => [['element' => '.form-error-message', 'text' => 'This password has been leaked in a data breach, it must not be used. Please use another password']],
            ],
            [
                'email' => 'unittest@test.com',
                'password' => '1234567',
                'doublePassword' => '7654321',
                'message' => [['element' => '.form-error-message', 'text' => 'Пароли должны совпадать']],
            ],
            [
                'email' => 'user@test.com',
                'password' => '!23SuperP@$$w0rd32!',
                'doublePassword' => '!23SuperP@$$w0rd32!',
                'message' => [['element' => '.form-error-message', 'text' => 'User with email "user@test.com" is already exists. Try to login instead']],
            ],
        ];

        foreach ($incorrectTests as $incorrectTest) {
            $loginForm['register[email]']->setValue($incorrectTest['email']);
            $loginForm['register[password][first]']->setValue($incorrectTest['password']);
            $loginForm['register[password][second]']->setValue($incorrectTest['doublePassword']);
            $crawler = $client->submit($loginForm);

            self::assertEquals(200, $client->getResponse()->getStatusCode());

            // повторный редирект на страницу регистрации
            self::assertEquals('/register', $client->getRequest()->getPathInfo());

            foreach ($incorrectTest['message'] as $message) {
                $errorText = $crawler->filter($message['element'])->first()->text();
                self::assertEquals($message['text'], $errorText);
            }
        }
    }

    public function testProfilePage(): void
    {
        $client = $this->setUpMock();
        $user = $this->logInUser();

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($username);
        self::assertEquals('user@test.com', $username);
        $client->followRedirects(true);

        // Переход с индекса курсов по кнопке в навбаре
        $crawler = $client->request('get', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $profileLink = $crawler->filter('a[href="/profile/"]')->eq(0)->link();
        $crawler = $client->click($profileLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals('/profile/', $client->getRequest()->getPathInfo());

        // Проверка наличия имени пользователя
        $userEmail = $crawler->filter('p')->eq(0)->text();
        self::assertStringContainsString($username, $userEmail);

        // Проверка наличия роли пользователя
        $userStatus = $crawler->filter('p')->eq(1)->text();
        self::assertStringContainsString('пользователь', $userStatus);
    }

    public function testAnonymousUserPageDenied(): void
    {
        $client = $this->setUpMock();

        $course = $this->entityManager->getRepository(Course::class)->findOneBy(['code' => 'c1']);
        $lessons = $this->entityManager->getRepository(Lesson::class)->findAll();

        $client->followRedirects(true);

        // роуты проверяемых путей
        $routesToCheck = [
            ['routeName' => 'course_new', 'args' => [], 'method'=> 'get'],
            ['routeName' => 'course_edit', 'args' => ['id' => $course->getId()], 'method'=> 'get'],
            ['routeName' => 'course_delete', 'args' => ['id' => $course->getId()], 'method'=> 'delete'],
            ['routeName' => 'lesson_new', 'args' => [], 'method'=> 'get'],
            ['routeName' => 'lesson_show', 'args' => ['id' => $lessons[0]->getId()], 'method'=> 'get'],
            ['routeName' => 'lesson_edit', 'args' => ['id' => $lessons[0]->getId()], 'method'=> 'get'],
            ['routeName' => 'lesson_delete', 'args' => ['id' => $lessons[0]->getId()], 'method'=> 'get'],
        ];

        foreach ($routesToCheck as $route) {
            $client->request($route['method'], $client->getContainer()->get('router')->generate($route['routeName'], $route['args']));

            // проверка редиректа на логин
            self::assertEquals(
                $client->getContainer()->get('router')->generate('app_login', [], UrlGenerator::ABSOLUTE_URL),
                $client->getRequest()->getUri()
            );
        }
    }

    public function testOrdinaryUserElementsUnavailability(): void
    {
        $client = $this->setUpMock();

        $this->logInUser();
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('user@test.com', $user);
        $client->followRedirects(true);

        $crawler = $client->request('get', '/');

        self::assertEquals(200, $client->getResponse()->getStatusCode());
        // кнопка добавления курса
        $addCourseButtonsCount = $crawler->filter('#add-btn')->count();
        self::assertEquals(0, $addCourseButtonsCount);

        // переход на страницу первого курса
        $courseLink = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($courseLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // кнопки добавления урока и редактирования курса
        $editButtonsCount = $crawler->filter('#course-edit')->count();
        self::assertEquals(0, $editButtonsCount);
        // кнопка удаления курса
        $deleteButtonCount = $crawler->filter('#add-lesson')->count();
        self::assertEquals(0, $deleteButtonCount);

        // переход на страницу первого урока
        $lessonLink = $crawler->filter('table td a')->first()->link();
        $crawler = $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // кнопки удаления и редактирования уроков
        $lessonDeleteEditButtonsCount = $crawler->filter('.btn')->count();
        self::assertEquals(0, $lessonDeleteEditButtonsCount);
    }

    public function testOrdinaryUserPagesUnavailability(): void
    {
        $client = $this->setUpMock();

        $this->logInUser();
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('user@test.com', $user);

        $client->request('get', $client->getContainer()->get('router')->generate('app_index'));
        $client->followRedirect();
        $client->followRedirect();
        $client->followRedirect();

        $em = self::getEntityManager();

        /** @var Course $randomCourse */
        $randomCourse = $em->createQueryBuilder()
            ->select('c')
            ->from(Course::class, 'c')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult(AbstractQuery::HYDRATE_OBJECT);

        /** @var Lesson $randomLesson */
        $randomLesson = $em->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult(AbstractQuery::HYDRATE_OBJECT);

        // роуты проверяемых путей
        $routesToCheck = [
            ['method' => 'get', 'routeName' => 'course_new', 'args' => [], 'desired' => 403],
            ['method' => 'get', 'routeName' => 'course_edit', 'args' => ['id' => $randomCourse->getId()], 'desired' => 403],
            ['method' => 'delete', 'routeName' => 'course_delete', 'args' => ['id' => $randomCourse->getId()], 'desired' => 403],
            ['method' => 'get', 'routeName' => 'lesson_new', 'args' => ['course' => $randomCourse->getId()], 'desired' => 403],
            ['method' => 'get', 'routeName' => 'lesson_edit', 'args' => ['id' => $randomLesson->getId()], 'desired' => 403],
            ['method' => 'delete', 'routeName' => 'lesson_delete', 'args' => ['id' => $randomLesson->getId()], 'desired' => 403],
        ];

        foreach ($routesToCheck as $route) {
            $client->request($route['method'], $client->getContainer()->get('router')->generate($route['routeName'], $route['args']));
            self::assertEquals($route['desired'], $client->getResponse()->getStatusCode(), "route:{$route['routeName']}");
        }
    }

    public function testSuperAdminUserElementsAvailability(): void
    {
        $client = $this->setUpMock();

        $this->logInAdmin();
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);

        $client->request('get', $client->getContainer()->get('router')->generate('app_index'));
        $client->followRedirect();
        $client->followRedirect();
        $client->followRedirect();

        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('course_index'));
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        // кнопка добавления курса
        $addCourseButton = $crawler->filter('.btn');
        self::assertNotNull($addCourseButton);

        // переход на страницу первого курса
        $courseLink = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($courseLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // кнопки добавления урока и редактирования курса
        $editButtonsCount = $crawler->filter('.btn-dark')->count();
        self::assertEquals(2, $editButtonsCount);
        // кнопка удаления курса
        $deleteButtonCount = $crawler->filter('.btn-danger')->count();
        self::assertEquals(1, $deleteButtonCount);

        // переход на страницу первого урока
        $lessonLink = $crawler->filter('table td a')->first()->link();
        $crawler = $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // кнопки удаления и редактирования уроков
        $lessonDeleteEditButtonsCount = $crawler->filter('.btn')->count();
        self::assertEquals(2, $lessonDeleteEditButtonsCount);
    }

    public function testSuperAdminUserPagesAvailability(): void
    {
        $client = $this->setUpMock();

        $this->logInAdmin();
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);

        $client->request('get', $client->getContainer()->get('router')->generate('app_index'));
        $client->followRedirect();
        $client->followRedirect();
        $client->followRedirect();

        $em = self::getEntityManager();

        /** @var Course $randomCourse */
        $randomCourse = $em->createQueryBuilder()
            ->select('c')
            ->from(Course::class, 'c')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult(AbstractQuery::HYDRATE_OBJECT);

        /** @var Lesson $randomLesson */
        $randomLesson = $em->createQueryBuilder()
            ->select('l')
            ->from(Lesson::class, 'l')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult(AbstractQuery::HYDRATE_OBJECT);

        // роуты проверяемых путей
        $routesToCheck = [
            ['method' => 'get', 'routeName' => 'course_new', 'args' => [], 'desired' => 200],
            ['method' => 'get', 'routeName' => 'course_edit', 'args' => ['id' => $randomCourse->getId()], 'desired' => 200],
            ['method' => 'delete', 'routeName' => 'course_delete', 'args' => ['id' => $randomCourse->getId()], 'desired' => 302],
            ['method' => 'get', 'routeName' => 'lesson_new', 'args' => ['course' => $randomCourse->getId()], 'desired' => 200],
            ['method' => 'get', 'routeName' => 'lesson_edit', 'args' => ['id' => $randomLesson->getId()], 'desired' => 200],
            ['method' => 'delete', 'routeName' => 'lesson_delete', 'args' => ['id' => $randomLesson->getId()], 'desired' => 302],
        ];

        foreach ($routesToCheck as $route) {
            $client->request($route['method'], $client->getContainer()->get('router')->generate($route['routeName'], $route['args']));
            self::assertEquals($route['desired'], $client->getResponse()->getStatusCode(), "route:{$route['routeName']}");
        }
    }
}
