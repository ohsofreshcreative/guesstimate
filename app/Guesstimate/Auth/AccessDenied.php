<?php

namespace App\Guesstimate\Auth;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Odmowa w warstwie usług. Bez kontrolera, który ją obsłuży, kończy się odpowiedzią 403, a nie błędem 500.
 */
class AccessDenied extends AccessDeniedHttpException
{
}
