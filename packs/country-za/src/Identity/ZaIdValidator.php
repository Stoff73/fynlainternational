<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Identity;

use Fynla\Core\Contracts\IdentityValidator;

/**
 * SA ID Number validator (13-digit Luhn).
 *
 * Format: YYMMDDSSSSCAZ
 *   YYMMDD — date of birth
 *   SSSS   — gender (0000-4999 female, 5000-9999 male)
 *   C      — citizenship (0 SA citizen, 1 permanent resident)
 *   A      — deprecated (was race classification — always 8 or 9 post-1994)
 *   Z      — Luhn check digit
 */
class ZaIdValidator implements IdentityValidator
{
    public function validate(string $idNumber): bool
    {
        $idNumber = trim($idNumber);

        if (! preg_match('/^\d{13}$/', $idNumber)) {
            return false;
        }

        // Validate DOB
        $year = (int) substr($idNumber, 0, 2);
        $month = (int) substr($idNumber, 2, 2);
        $day = (int) substr($idNumber, 4, 2);

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return false;
        }

        // Luhn check on the full 13-digit string.
        return $this->luhnCheck($idNumber);
    }

    public function extractMetadata(string $idNumber): array
    {
        if (! $this->validate($idNumber)) {
            return [];
        }

        $yy = (int) substr($idNumber, 0, 2);
        $mm = substr($idNumber, 2, 2);
        $dd = substr($idNumber, 4, 2);

        // 2-digit year disambiguation: >= current 2-digit year → 19xx,
        // else 20xx. Current year 2026 → cutoff 26.
        $currentYy = (int) date('y');
        $century = $yy > $currentYy ? '19' : '20';
        $dob = "{$century}" . sprintf('%02d', $yy) . "-{$mm}-{$dd}";

        $genderDigits = (int) substr($idNumber, 6, 4);
        $gender = $genderDigits >= 5000 ? 'male' : 'female';

        $citizenshipDigit = (int) substr($idNumber, 10, 1);
        $citizenship = $citizenshipDigit === 0 ? 'citizen' : 'permanent_resident';

        return [
            'date_of_birth' => $dob,
            'gender' => $gender,
            'citizenship' => $citizenship,
        ];
    }

    private function luhnCheck(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$length - 1 - $i];

            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }
}
