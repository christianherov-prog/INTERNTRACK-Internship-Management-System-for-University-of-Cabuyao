<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown inside the application transaction when the student already has an accepted placement. */
class PlacementLockedException extends RuntimeException
{
}
