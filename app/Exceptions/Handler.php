<?php

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
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
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Handle API requests with standardized JSON responses
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with standardized responses.
     */
    protected function handleApiException(Request $request, Throwable $e)
    {
        // Validation exceptions
        if ($e instanceof ValidationException) {
            return ApiResponse::validationError(
                $e->errors(),
                'The given data was invalid.'
            );
        }

        // Authentication exceptions
        if ($e instanceof AuthenticationException) {
            return ApiResponse::unauthorized('Authentication required.');
        }

        // Authorization exceptions
        if ($e instanceof AuthorizationException) {
            return ApiResponse::forbidden('This action is unauthorized.');
        }

        // Model not found exceptions
        if ($e instanceof ModelNotFoundException) {
            $model = class_basename($e->getModel());
            return ApiResponse::notFound("The requested {$model} was not found.");
        }

        // Not found HTTP exceptions
        if ($e instanceof NotFoundHttpException) {
            return ApiResponse::notFound('The requested resource was not found.');
        }

        // Method not allowed exceptions
        if ($e instanceof MethodNotAllowedHttpException) {
            return ApiResponse::error(
                'The specified method for the request is invalid.',
                Response::HTTP_METHOD_NOT_ALLOWED
            );
        }

        // Rate limiting exceptions
        if ($e instanceof ThrottleRequestsException) {
            return ApiResponse::error(
                'Too many requests. Please try again later.',
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        // Database connection exceptions
        if ($e instanceof \Illuminate\Database\QueryException) {
            // Log the actual error for debugging
            Log::error('Database error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return ApiResponse::serverError('A database error occurred. Please try again.');
        }

        // General server errors
        if (config('app.debug')) {
            // In debug mode, show detailed error information
            return ApiResponse::error(
                $e->getMessage(),
                $this->getStatusCode($e),
                [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTrace(),
                ]
            );
        }

        // In production, hide detailed error information
        Log::error('Server error: ' . $e->getMessage(), [
            'exception' => $e,
            'request' => $request->all(),
            'user_id' => Auth::id(),
        ]);

        return ApiResponse::serverError('An unexpected error occurred. Please try again.');
    }

    /**
     * Get the appropriate HTTP status code for the exception.
     */
    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && $e->getCode() >= 400 && $e->getCode() < 600) {
            return $e->getCode();
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponse::unauthorized('Authentication required.');
        }

        return redirect()->guest($exception->redirectTo() ?? route('login'));
    }
}