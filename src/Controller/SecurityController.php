<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthCookieFactory;
use App\Service\SessionManager;
use App\Service\ThemePreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SessionManager $sessionManager,
        AuthCookieFactory $authCookieFactory,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_catalogue');
        }

        $lastUsername = '';
        $error = null;

        if ($request->isMethod('POST')) {
            $lastUsername = trim((string) $request->request->get('_username'));

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('authenticate', (string) $request->request->get('_csrf_token')))) {
                $error = 'Session invalide. Merci de reessayer.';
            } else {
                $user = $userRepository->findOneBy(['email' => mb_strtolower($lastUsername)]);
                if ($user instanceof User && $passwordHasher->isPasswordValid($user, (string) $request->request->get('_password'))) {
                    [$session, $plainToken, $deviceId] = $sessionManager->createSession(
                        $user,
                        null,
                        $request->request->getBoolean('_remember_me', true),
                    );

                    $response = $this->redirectToRoute('app_catalogue');
                    $response->headers->setCookie($authCookieFactory->buildAuthCookie($request, $plainToken, $session->getExpiresAt()));
                    $response->headers->setCookie($authCookieFactory->buildDeviceCookie($request, $deviceId));

                    return $response;
                }

                $error = 'Identifiants invalides.';
            }
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ThemePreferenceManager $themePreferenceManager,
        SessionManager $sessionManager,
        AuthCookieFactory $authCookieFactory,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_catalogue');
        }

        $user = new User();
        $form = $this->createFormBuilder($user)
            ->add('firstName', TextType::class, ['label' => 'Prenom'])
            ->add('lastName', TextType::class, ['label' => 'Nom'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('phone', TextType::class, ['label' => 'Telephone', 'required' => false])
            ->add('plainPassword', RepeatedType::class, [
                'mapped' => false,
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Confirmation'],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Creer mon compte'])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (null !== $userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('error', 'Cette adresse email est deja utilisee.');

                return $this->redirectToRoute('app_register');
            }

            $user
                ->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()))
                ->setRoles(['ROLE_USER'])
                ->setDarkModeEnabled($themePreferenceManager->resolveDarkMode(null, $request))
                ->setLoyaltyPoints(150);

            $entityManager->persist($user);
            $entityManager->flush();
            $this->addFlash('success', 'Compte cree. Bienvenue sur DrinkIn.');

            [$session, $plainToken, $deviceId] = $sessionManager->createSession($user);

            $response = $this->redirectToRoute('app_catalogue');
            $response->headers->setCookie($authCookieFactory->buildAuthCookie($request, $plainToken, $session->getExpiresAt()));
            $response->headers->setCookie($authCookieFactory->buildDeviceCookie($request, $deviceId));

            return $response;
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(Request $request, SessionManager $sessionManager, AuthCookieFactory $authCookieFactory): Response
    {
        $request->attributes->set('_skip_auth_token_refresh', true);

        $plainToken = $request->cookies->get(AuthCookieFactory::AUTH_COOKIE_NAME);
        if (is_string($plainToken)) {
            $session = $sessionManager->findActiveSessionByPlainToken($plainToken);
            if (null !== $session) {
                $sessionManager->revoke($session, 'logout');
            }
        }

        $request->getSession()->invalidate();
        $response = $this->redirectToRoute('app_home');
        $response->headers->setCookie($authCookieFactory->clearAuthCookie($request));
        $response->headers->setCookie($authCookieFactory->clearDeviceCookie($request));
        $phpSessionCookie = $authCookieFactory->clearPhpSessionCookie($request);
        if (null !== $phpSessionCookie) {
            $response->headers->setCookie($phpSessionCookie);
        }
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
