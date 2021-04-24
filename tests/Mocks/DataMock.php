<?php

namespace App\Tests\Mocks;

use App\Exception\FailureResponseException;
use App\Model\Response\CourseDto;
use App\Model\Response\ErrorResponseDto;
use App\Model\Response\TransactionHistoryDto;
use App\Security\User;

class DataMock
{
    /**
     * @var CourseDto[]
     */
    public $courses;

    /**
     * @var User[]
     */
    public $registeredUsers;

    /**
     * @var int[]
     */
    public $userBalance;

    /**
     * @var array
     */
    public $clientTransactions;

    /**
     * @var array
     */
    public $boughtCoursesByClient;

    public function __construct()
    {
        $courseDataList = [
            [
                'price' => 234.23,
                'type' => 'rent',
                'code' => 'c1',
                'title' => 'course1',
                'owned' => true,
                'ownedUntil' => (new \DateTime())->modify('+1 month'),
                'rentTime' => new \DateInterval('P30D'),
            ],
            [
                'price' => 100.23,
                'type' => 'buy',
                'code' => 'c2',
                'title' => 'course2',
                'owned' => false,
            ],
            [
                'price' => 88000.23,
                'type' => 'rent',
                'code' => 'c3',
                'title' => 'course3',
                'owned' => true,
                'ownedUntil' => (new \DateTime())->modify('+2 month'),
                'rentTime' => new \DateInterval('P60D'),
            ],
            [
                'type' => 'free',
                'code' => 'c4',
                'title' => 'course4',
                'owned' => false,
            ],
            [
                'type' => 'free',
                'code' => 'c5',
                'title' => 'course5',
                'owned' => true,
            ],
        ];

        $this->courses = [];
        foreach ($courseDataList as $courseDataItem) {
            $course = new CourseDto();
            $course->setTitle($courseDataItem['title']);
            $course->setCode($courseDataItem['code']);
            $course->setType($courseDataItem['type']);
            $course->setPrice($courseDataItem['price'] ?? null);
            $course->setRentTime($courseDataItem['rentTime'] ?? null);
            $course->setOwned($courseDataItem['owned']);
            $course->setOwnedUntil($courseDataItem['ownedUntil'] ?? null);
            $this->courses[$courseDataItem['code']] = $course;
        }

        $admin = new User();
        $admin->setEmail('admin@test.com');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setApiToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlYXQiOjE2MTkxNzE4MzksImV4cCI6MTYyMTc2MzgzOSwicm9sZXMiOlsiUk9MRV9TVVBFUl9BRE1JTiJdLCJ1c2VybmFtZSI6ImFkbWluQHRlc3QuY29tIn0.mJPYf0U9u4BjzRGIDwUNvCCJueUcftbYJ1V5pGMSJmI');
        $admin->setRefreshToken('refresh');

        $user = new User();
        $user->setEmail('user@test.com');
        $user->setRoles(['ROLE_USER']);
        $user->setApiToken('eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlYXQiOjE2MTkxNzE3MzYsImV4cCI6MTYyMTc2MzczNiwicm9sZXMiOlsiUk9MRV9VU0VSIl0sInVzZXJuYW1lIjoidXNlckB0ZXN0LmNvbSJ9.tGn61X1VS9cnI90NB_pTRyDFAVTqCstx4YIXAbPxSuM');
        $user->setRefreshToken('refresh');

        $this->registeredUsers = ['admin@test.com' => $admin, 'user@test.com' => $user];

        $this->userBalance = ['admin@test.com' => 100000, 'user@test.com' => 80000];

        $boughtCourses = [
            'admin@test.com' => ['c1', 'c2', 'c4'],
            'user@test.com' => ['c2', 'c3'],
        ];

        foreach ($boughtCourses as $username => $boughtList) {
            foreach ($boughtList as $courseCode) {
                $boughtCourse = $this->courses[$courseCode];

                $transaction = new TransactionHistoryDto();
                $transaction->setType('payment');
                $transaction->setAmount($boughtCourse->getPrice() ?? 0);
                $transaction->setCourseCode($boughtCourse->getCode());

                $daysAgo = random_int(1, 30);
                $transaction->setCreatedAt((new \DateTime())->modify("-$daysAgo days"));

                $this->clientTransactions[$username][] = $transaction;
                $this->boughtCoursesByClient[$username][] = $boughtCourse;
            }
        }
    }

    public function testUserValid(User $user, $role = '')
    {
        if (!array_key_exists($user->getUsername(), $this->registeredUsers) ||
            $this->registeredUsers[$user->getUsername()]->getApiToken() !== $user->getApiToken()) {
            $error = new ErrorResponseDto();
            $error->setCode(401);
            $error->setMessage('Invalid credentials.');
            throw new FailureResponseException($error);
        }

        if ($role && !in_array($role, $this->registeredUsers[$user->getUsername()]->getRoles())) {
            $error = new ErrorResponseDto();
            $error->setCode(403);
            throw new FailureResponseException($error);
        }
    }

    public function getJwt(User $user)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);

        $payload = json_encode([
            'eat' => (new \DateTime())->getTimestamp(),
            'exp' => (new \DateTime())->modify('+1 hour')->getTimestamp(),
            'roles' => $user->getRoles(),
            'username' => $user->getUsername(),
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . '.' . $base64UrlPayload, 'abC123!', true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }
}
