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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;

class LessonControllerTest extends AbstractTest
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    private $incorrectLessonData;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->incorrectLessonData = [
            [
                'name' => ' ',
                'indexNumber' => '101',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Name can not be blank']],
            ],
            [
                'name' => bin2hex(random_bytes(1000)),
                'indexNumber' => '101',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'Name max length is 255 symbols']],
            ],
            [
                'name' => bin2hex(random_bytes(100)),
                'indexNumber' => 'qwerty',
                'content' => 'Content',
                'message' => [['element' => '.form-error-message', 'index' => 0, 'text' => 'This value is not valid.']],
            ],
        ];
    }

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

    public function testLessonDisplayInfo(): void
    {
        $client = $this->setUpMock();
        $user = $this->logInAdmin();
        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $username);
        $client->followRedirects(true);

        $coursesQueryClient = self::$container->get('App\Service\CoursesQueryClient');
        /** @var CourseDto[] $courses */
        $courses = $coursesQueryClient->getCoursesList($user);

        $availableCourses = array_filter($courses, function (CourseDto $c) {
            return $c->getOwned();
        });
        self::assertNotEmpty($availableCourses);

        foreach ($availableCourses as $availableCourse) {
            $crawler = $client->request('GET', '/courses');
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $codeSelector = "div[data-code=\"{$availableCourse->getCode()}\"] a";
            $link = $crawler->filter($codeSelector)->eq(0)->link();
            $crawler = $client->click($link);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            /** @var EntityManagerInterface $em */
            $em = self::getEntityManager();
            $courseTitle = $crawler->filter('h1')->eq(0)->text();
            $startNamePos = strpos($courseTitle, '"') + 1;
            $endNamePos = strrpos($courseTitle, '"');
            $courseTitle = substr($courseTitle, $startNamePos, $endNamePos - $startNamePos);
            /** @var Course $course */
            $course = $em->getRepository(Course::class)->findOneBy(['name' => $courseTitle]);
            self::assertNotNull($course);

            $lessonsCount = $crawler->filter('table tbody a')->count();
            /** @var Lesson[] $lessons */
            $lessons = $em->getRepository(Lesson::class)->findBy(
                ['course' => $course->getId()],
                ['indexNumber' => 'ASC']
            );
            self::assertCount($lessonsCount, $lessons);

            for ($j = 0; $j !== $lessonsCount; ++$j) {
                $lessonLink = $crawler->filter('table tbody a')->eq($j)->link();
                $crawler = $client->click($lessonLink);
                self::assertEquals(200, $client->getResponse()->getStatusCode());

                $lessonContent = $crawler->filter('p')->eq(0)->text();
                self::assertEquals($lessonContent, $lessons[$j]->getContent());

                $lessonTitle = $crawler->filter('h1')->eq(0)->text();
                self::assertEquals($lessonTitle, $lessons[$j]->getName());

                $crawler = $client->request('GET', '/courses/' . $course->getId());
                self::assertEquals(200, $client->getResponse()->getStatusCode());
            }

            $crawler = $client->request('GET', '/courses/');
            self::assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testCreateNewLesson(): void
    {
        $client = $this->setUpMock();
        $user = $this->logInAdmin();

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $username);
        $client->followRedirects(true);

        $crawler = $client->request('GET', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $coursesCount = $crawler->filter('div.card')->count();

        $coursesQueryClient = self::$container->get('App\Service\CoursesQueryClient');
        /** @var CourseDto[] $courses */
        $courses = $coursesQueryClient->getCoursesList($user);

        $availableCourses = array_filter($courses, function (CourseDto $c) {
            return $c->getOwned();
        });
        self::assertNotEmpty($availableCourses);

        foreach ($availableCourses as $availableCourse) {
            $codeSelector = "div[data-code=\"{$availableCourse->getCode()}\"] a";
            $link = $crawler->filter($codeSelector)->eq(0)->link();
            $crawler = $client->click($link);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $previousLessonsCount = $crawler->filter('table tbody tr')->count();

            $link = $crawler->filter('a#add-lesson')->link();
            $crawler = $client->click($link);
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            $form = $crawler->filter('form')->eq(0)->form();
            $form['lesson[name]'] = 'TestLesson';
            $form['lesson[content]'] = 'This is content of lesson';
            $form['lesson[indexNumber]'] = '101';
            $crawler = $client->submit($form);

            self::assertEquals($previousLessonsCount + 1, $crawler->filter('table tbody tr')->count());

            $crawler = $client->request('GET', '/courses');
        }
    }

    public function testEditLesson(): void
    {
        $client = $this->setUpMock();
        $user = $this->logInAdmin();
        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $username);
        $client->followRedirects(true);

        $crawler = $client->request('GET', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $coursesQueryClient = self::$container->get('App\Service\CoursesQueryClient');
        /** @var CourseDto[] $courses */
        $courses = $coursesQueryClient->getCoursesList($user);

        $availableCourses = array_values(array_filter($courses, function (CourseDto $c) {
            return $c->getOwned();
        }));

        self::assertNotEmpty($availableCourses);
        $availableCourse = $availableCourses[0];

        $codeSelector = "div[data-code=\"{$availableCourse->getCode()}\"] a";
        $link = $crawler->filter($codeSelector)->eq(0)->link();
        $crawler = $client->click($link);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $lessonLink = $crawler->filter('table tbody tr a')->eq(0)->link();
        $crawler = $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $lessonPage = $client->getRequest()->getPathInfo();

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
        $editLessonForm['lesson[indexNumber]']->setValue((string) random_int(1, 1000));
        $editLessonForm['lesson[content]']->setValue($newLessonContent);
        $crawler = $client->submit($editLessonForm);

        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals($lessonPage, $client->getRequest()->getPathInfo());

        self::assertEquals($newLessonName, $crawler->filter('h1')->eq(0)->text());
        self::assertEquals($newLessonContent, $crawler->filter('p')->eq(0)->text());
    }

    public function testRemoveLesson(): void
    {
        $client = $this->setUpMock();
        $this->logInAdmin();
        $user = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($user);
        self::assertEquals('admin@test.com', $user);
        $client->followRedirects(true);

        $crawler = $client->request('GET', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $client->followRedirects(true);

        $cardLink = $crawler->filter('a.card-link')->eq(0)->link();
        $crawler = $client->click($cardLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $lessonLink = $crawler->filter('table tbody tr a')->eq(0)->link();
        $crawler = $client->click($lessonLink);
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $deleteForm = $crawler->filter('form')->eq(0)->form();
        $crawler = $client->submit($deleteForm);

        self::assertEquals(200, $client->getResponse()->getStatusCode());
    }

    public function testEditIncorrectLesson(): void
    {
        $client = $this->setUpMock();
        $user = $this->logInAdmin();

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($username);
        self::assertEquals('admin@test.com', $username);
        $client->followRedirects(true);

        $crawler = $client->request('GET', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        $coursesQueryClient = self::$container->get('App\Service\CoursesQueryClient');
        /** @var CourseDto[] $courses */
        $courses = $coursesQueryClient->getCoursesList($user);

        $availableCourses = array_values(array_filter($courses, function (CourseDto $c) {
            return $c->getOwned();
        }));

        self::assertNotEmpty($availableCourses);
        $availableCourse = $availableCourses[0];

        $codeSelector = "div[data-code=\"{$availableCourse->getCode()}\"] a";
        $link = $crawler->filter($codeSelector)->eq(0)->link();
        $crawler = $client->click($link);
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
            $crawler = $client->submit($editLessonForm);

            self::assertEquals(200, $client->getResponse()->getStatusCode());

            foreach ($incorrectLesson['message'] as $errorMessage) {
                $errorLabel = $crawler->filter($errorMessage['element'])->eq($errorMessage['index']);
                self::assertNotNull($errorLabel);
                self::assertEquals($errorMessage['text'], $errorLabel->text());
            }
        }
    }
}
