<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ThemePreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/preferences', name: 'api_preferences_')]
final class PreferenceController extends AbstractController
{
    #[Route('/theme', name: 'theme', methods: ['POST'])]
    public function theme(
        Request $request,
        ThemePreferenceManager $themePreferenceManager,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $payload = $this->decodePayload($request);
        $darkModeEnabled = (bool) ($payload['darkModeEnabled'] ?? true);

        $user = $this->getUser();
        if ($user instanceof User) {
            $user->setDarkModeEnabled($darkModeEnabled);
            $entityManager->flush();
        }

        $response = $this->json([
            'ok' => true,
            'darkModeEnabled' => $darkModeEnabled,
        ]);
        $themePreferenceManager->persist($request, $response, $darkModeEnabled);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new BadRequestException('Invalid JSON payload.', $exception);
        }

        return is_array($payload) ? $payload : [];
    }
}
