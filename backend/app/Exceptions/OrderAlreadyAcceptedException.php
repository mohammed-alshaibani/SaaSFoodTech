<?php

namespace App\Exceptions;

use Exception;

class OrderAlreadyAcceptedException extends Exception
{
    protected $message = 'This order has already been accepted by another provider.';
    
    protected $code = 409;
    
    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => $this->message,
            'error_code' => 'ORDER_ALREADY_ACCEPTED',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString()
        ], 409);
    }
}
