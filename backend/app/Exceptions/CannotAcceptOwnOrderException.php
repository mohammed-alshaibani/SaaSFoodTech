<?php

namespace App\Exceptions;

use Exception;

class CannotAcceptOwnOrderException extends Exception
{
    protected $message = 'Customers cannot accept their own orders.';
    
    protected $code = 403;
    
    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => $this->message,
            'error_code' => 'CANNOT_ACCEPT_OWN_ORDER',
            'request_id' => request()->header('X-Request-ID'),
            'timestamp' => now()->toISOString()
        ], 403);
    }
}
