<?php

namespace App\Support;

use App\Models\OjtRequirementTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who a requirement applies to. Students match by user id, section, then program name.
 */
final class RequirementAudience
{
    /** @return list<array{type: string, id: string}> */
    public static function matchesForStudent(User $user): array
    {
        $user->loadMissing('studentProfile.program');
        $profile = $user->studentProfile;

        $matches = [
            ['type' => 'student', 'id' => (string) $user->id],
        ];

        if ($profile?->section) {
            $matches[] = ['type' => 'section', 'id' => (string) $profile->section];
        }

        $programName = $profile?->program?->name;
        if ($programName) {
            $matches[] = ['type' => 'program', 'id' => (string) $programName];
        }

        return $matches;
    }

    public static function scopeTemplatesForStudent(Builder $query, User $user): Builder
    {
        $matches = self::matchesForStudent($user);

        return $query->where(function (Builder $outer) use ($matches) {
            $outer->whereHas('targets', function (Builder $q) use ($matches) {
                $q->where(function (Builder $sub) use ($matches) {
                    foreach ($matches as $match) {
                        $sub->orWhere(function (Builder $row) use ($match) {
                            $row->where('target_type', $match['type'])
                                ->where('target_id', $match['id']);
                        });
                    }
                });
            })->orDoesntHave('targets');
        });
    }

    public static function studentCanAccessTemplate(User $user, OjtRequirementTemplate $template): bool
    {
        $targets = $template->relationLoaded('targets')
            ? $template->targets
            : $template->targets()->get();

        if ($targets->isEmpty()) {
            return true;
        }

        $matches = self::matchesForStudent($user);
        foreach ($targets as $target) {
            foreach ($matches as $match) {
                if ($target->target_type === $match['type'] && (string) $target->target_id === $match['id']) {
                    return true;
                }
            }
        }

        return false;
    }
}
