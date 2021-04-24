<?php

namespace App\Controller;

use App\Exception\BillingUnavailableException;
use App\Exception\ValidationException;
use App\Form\RegisterType;
use App\Model\Request\UserRegisterCredentialsDto;
use App\Security\User;
use App\Security\UserBillingAuthenticator;
use App\Service\AuthenticationClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     *
     * @param AuthenticationUtils $authenticationUtils
     *
     * @return Response
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_index');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
    }

    /**
     * @Route("/register", name="app_register")
     *
     * @param Request $request
     * @param GuardAuthenticatorHandler $guardHandler
     * @param UserBillingAuthenticator $userAuthenticator
     * @param AuthenticationClient $authenticationClient
     *
     * @return Response
     */
    public function register(
        Request $request,
        GuardAuthenticatorHandler $guardHandler,
        UserBillingAuthenticator $userAuthenticator,
        AuthenticationClient $authenticationClient
    ): Response {
        if ($this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute('app_index');
        }

        $registrationData = new UserRegisterCredentialsDto();
        $registerForm = $this->createForm(RegisterType::class, $registrationData);
        $registerForm->handleRequest($request);

        if ($registerForm->isSubmitted() && $registerForm->isValid()) {
            try {
                $user = $authenticationClient->register($registrationData);
            } catch (ValidationException $e) {
                $genericErrors = [];
                foreach ($e->getDetails() as $errorPath => $errorMessage) {
                    if ($registerForm->has($errorPath)) {
                        $registerForm->get($errorPath)->addError(new FormError($errorMessage));
                    } else {
                        $genericErrors[] = $errorMessage;
                    }
                }

                return $this->render('security/register.html.twig', [
                    'register_form' => $registerForm->createView(),
                    'errors' => $genericErrors,
                ]);
            } catch (BillingUnavailableException $e) {
                return $this->render('security/register.html.twig', [
                    'register_form' => $registerForm->createView(),
                    'errors' => $e->getMessage(),
                ]);
            } catch (\Exception $e) {
                return $this->render('security/register.html.twig', [
                    'register_form' => $registerForm->createView(),
                    'errors' => ['Undefined error'],
                ]);
            }

            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $userAuthenticator,
                'main' // firewall name in security.yaml
            );
        }

        return $this->render('security/register.html.twig', [
            'register_form' => $registerForm->createView(),
        ]);
    }
}
