<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (AuthorizationException $e, Request $request) {
            if ($this->shouldReturnJson($request, $e)) {
                return response()->json([
                    'error' => [
                        'code' => 'ERR_HTTP_403',
                        'message' => $this->resolveErrorMessage($e),
                    ],
                ], 403);
            }

            return null;
        });

        $this->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($this->shouldReturnJson($request, $e)) {
                return response()->json([
                    'error' => [
                        'code' => 'ERR_HTTP_404',
                        'message' => 'Resource not found.',
                    ],
                ], 404);
            }

            return null;
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($this->shouldReturnJson($request, $e)) {
                return response()->json([
                    'error' => [
                        'code' => 'ERR_HTTP_404',
                        'message' => 'Resource not found.',
                    ],
                ], 404);
            }

            return null;
        });
    }

    public function render($request, Throwable $e)
    {
        if ($this->shouldReturnJson($request, $e)) {
            return response()->json([
                'error' => [
                    'code' => $this->resolveErrorCode($e),
                    'message' => $this->resolveErrorMessage($e),
                ],
            ], $this->resolveStatusCode($e));
        }

        return parent::render($request, $e);
    }

    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($this->shouldReturnJson($request, $exception)) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_UNAUTHENTICATED',
                    'message' => 'Authentication required.',
                ],
            ], 401);
        }

        return parent::unauthenticated($request, $exception);
    }

    protected function shouldReturnJson($request, Throwable $e): bool
    {
        if (method_exists($request, 'expectsJson') && $request->expectsJson()) {
            return true;
        }

        if (method_exists($request, 'getPathInfo')) {
            $path = ltrim((string) $request->getPathInfo(), '/');

            return str_starts_with($path, 'api/');
        }

        return false;
    }

    protected function resolveStatusCode(Throwable $e): int
    {
        if ($e instanceof ValidationException) {
            return 422;
        }

        if ($e instanceof AuthenticationException) {
            return 401;
        }

        if ($e instanceof AuthorizationException) {
            return 403;
        }

        if ($e instanceof ThrottleRequestsException) {
            return 429;
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return 404;
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode();
        }

        return 500;
    }

    protected function resolveErrorCode(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return 'ERR_VALIDATION';
        }

        if ($e instanceof AuthenticationException) {
            return 'ERR_UNAUTHENTICATED';
        }

        if ($e instanceof ThrottleRequestsException) {
            return 'ERR_TOO_MANY_REQUESTS';
        }

        if ($e instanceof AuthorizationException) {
            return 'ERR_HTTP_403';
        }

        if ($e instanceof HttpExceptionInterface) {
            return 'ERR_HTTP_'.
                $e->getStatusCode();
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return 'ERR_HTTP_404';
        }

        return 'ERR_INTERNAL_SERVER_ERROR';
    }

    protected function resolveErrorMessage(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return $e->validator->errors()->first() ?? 'Validation failed.';
        }

        if ($e instanceof AuthenticationException) {
            return $e->getMessage() ?: 'Authentication required.';
        }

        if ($e instanceof AuthorizationException) {
            return $e->getMessage() ?: 'This action is unauthorized.';
        }

        if ($e instanceof ThrottleRequestsException) {
            return 'Too many requests. Please slow down.';
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getMessage() ?: 'HTTP error encountered.';
        }

        return 'An unexpected error occurred.';
    }
}
