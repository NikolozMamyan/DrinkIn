<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProfileManagerService
{
    private const ADDRESS_ICONS = ['house', 'briefcase', 'store', 'location-dot'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AddressRepository $addressRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function updateProfile(User $user, array $payload): void
    {
        $this->validate($payload, new Assert\Collection(
            fields: [
                'firstName' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Length(max: 100),
                    ]),
                ],
                'lastName' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Length(max: 100),
                    ]),
                ],
                'email' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Email(),
                        new Assert\Length(max: 180),
                    ]),
                ],
                'phone' => [
                    new Assert\Optional([
                        new Assert\Length(max: 30),
                    ]),
                ],
            ],
            allowExtraFields: false,
        ));

        $email = mb_strtolower(trim((string) $payload['email']));
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
            throw new ValidationFailedException($payload, new ConstraintViolationList([
                new ConstraintViolation('Cette adresse email est deja utilisee.', null, [], $payload, 'email', $email),
            ]));
        }

        $user
            ->setFirstName(trim((string) $payload['firstName']))
            ->setLastName(trim((string) $payload['lastName']))
            ->setEmail($email)
            ->setPhone($this->normalizeOptionalString($payload['phone'] ?? null));

        $this->entityManager->flush();
    }

    public function updatePreference(User $user, string $key, bool $enabled): void
    {
        match ($key) {
            'notificationsEnabled' => $user->setNotificationsEnabled($enabled),
            'locationEnabled' => $user->setLocationEnabled($enabled),
            'darkModeEnabled' => $user->setDarkModeEnabled($enabled),
            default => throw new \InvalidArgumentException('Unknown preference key.'),
        };

        $this->entityManager->flush();
    }

    public function createAddress(User $user, array $payload): Address
    {
        $address = new Address();
        $user->addAddress($address);
        $this->hydrateAddress($user, $address, $payload, true);

        return $address;
    }

    public function updateAddress(User $user, int $id, array $payload): ?Address
    {
        $address = $this->addressRepository->findOneForUser($id, $user);
        if (!$address instanceof Address) {
            return null;
        }

        $this->hydrateAddress($user, $address, $payload, false);

        return $address;
    }

    public function deleteAddress(User $user, int $id): bool
    {
        $address = $this->addressRepository->findOneForUser($id, $user);
        if (!$address instanceof Address) {
            return false;
        }

        $wasDefault = $address->isDefault();
        $user->removeAddress($address);
        $this->entityManager->remove($address);

        if ($wasDefault) {
            $nextAddress = $user->getAddresses()->first();
            if ($nextAddress instanceof Address) {
                $nextAddress->setIsDefault(true);
            }
        }

        $this->entityManager->flush();

        return true;
    }

    private function hydrateAddress(User $user, Address $address, array $payload, bool $isNew): void
    {
        $this->validate($payload, new Assert\Collection(
            fields: [
                'label' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Length(max: 100),
                    ]),
                ],
                'street' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Length(max: 160),
                    ]),
                ],
                'postalCode' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Length(max: 20),
                    ]),
                ],
                'city' => [
                    new Assert\Required([
                        new Assert\NotBlank(),
                        new Assert\Length(max: 120),
                    ]),
                ],
                'countryCode' => [
                    new Assert\Optional([
                        new Assert\Length(min: 2, max: 2),
                    ]),
                ],
                'icon' => [
                    new Assert\Optional([
                        new Assert\Choice(choices: self::ADDRESS_ICONS),
                    ]),
                ],
                'isDefault' => [
                    new Assert\Optional([
                        new Assert\Type('bool'),
                    ]),
                ],
            ],
            allowExtraFields: false,
        ));

        $isDefault = (bool) ($payload['isDefault'] ?? false);
        if ($isNew && 1 === $user->getAddresses()->count()) {
            $isDefault = true;
        }

        if ($isDefault) {
            foreach ($user->getAddresses() as $otherAddress) {
                $otherAddress->setIsDefault(false);
            }
        } elseif (!$this->hasDefaultAddress($user, $address)) {
            $isDefault = true;
        }

        $address
            ->setLabel(trim((string) $payload['label']))
            ->setStreet(trim((string) $payload['street']))
            ->setPostalCode(trim((string) $payload['postalCode']))
            ->setCity(trim((string) $payload['city']))
            ->setCountryCode(strtoupper((string) ($payload['countryCode'] ?? 'FR')))
            ->setIcon((string) ($payload['icon'] ?? 'house'))
            ->setIsDefault($isDefault)
            ->setUser($user);

        $this->entityManager->persist($address);
        $this->entityManager->flush();
    }

    private function hasDefaultAddress(User $user, Address $ignore): bool
    {
        foreach ($user->getAddresses() as $address) {
            if ($address !== $ignore && $address->isDefault()) {
                return true;
            }
        }

        return false;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return '' === $normalized ? null : $normalized;
    }

    private function validate(array $payload, Assert\Collection $constraint): void
    {
        $violations = $this->validator->validate($payload, $constraint);
        if (0 === count($violations)) {
            return;
        }

        throw new ValidationFailedException($payload, $violations);
    }
}
