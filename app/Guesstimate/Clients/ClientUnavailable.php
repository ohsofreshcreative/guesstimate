<?php

namespace App\Guesstimate\Clients;

use RuntimeException;

/**
 * Klient wybrany do oferty został w międzyczasie usunięty (soft delete).
 */
class ClientUnavailable extends RuntimeException
{
}
