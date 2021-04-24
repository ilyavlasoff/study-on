<?php

namespace App\Controller;

use App\Security\User;
use App\Service\PersonalQueryClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 *
 * @Route("/profile")
 */
class UserController extends AbstractController
{
    private $personalQueryClient;

    public function __construct(PersonalQueryClient $personalQueryClient)
    {
        $this->personalQueryClient = $personalQueryClient;
    }

    /**
     * @Route("/", name="user_profile")
     */
    public function profile()
    {
        /** @var User $appUser */
        $appUser = $this->getUser();

        $userProfileData = $this->personalQueryClient->currentClient($appUser);

        return $this->render('user/profile.html.twig', [
            'profile' => $userProfileData,
        ]);
    }

    /**
     * @Route("/transactions", name="user_transactions")
     */
    public function transactions()
    {
        /** @var User $user */
        $user = $this->getUser();

        $userTransactions = $this->personalQueryClient->getClientTransactions($user);

        return $this->render('user/transaction_page.twig', [
            'transactions' => $userTransactions,
            'username' => $user->getUsername(),
        ]);
    }
}
