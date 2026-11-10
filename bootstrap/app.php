<?php

use App\Http\Middleware\RequireRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => 403,
                $exception instanceof ModelNotFoundException => 404,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => 500,
            };

            $message = match (true) {
                $exception instanceof ValidationException => 'Data yang dikirim tidak valid.',
                $exception instanceof AuthenticationException => 'Silakan login untuk melanjutkan.',
                $exception instanceof AuthorizationException => 'Anda tidak memiliki akses untuk tindakan ini.',
                $exception instanceof ModelNotFoundException => 'Data tidak ditemukan.',
                $status >= 500 => 'Terjadi kesalahan pada server.',
                default => $exception->getMessage() ?: 'Permintaan tidak dapat diproses.',
            };

            return response()->json([
                'status' => false,
                'message' => $message,
                'data' => $exception instanceof ValidationException
                    ? ['errors' => $exception->errors()]
                    : null,
            ], $status);
        });
    })->create();
