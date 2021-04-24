<?php

namespace App\Tests;

use App\DataFixtures\TestFixtures;
use App\Entity\Course;
use App\Model\Response\BillingUserDto;
use App\Model\Response\CourseDto;
use App\Model\Response\TransactionHistoryDto;
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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class PaymentControllerTest extends AbstractTest
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

    public function testCourseUnavailabilityForPurchase()
    {
        $client = $this->setUpMock();
        $user = $this->logInUser();

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($username);
        self::assertEquals('user@test.com', $username);
        $client->followRedirects(true);

        $courseQueryClient = self::$container->get('App\Service\CoursesQueryClient');
        $personalQueryClient = self::$container->get('App\Service\PersonalQueryClient');

        /** @var BillingUserDto $billingUser */
        $billingUser = $personalQueryClient->currentClient($user);
        $userBalance = $billingUser->getBalance();

        /** @var CourseDto[] $courses */
        $courses = $courseQueryClient->getCoursesList($user);
        // Отбор не купленных курсов со стоимостью выше баланса пользователя
        $unavailableCourses = array_filter($courses, function (CourseDto $item) use ($userBalance) {
            return !$item->getOwned() && $item->getPrice() >= $userBalance;
        });

        foreach ($unavailableCourses as $course) {
            // Для всех таких курсов проверяется страница покупки
            $courseContent = $this->getEntityManager()->getRepository(Course::class)->find(['code' => $course->getCode()]);
            $crawler = $client->request('get', "/courses/{$courseContent->getId()}");
            self::assertEquals(200, $client->getResponse()->getStatusCode());

            // Страница покупки не должна содержать кнопку покупки и должна содерать сообщение о недостаточном балансе
            $purchaseButtonCount = $crawler->filter('.btn#buy-btn')->count();
            $cashWarning = $crawler->filter('#no-cash-warning');

            // Проверка кнопки и сообщения
            self::assertEquals(0, $purchaseButtonCount);
            self::assertEquals(1, $cashWarning->count());
            self::assertEquals('На вашем счету недостаточно средств для приобретения курса.', $cashWarning->text());
        }
    }

    public function testCoursePurchase()
    {
        $client = $this->setUpMock();
        $user = $this->logInAdmin();

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($username);
        self::assertEquals('admin@test.com', $username);
        $client->followRedirects(true);

        $courseQueryClient = self::$container->get('App\Service\CoursesQueryClient');
        $personalQueryClient = self::$container->get('App\Service\PersonalQueryClient');

        /** @var BillingUserDto $billingUser */
        $billingUser = $personalQueryClient->currentClient($user);
        $userBalance = $billingUser->getBalance();

        // Отбор курсов, которые еще не куплены пользователем и стоимость которых ниже его баланса
        /** @var CourseDto[] $courses */
        $courses = $courseQueryClient->getCoursesList($user);
        $availableCourses = array_values(array_filter($courses, function (CourseDto $item) use ($userBalance) {
            return !$item->getOwned() && $item->getPrice() < $userBalance;
        }));

        // Покупка случайного курса из найденных
        /** @var CourseDto $purchasedCourse */
        $purchasedCourse = $availableCourses[random_int(0, count($availableCourses) - 1)];
        $purchasedCourseContent = $this->getEntityManager()
            ->getRepository(Course::class)->findOneBy(['code' => $purchasedCourse->getCode()]);
        $courseId = $purchasedCourseContent->getId();

        $crawler = $client->request('get', "/courses/$courseId/");
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // Поиск кнопки покупки, переход на страницу оплаты
        $purchaseButton = $crawler->filter('a#buy-btn');
        self::assertEquals(1, $purchaseButton->count());
        $crawler = $client->click($purchaseButton->link());
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // Таблица деталей оплаты
        // Содержит строки
        // - наименование операции
        // - тип операции
        // - стоимость операции
        $tableRow = $crawler->filter('table tbody tr')->eq(0);
        $tableCells = $tableRow->filter('td');

        self::assertEquals($purchasedCourse->getTitle(), $tableCells->eq(0)->text());
        self::assertEquals($purchasedCourse->getType(), $tableCells->eq(1)->text());
        $coursePrice = $purchasedCourse->getPrice() ?? 0;
        self::assertEquals("{$coursePrice} руб.", $tableCells->eq(2)->text());

        // Проверка строки итоговой стоимости
        $sumTotal = $crawler->filter('.purchase-sum')->eq(0);
        $roundedSum = round($coursePrice, 2);
        self::assertEquals("Итого: {$roundedSum} руб.", $sumTotal->text());

        // Подтверждение покупки
        $confirmForm = $crawler->filter('form')->form();
        $crawler = $client->submit($confirmForm);

        // Проверка перехода на индекс
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        self::assertEquals('/courses/', $client->getRequest()->getPathInfo());

        // Проверка уменьшения баланса счета
        $billingUser = $personalQueryClient->currentClient($user);
        $updatedUserBalance = $billingUser->getBalance();
        self::assertEquals($userBalance, $updatedUserBalance + $coursePrice);

        // Проверка наличия купленного курса в списке
        $codeSelector = ".card[data-code=\"{$purchasedCourse->getCode()}\"]";
        $coursesCount = $crawler->filter($codeSelector)->count();

        self::assertEquals(1, $coursesCount);
    }

    public function testTransactionsHistoryPage()
    {
        $client = $this->setUpMock();
        $user = $this->logInUser();

        $username = $this->tokenStorage->getToken()->getUsername();
        self::assertNotNull($username);
        self::assertEquals('user@test.com', $username);
        $client->followRedirects(true);

        $queryController = self::$container->get('App\Service\PersonalQueryClient');
        $transactionsHistory = $queryController->getClientTransactions($user);

        // Переход из индекса курсов
        $crawler = $client->request('get', '/courses');
        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // По кнопке в навбаре
        $profileLink = $crawler->filter('a.nav-link[href="/profile/"]')->eq(0)->link();
        $crawler = $client->click($profileLink);

        // На странице деталей о пользователе, клик по кнопке платежи
        self::assertEquals(200, $client->getResponse()->getStatusCode());
        $paymentsListLink = $crawler->filter('a.btn')->eq(0)->link();
        $crawler = $client->click($paymentsListLink);

        self::assertEquals(200, $client->getResponse()->getStatusCode());

        // Поиск строк таблицы
        // Каждая строка содержит
        // - тип платежа
        // - сумму платежа
        // - назначение платежа
        $tableRows = $crawler->filter('table tbody tr');
        self::assertEquals(count($transactionsHistory), $tableRows->count());

        $tableRows->each(function (Crawler $crawler, $i) use ($transactionsHistory) {
            $cells = $crawler->filter('td');
            $transactionType = $cells->eq(0)->text();
            $transactionSum = $cells->eq(1)->text();
            $transactionName = $cells->eq(2)->text();

            $transactionCourseLink = $cells->eq(2)->filter('a');
            preg_match("/^(\d+\.?\d*)? p\./", $transactionSum, $matches);
            self::assertGreaterThan(0, count($matches));
            $cost = $matches[1];
            $linkHref = $transactionCourseLink->attr('href');

            // Назначение платежа содержит прямую ссылку на страницу платежа
            preg_match("/^\/courses\/(\d+)/", $linkHref, $matches);
            self::assertGreaterThan(0, count($matches));
            $courseLocalId = $matches[1];

            /** @var Course $courseContent */
            $courseContent = $this->getEntityManager()->getRepository(Course::class)->find($courseLocalId);
            self::assertNotNull($courseContent);
            $courseCode = $courseContent->getCode();

            self::assertStringContainsString('Покупка курса: ', $transactionName);

            // Поиск записи в БД о платеже с полученными из строки данными
            $realTransactions = array_filter($transactionsHistory, function (TransactionHistoryDto $loadedCourse) use ($transactionType, $cost, $courseCode) {
                $operationTypes = [
                    'Списание' => 'payment',
                    'Пополнение' => 'deposit',
                ];

                return $loadedCourse->getType() === $operationTypes[$transactionType] ?? null
                    && $loadedCourse->getAmount() === $cost[0]
                    && $loadedCourse->getCourseCode() == $courseCode;
            });

            self::assertGreaterThan(0, $realTransactions);
        });
    }
}
