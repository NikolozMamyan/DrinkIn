<?php

declare(strict_types=1);

namespace App\Service;

final class MoneyFormatter
{
    public function euros(int $amountCents): string
    {
        return number_format($amountCents / 100, 2, ',', ' ').'EUR';
    }
}
