<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        \Illuminate\Database\QueryException::class => 'error',
        \PDOException::class => 'critical',
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Validation\ValidationException::class,
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
            Log::error('Exception occurred', [
                'message' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // API requests - return JSON error response
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return $this->renderApiError($request, $e);
        }

        // Web requests - handle with session flash messages and redirects
        return $this->renderWebError($request, $e);
    }

    /**
     * Render API error response
     */
    protected function renderApiError($request, Throwable $e): \Illuminate\Http\JsonResponse
    {
        $statusCode = 500;
        $message = 'Server Error';
        $error = null;

        if ($e instanceof ValidationException) {
            $statusCode = 422;
            $message = 'Validation failed';
            $error = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $statusCode = 401;
            $message = 'Unauthenticated';
            $error = 'Please login to access this resource';
        } elseif ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            $statusCode = 404;
            $message = 'Not Found';
            $error = 'The requested resource was not found';
        } elseif ($e instanceof MethodNotAllowedHttpException) {
            $statusCode = 405;
            $message = 'Method Not Allowed';
            $error = 'This HTTP method is not allowed for this endpoint';
        } elseif ($e instanceof ThrottleRequestsException) {
            $statusCode = 429;
            $message = 'Too Many Requests';
            $error = 'Please slow down and try again later';
        } elseif ($e instanceof TokenMismatchException) {
            $statusCode = 419;
            $message = 'Page Expired';
            $error = 'Your session has expired. Please refresh the page and try again';
        } elseif ($e instanceof \Illuminate\Database\QueryException) {
            $statusCode = 500;
            $message = 'Database Error';
            $error = 'A database error occurred. Please try again later';
        } elseif ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'Bcrypt')) {
            // Handle bcrypt/password hash errors
            $statusCode = 500;
            $message = 'Authentication Error';
            $error = 'There was a problem with the authentication system. Please contact support';
        } else {
            // Generic server error - don't expose internal details in production
            $error = app()->environment('production')
                ? 'An unexpected error occurred. Please try again later'
                : $e->getMessage();
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => $error,
            'code' => $statusCode,
        ], $statusCode);
    }

    /**
     * Render Web error response with session flash
     */
    protected function renderWebError($request, Throwable $e): \Symfony\Component\HttpFoundation\Response
    {
        $message = null;

        if ($e instanceof ValidationException) {
            return parent::render($request, $e);
        } elseif ($e instanceof AuthenticationException) {
            return redirect()->route('admin.login')
                ->with('error', 'Please login to access this page.');
        } elseif ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            $message = 'The requested page or resource was not found.';
        } elseif ($e instanceof TokenMismatchException) {
            $message = 'Your session has expired. Please refresh the page and try again.';
        } elseif ($e instanceof ThrottleRequestsException) {
            $message = 'Too many attempts. Please slow down and try again later.';
        } elseif ($e instanceof \RuntimeException && str_contains($e->getMessage(), 'Bcrypt')) {
            $message = 'There was a problem with the authentication system. Please contact support.';
        } elseif ($e instanceof \Illuminate\Database\QueryException) {
            $message = 'A database error occurred. Please try again later.';
        } else {
            // Generic error
            $message = app()->environment('production')
                ? 'An unexpected error occurred. Please try again later.'
                : $e->getMessage();
        }

        // For admin routes, flash error and redirect back or to dashboard
        if ($request->is('admin/*')) {
            if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Error',
                    'error' => $message,
                ], 500);
            }

            // If there's a previous page, go back with error
            if (url()->previous() !== url()->current()) {
                return redirect()->back()->with('error', $message);
            }

            // Otherwise go to admin dashboard
            return redirect()->route('admin.dashboard')->with('error', $message);
        }

        return parent::render($request, $e);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        // For API requests, return JSON response
        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'error' => 'No valid authentication token provided or token has expired.',
                'code' => 401
            ], 401);
        }

        // For web requests, redirect to login
        return redirect()->guest(route('admin.login'))
            ->with('error', 'Please login to access this page.');
    }
}