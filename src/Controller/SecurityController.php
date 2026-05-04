<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ThemePreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_catalogue');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ThemePreferenceManager $themePreferenceManager,
        Security $security,
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

            $response = $security->login($user, 'form_login', 'main', [new RememberMeBadge()]);

            return $response ?? $this->redirectToRoute('app_catalogue');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Logout is managed by Symfony security.');
    }
}
