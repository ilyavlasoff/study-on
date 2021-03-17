<?php

namespace App\Controller;

use App\Exception\AuthenticationException;
use App\Exception\BillingUnavailableException;
use App\Exception\FailureResponseException;
use App\Service\BillingClient;
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
            return $this->render('error/error.html.twig', [
                'error' => 'Сервис временно недоступен',
            ]);
        } catch (FailureResponseException $e) {
            return $this->render('error/error.html.twig', [
                'error' => implode(", ", $e->getFailureErrors()),
            ]);
        } catch (AuthenticationException $e) {
            return $this->render('error/error.html.twig', [
                'error' => $e->getMessage(),
            ]);
        }

        return $this->render('user/profile.html.twig', [
            'profile' => $currentUser,
        ]);
    }
}
