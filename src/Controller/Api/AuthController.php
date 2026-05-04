<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthCookieFactory;
use App\Service\SessionManager;
use App\Service\ThemePreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        SessionManager $sessionManager,
        AuthCookieFactory $authCookieFactory,
        ThemePreferenceManager $themePreferenceManager,
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        try {
            $this->validatePayload($payload, true);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        $email = mb_strtolower(trim((string) $payload['email']));
        if ($userRepository->findOneBy(['email' => $email]) instanceof User) {
            return $this->json(['error' => 'Email deja utilise.'], 409);
        }

        $user = (new User())
            ->setFirstName(trim((string) $payload['firstName']))
            ->setLastName(trim((string) $payload['lastName']))
            ->setEmail($email)
            ->setPhone($this->normalizeOptionalString($payload['phone'] ?? null))
            ->setRoles(['ROLE_USER'])
            ->setLoyaltyPoints(150)
            ->setDarkModeEnabled($themePreferenceManager->resolveDarkMode(null, $request));

        $user->setPassword($passwordHasher->hashPassword($user, (string) $payload['password']));

        $entityManager->persist($user);
        $entityManager->flush();

        [$session, $plainToken, $deviceId] = $sessionManager->createSession($user);

        $response = $this->json([
            'message' => 'Inscription reussie.',
            'redirectUrl' => $this->generateUrl('app_catalogue'),
            'user' => $this->normalizeUser($user),
            'session' => [
                'id' => $session->getId(),
                'expiresAt' => $session->getExpiresAt()->format(DATE_ATOM),
            ],
        ], 201);

        $response->headers->setCookie($authCookieFactory->buildAuthCookie($request, $plainToken, $session->getExpiresAt()));
        $response->headers->setCookie($authCookieFactory->buildDeviceCookie($request, $deviceId));

        return $response;
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        SessionManager $sessionManager,
        AuthCookieFactory $authCookieFactory,
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        try {
            $this->validatePayload($payload, false);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], 422);
        }

        $email = mb_strtolower(trim((string) $payload['email']));
        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User || !$passwordHasher->isPasswordValid($user, (string) $payload['password'])) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        [$session, $plainToken, $deviceId] = $sessionManager->createSession(
            $user,
            null,
            (bool) ($payload['remember'] ?? true),
        );

        $response = $this->json([
            'message' => 'Connexion reussie.',
            'redirectUrl' => $this->generateUrl('app_catalogue'),
            'user' => $this->normalizeUser($user),
            'session' => [
                'id' => $session->getId(),
                'expiresAt' => $session->getExpiresAt()->format(DATE_ATOM),
            ],
        ]);

        $response->headers->setCookie($authCookieFactory->buildAuthCookie($request, $plainToken, $session->getExpiresAt()));
        $response->headers->setCookie($authCookieFactory->buildDeviceCookie($request, $deviceId));

        return $response;
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(
        Request $request,
        SessionManager $sessionManager,
        AuthCookieFactory $authCookieFactory,
    ): JsonResponse {
        $request->attributes->set('_skip_auth_token_refresh', true);

        $plainToken = $request->cookies->get(AuthCookieFactory::AUTH_COOKIE_NAME);
        if (is_string($plainToken)) {
            $session = $sessionManager->findActiveSessionByPlainToken($plainToken);
            if (null !== $session) {
                $sessionManager->revoke($session, 'logout');
            }
        }

        $response = $this->json(['message' => 'Deconnexion reussie.']);
        $response->headers->setCookie($authCookieFactory->clearAuthCookie($request));
        $response->headers->setCookie($authCookieFactory->clearDeviceCookie($request));
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }
        $phpSessionCookie = $authCookieFactory->clearPhpSessionCookie($request);
        if (null !== $phpSessionCookie) {
            $response->headers->setCookie($phpSessionCookie);
        }

        return $response;
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Non authentifie.'], 401);
        }

        return $this->json($this->normalizeUser($user));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePayload(array $payload, bool $registration): void
    {
        $fields = [
            'email' => new Assert\Required([new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]),
            'password' => new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 8, max: 255)]),
            'remember' => new Assert\Optional([new Assert\Type('bool')]),
        ];

        if ($registration) {
            $fields['firstName'] = new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 100)]);
            $fields['lastName'] = new Assert\Required([new Assert\NotBlank(), new Assert\Length(max: 100)]);
            $fields['phone'] = new Assert\Optional([new Assert\Length(max: 30)]);
        }

        $violations = $this->validator->validate($payload, new Assert\Collection(
            fields: $fields,
            allowExtraFields: false,
        ));

        if (count($violations) > 0) {
            throw new \InvalidArgumentException($violations[0]?->getMessage() ?? 'Payload invalide.');
        }
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'roles' => $user->getRoles(),
        ];
    }
}
