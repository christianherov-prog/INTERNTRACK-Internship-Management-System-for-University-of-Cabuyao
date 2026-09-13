<?php

namespace App\Policies;

use App\Models\Internship;
use App\Models\User;
use App\Support\InternshipAccess;

/**
 * Starter policy for internship-scoped authorization.
 * Controllers may gradually migrate from ad-hoc checks to $this->authorize().
 */
class InternshipPolicy
{
    public function view(User $user, Internship $internship): bool
    {
        return InternshipAccess::canView($user, $internship);
    }

    public function manageAsCoordinator(User $user, Internship $internship): bool
    {
        return InternshipAccess::canManageAsCoordinator($user, $internship);
    }

    public function finalizeAbsorption(User $user, Internship $internship): bool
    {
        return $user->role === 'director';
    }
}
