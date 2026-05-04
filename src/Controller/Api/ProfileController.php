<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\DemoProfileService;
use App\Service\ProfileManagerService;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[Route('/api/profile', name: 'api_profile_')]
final class ProfileController extends AbstractController
{
    #[Route('', name: 'update', methods: ['PATCH'])]
    public function update(Request $request, ProfileManagerService $profileManagerService, DemoProfileService $profileService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();

        try {
            $profileManagerService->updateProfile($user, $this->decodePayload($request));
        } catch (ValidationFailedException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getViolations()[0]?->getMessage() ?? 'Invalid profile payload.',
            ], 422);
        }

        return $this->json([
            'ok' => true,
            'profile' => $profileService->build($user),
        ]);
    }

    #[Route('/preferences', name: 'preferences', methods: ['POST'])]
    public function preferences(Request $request, ProfileManagerService $profileManagerService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();

        $payload = $this->decodePayload($request);
        $key = (string) ($payload['key'] ?? '');
        $enabled = (bool) ($payload['enabled'] ?? false);

        try {
            $profileManagerService->updatePreference($user, $key, $enabled);
        } catch (\InvalidArgumentException) {
            return $this->json(['ok' => false], 422);
        }

        return $this->json(['ok' => true]);
    }

    #[Route('/addresses', name: 'create_address', methods: ['POST'])]
    public function createAddress(Request $request, ProfileManagerService $profileManagerService, DemoProfileService $profileService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();

        try {
            $profileManagerService->createAddress($user, $this->decodePayload($request));
        } catch (ValidationFailedException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getViolations()[0]?->getMessage() ?? 'Invalid address payload.',
            ], 422);
        }

        return $this->json([
            'ok' => true,
            'profile' => $profileService->build($user),
        ]);
    }

    #[Route('/addresses/{id}', name: 'update_address', methods: ['PUT'])]
    public function updateAddress(int $id, Request $request, ProfileManagerService $profileManagerService, DemoProfileService $profileService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();

        try {
            $address = $profileManagerService->updateAddress($user, $id, $this->decodePayload($request));
        } catch (ValidationFailedException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getViolations()[0]?->getMessage() ?? 'Invalid address payload.',
            ], 422);
        }

        if (null === $address) {
            return $this->json(['ok' => false], 404);
        }

        return $this->json([
            'ok' => true,
            'profile' => $profileService->build($user),
        ]);
    }

    #[Route('/addresses/{id}', name: 'delete_address', methods: ['DELETE'])]
    public function deleteAddress(int $id, ProfileManagerService $profileManagerService, DemoProfileService $profileService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        /** @var User $user */
        $user = $this->getUser();

        if (!$profileManagerService->deleteAddress($user, $id)) {
            return $this->json(['ok' => false], 404);
        }

        return $this->json([
            'ok' => true,
            'profile' => $profileService->build($user),
        ]);
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
