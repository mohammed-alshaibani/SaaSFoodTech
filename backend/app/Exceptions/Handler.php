<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        AuthenticationException::class,
        AuthorizationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Log structured data for debugging
            if (app()->environment('production')) {
                logger()->error('API Error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request_id' => request()->header('X-Request-ID'),
                    'user_id' => auth()->id(),
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                ]);
            }
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): JsonResponse
    {
        // Add request ID for tracking
        $requestId = $request->header('X-Request-ID') ?? uniqid('req_', true);

        // Handle API requests specifically
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($e, $requestId);
        }

        // Fallback to default handling for web requests
        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with consistent JSON responses.
     */
    protected function handleApiException(Throwable $e, string $requestId): JsonResponse
    {
        $statusCode = 500;
        $errorCode = 'INTERNAL_ERROR';
        $message = 'An unexpected error occurred. Please try again.';
        $details = [];

        // Authentication errors
        if ($e instanceof AuthenticationException) {
            $statusCode = 401;
            $errorCode = 'UNAUTHENTICATED';
            $message = 'Authentication required. Please login to continue.';
        }

        // Authorization errors
        elseif ($e instanceof AuthorizationException) {
            $statusCode = 403;
            $errorCode = 'FORBIDDEN';
            $message = 'You do not have permission to perform this action.';
        }

        // Validation errors
        elseif ($e instanceof ValidationException) {
            $statusCode = 422;
            $errorCode = 'VALIDATION_FAILED';
            $message = 'The given data was invalid.';
            $details = $e->errors();
        }

        // Not found errors
        elseif ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            $statusCode = 404;
            $errorCode = 'NOT_FOUND';
            $message = 'The requested resource was not found.';
        }

        // Method not allowed
        elseif ($e instanceof MethodNotAllowedHttpException) {
            $statusCode = 405;
            $errorCode = 'METHOD_NOT_ALLOWED';
            $message = 'The HTTP method is not allowed for this endpoint.';
        }

        // Rate limiting
        elseif ($e instanceof TooManyRequestsHttpException) {
            $statusCode = 429;
            $errorCode = 'TOO_MANY_REQUESTS';
            $message = 'Too many requests. Please try again later.';
            $details['retry_after'] = $e->getHeaders()['Retry-After'] ?? 60;
        }

        // HTTP exceptions
        elseif ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
            $errorCode = 'HTTP_ERROR';
            $message = $e->getMessage() ?: 'An HTTP error occurred.';
        }

        // Log the error for debugging
        if ($statusCode >= 500) {
            logger()->critical('API Server Error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request_id' => $requestId,
                'user_id' => auth()->id(),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $message,
                'details' => $details,
                'request_id' => $requestId,
                'timestamp' => now()->toISOString(),
            ]
        ], $statusCode);
    }
}
