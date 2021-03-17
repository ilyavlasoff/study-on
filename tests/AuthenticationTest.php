<?php

namespace App\Tests;

use App\DataFixtures\AppFixtures;
use App\Entity\Course;
use App\Entity\Lesson;
use App\Security\User;
use Doctrine\ORM\AbstractQuery;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\BillingClient;

class AuthenticationTest extends AbstractTest
{
    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    private $billingUrlBase;
    private $billingApiVersion;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->httpClient = self::$container->get('http_client');
        $this->serializer = self::$container->get('jms_serializer');
        $this->tokenStorage = self::$container->get('security.token_storage');
        $this->billingUrlBase = 'billing.study-on.local';
        $this->billingApiVersion = 'v1';
    }

    protected function getFixtures(): array
    {
        return [AppFixtures::class];
    }

    private function setUpMock()
    {
        $client = self::getClient();

        $client->disableReboot();

        $client->getContainer()->set(
            BillingClient::class,
            new BillingClientMock(
                $this->billingUrlBase,
                $this->billingApiVersion,
                $this->httpClient,
                $this->serializer,
                $this->tokenStorage
            )
        );
        return $client;
    }

    private function logInAdmin()
    {
        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setApiToken('password');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $this->logIn($admin);
    }

    private function logInUser()
    {
        $user = new User();
        $user->setEmail('user@test.com');
        $user->setApiToken('password');
        $user->setRoles(['ROLE_USER']);
        $this->logIn($user);
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
        $client = $this->setUpMock();
        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('app_login'));
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $loginForm = $crawler->filter('form')->first()->form();

        $userEmail = 'user@test.com';
        $userPassword = 'password';
        $loginForm['email'] = $userEmail;
        $loginForm['password'] = $userPassword;
        $crawler = $client->submit($loginForm);
        // проверка перенаправления на  главную страницу
        self::assertEquals(302, $client->getResponse()->getStatusCode());
        $crawler = $client->followRedirect();

        self::assertEquals(
            $client->getContainer()->get('router')->generate('app_index', [], UrlGenerator::ABSOLUTE_URL),
            $client->getRequest()->getUri()
        );

        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals($userEmail, $user);
    }

    public function testIncorrectLogin(): void
    {
        $client = $this->setUpMock();

        $incorrectTests = [
            [
                'email' => 'admin@test.com',
                'password' => '1password',
                'message' => ['element' => 'div.alert', 'text' => 'Invalid credentials'],
            ],
            [
                'email' => 'odmin@test.com',
                'password' => 'password',
                'message' => ['element' => 'div.alert', 'text' => 'Invalid credentials'],
            ],
        ];

        foreach ($incorrectTests as $incorrectTest) {
            $crawler = $client->request('get', $client->getContainer()->get('router')->generate('app_login'));
            self::assertEquals(200, $client->getResponse()->getStatusCode());
            $loginForm = $crawler->filter('form')->first()->form();
            $loginForm['email'] = $incorrectTest['email'];
            $loginForm['password'] = $incorrectTest['password'];
            $crawler = $client->submit($loginForm);

            self::assertEquals(302, $client->getResponse()->getStatusCode());
            $crawler = $client->followRedirect();
            // повторный редирект на страницу логина
            self::assertEquals(
                $client->getContainer()->get('router')->generate('app_login', [], UrlGenerator::ABSOLUTE_URL),
                $client->getRequest()->getUri()
            );

            $alertText = $crawler->filter($incorrectTest['message']['element'])->text();
            self::assertEquals($alertText, $incorrectTest['message']['text']);
        }
    }

    public function testCorrectRegister(): void
    {
        $client = $this->setUpMock();
        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('app_register'));
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
        self::assertEquals(
            $client->getContainer()->get('router')->generate('app_index', [], UrlGenerator::ABSOLUTE_URL),
            $client->getRequest()->getUri()
        );
        $crawler = $client->followRedirect();

        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals(
            $client->getContainer()->get('router')->generate('course_index', [], UrlGenerator::ABSOLUTE_URL),
            $client->getRequest()->getUri()
        );

        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals($email, $user);
    }

    public function testIncorrectRegister(): void
    {
        $client = $this->setUpMock();
        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('app_register'));
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
                'message' => [['element' => 'div.alert-danger ul li', 'text' => 'This password has been leaked in a data breach, it must not be used. Please use another password']],
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
                'message' => [['element' => 'div.alert-danger ul li', 'text'=> 'User with email "user@test.com" is already exists. Try to login instead']],
            ]
        ];

        foreach ($incorrectTests as $incorrectTest) {
            $loginForm['register[email]'] = $incorrectTest['email'];
            $loginForm['register[password][first]'] = $incorrectTest['password'];
            $loginForm['register[password][second]'] = $incorrectTest['doublePassword'];
            $crawler = $client->submit($loginForm);

            self::assertEquals(200, $client->getResponse()->getStatusCode());

            // повторный редирект на страницу регистрации
            self::assertEquals(
                $client->getContainer()->get('router')->generate('app_register', [], UrlGenerator::ABSOLUTE_URL),
                $client->getRequest()->getUri()
            );

            foreach ($incorrectTest['message'] as $message) {
                $errorText = $crawler->filter($message['element'])->first()->text();
                self::assertEquals($message['text'], $errorText);
            }
        }
    }

    public function testProfilePage(): void
    {
        $client = $this->setUpMock();

        $this->logInUser();
        /** @var User $user */
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('user@test.com', $user);

        $client->request('get', $client->getContainer()->get('router')->generate('course_index'));
        $client->followRedirect();
        $client->followRedirect();
        $crawler = $client->followRedirect();

        $profileLink = $crawler->filter('a.nav-link')->eq(0)->link();
        $crawler = $client->click($profileLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $userEmail = $crawler->filter('p')->eq(0)->text();
        self::assertStringContainsString($user, $userEmail);

        $userStatus = $crawler->filter('p')->eq(1)->text();
        self::assertStringContainsString('пользователь', $userStatus);
    }

    public function testAnonymousUserPageDenied(): void
    {
        $client = $this->setUpMock();

        // роуты проверяемых путей
        $routesToCheck = [
            ['routeName' => 'course_index', 'args' => []],
            ['routeName' => 'course_new', 'args' => []],
            ['routeName' => 'course_show', 'args' => ['id' => 0]],
            ['routeName' => 'course_edit', 'args' => ['id' => 0]],
            ['routeName' => 'course_delete', 'args' => ['id' => 0]],
            ['routeName' => 'lesson_new', 'args' => []],
            ['routeName' => 'lesson_show', 'args' => ['id' => 0]],
            ['routeName' => 'lesson_edit', 'args' => ['id' => 0]],
            ['routeName' => 'lesson_delete', 'args' => ['id' => 0]],
        ];

        foreach ($routesToCheck as $route) {
            $client->request('get', $client->getContainer()->get('router')->generate($route['routeName'], $route['args']));
            self::assertInstanceOf(RedirectResponse::class, $client->getResponse());
            $client->followRedirect();

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

        $crawler = $client->request('get', $client->getContainer()->get('router')->generate('course_index'));
        $client->followRedirect();
        $client->followRedirect();
        $crawler = $client->followRedirect();

        self::assertEquals(200, $client->getResponse()->getStatusCode());
        // кнопка добавления курса
        $addCourseButtonsCount = $crawler->filter('.btn')->count();
        self::assertEquals(0, $addCourseButtonsCount);

        // переход на страницу первого курса
        $courseLink = $crawler->filter('a.card-link')->first()->link();
        $crawler = $client->click($courseLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // кнопки добавления урока и редактирования курса
        $editButtonsCount = $crawler->filter('.btn-dark')->count();
        self::assertEquals(0, $editButtonsCount);
        // кнопка удаления курса
        $deleteButtonCount = $crawler->filter('.btn-danger')->count();
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
