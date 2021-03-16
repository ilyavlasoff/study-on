<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Service\BillingClient;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    private $billingClient;

    public function __construct(BillingClient $billingClient)
    {
        $this->billingClient = $billingClient;
    }

    /**
     * @Route("/profile", name="user_profile")
     */
    public function profile()
    {
        try {
            $currentUser = $this->billingClient->currentClient();
        } catch (BillingUnavailableException $e) {
        } catch (FailureResponseException $e) {
        }

        return $this->render('user/profile.html.twig', [
            'profile' => $currentUser,
        ]);
    }
}
