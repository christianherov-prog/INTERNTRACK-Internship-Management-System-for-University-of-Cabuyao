<?php

namespace App\Support;

/**
 * The controlled CCS company directory: the canonical HTE names, their
 * baseline capacity, and the legacy demo companies that were retired from it.
 *
 * `slots_available` is the only capacity column on `companies`; it is
 * decremented when a placement becomes occupying and restored when it frees
 * (see InternshipStatusController::adjustSlotsForTransition). For a controlled
 * company the authoritative capacity is therefore this baseline:
 *
 *     available = baseline capacity - internships currently occupying a slot
 */
final class CcsCompanyDirectory
{
    /** @var list<array{name: string, slots: int, industry: string}> */
    public const COMPANIES = [
        ['name' => 'Accenture Philippines', 'slots' => 72, 'industry' => 'IT Consulting'],
        ['name' => 'IBM Philippines', 'slots' => 64, 'industry' => 'IT Services'],
        ['name' => 'Infor', 'slots' => 38, 'industry' => 'Enterprise Software'],
        ['name' => 'Oracle Philippines', 'slots' => 55, 'industry' => 'Enterprise Software'],
        ['name' => 'Microsoft Philippines', 'slots' => 47, 'industry' => 'Software'],
        ['name' => 'DXC Technology Philippines', 'slots' => 80, 'industry' => 'IT Services'],
        ['name' => 'NTT DATA Philippines', 'slots' => 33, 'industry' => 'IT Services'],
        ['name' => 'Cognizant Philippines', 'slots' => 59, 'industry' => 'IT Consulting'],
        ['name' => 'Wipro Philippines', 'slots' => 41, 'industry' => 'IT Services'],
        ['name' => 'Tata Consultancy Services (TCS) Philippines', 'slots' => 68, 'industry' => 'IT Services'],
    ];

    /** Legacy demo companies that are no longer part of the controlled CCS dataset. */
    public const OBSOLETE = ['TechCorp PH', 'Asia Brewery', 'Yakult'];

    /** @return list<string> */
    public static function names(): array
    {
        return array_column(self::COMPANIES, 'name');
    }

    public static function baselineSlots(string $name): ?int
    {
        foreach (self::COMPANIES as $row) {
            if ($row['name'] === $name) {
                return $row['slots'];
            }
        }

        return null;
    }
}
