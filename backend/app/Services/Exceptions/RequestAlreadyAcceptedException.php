<?php

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * Thrown when a provider tries to accept a request that already has a provider assigned.
 */
class RequestAlreadyAcceptedException extends RuntimeException
{
    public function __construct(int $requestId)
    {
        parent::__construct("Service request [{$requestId}] has already been accepted by another provider.");
    }
}
