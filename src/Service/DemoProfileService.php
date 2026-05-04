<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

final class DemoProfileService
{
    /**
     * @return array<string, mixed>
     */
    public function build(?User $user): array
    {
        if (null === $user) {
            return [
                'firstName' => '',
                'lastName' => '',
                'fullName' => '',
                'initial' => '',
                'email' => '',
                'phone' => '',
                'points' => 0,
                'memberLevel' => 'Silver',
                'stats' => [
                    ['value' => '0', 'label' => 'Commandes'],
                    ['value' => '0', 'label' => 'Points'],
                    ['value' => '0EUR', 'label' => 'Depense'],
                ],
                'preferences' => [
                    'notificationsEnabled' => false,
                    'locationEnabled' => false,
                    'darkModeEnabled' => false,
                ],
                'addresses' => [],
            ];
        }

        $addresses = array_map(static fn ($address): array => [
            'id' => $address->getId(),
            'label' => $address->getLabel(),
            'address' => sprintf('%s, %s %s', $address->getStreet(), $address->getPostalCode(), $address->getCity()),
            'street' => $address->getStreet(),
            'postalCode' => $address->getPostalCode(),
            'city' => $address->getCity(),
            'countryCode' => $address->getCountryCode(),
            'icon' => $address->getIcon(),
            'isDefault' => $address->isDefault(),
        ], $user->getAddresses()->toArray());

        usort($addresses, static function (array $left, array $right): int {
            if ($left['isDefault'] === $right['isDefault']) {
                return strcmp($left['label'], $right['label']);
            }

            return $left['isDefault'] ? -1 : 1;
        });

        return [
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'initial' => strtoupper(substr($user->getFirstName(), 0, 1)),
            'email' => $user->getEmail(),
            'phone' => $user->getPhone() ?? '',
            'points' => $user->getLoyaltyPoints(),
            'memberLevel' => $user->getLoyaltyPoints() >= 1200 ? 'Gold' : 'Silver',
            'stats' => [
                ['value' => (string) $user->getOrders()->count(), 'label' => 'Commandes'],
                ['value' => number_format($user->getLoyaltyPoints(), 0, ',', ' '), 'label' => 'Points'],
                ['value' => number_format(array_sum(array_map(static fn ($order): int => $order->getTotalCents(), $user->getOrders()->toArray())) / 100, 0, ',', ' ').'EUR', 'label' => 'Depense'],
            ],
            'preferences' => [
                'notificationsEnabled' => $user->isNotificationsEnabled(),
                'locationEnabled' => $user->isLocationEnabled(),
                'darkModeEnabled' => $user->isDarkModeEnabled(),
            ],
            'addresses' => $addresses,
        ];
    }
}
