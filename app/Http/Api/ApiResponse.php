<?php

namespace App\Http\Api;

use App\Exceptions\CheckoutException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The mobile API envelope (mobile plan §1), shared by every /api/* response
 * so the app's ApiClient can parse success and failure the same way:
 *
 *   {"ok":true,"data":…,"meta":{page,per_page,total,last_page}}
 *   {"ok":false,"error":{"code","message","fields":{field:[msg]}}}
 */
final class ApiResponse
{
    /**
     * @param  mixed  $data  Array, JsonResource, resource collection or paginator.
     * @param  array<string, mixed>|null  $meta
     */
    public static function ok(mixed $data = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        $paginator = $data instanceof LengthAwarePaginator ? $data : null;

        if ($data instanceof JsonResource) {
            $paginator = $data->resource instanceof LengthAwarePaginator ? $data->resource : null;
            $data = $data->resolve();
        } elseif ($paginator !== null) {
            $data = $paginator->items();
        }

        if ($paginator !== null) {
            $meta = ($meta ?? []) + [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ];
        }

        $body = ['ok' => true, 'data' => $data];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }

        return new JsonResponse($body, $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $fields
     * @param  array<string, string>  $headers
     */
    public static function error(string $code, string $message, int $status, ?array $fields = null, array $headers = []): JsonResponse
    {
        $error = ['code' => $code, 'message' => $message];

        if ($fields !== null) {
            $error['fields'] = $fields;
        }

        return new JsonResponse(['ok' => false, 'error' => $error], $status, $headers);
    }

    /**
     * Exception → envelope. Wired for api/* in bootstrap/app.php; reporting
     * still happens through the normal handler, this only shapes the body.
     */
    public static function fromThrowable(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::error('validation_failed', $e->getMessage(), 422, $e->errors()),
            $e instanceof AuthenticationException => self::error('unauthenticated', $e->getMessage(), 401),
            $e instanceof AuthorizationException => self::error('forbidden', $e->getMessage(), 403),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => self::error('not_found', __('Not found.'), 404),
            $e instanceof ThrottleRequestsException => self::error('throttled', __('Too many requests. Try again shortly.'), 429, null, $e->getHeaders()),
            $e instanceof CheckoutException => self::error('checkout_failed', $e->getMessage(), 409),
            $e instanceof HttpExceptionInterface => self::error(
                $e->getStatusCode() === 403 ? 'forbidden' : 'http_error',
                $e->getMessage() !== '' ? $e->getMessage() : __('Request failed.'),
                $e->getStatusCode(),
                null,
                $e->getHeaders(),
            ),
            default => self::error('server_error', config('app.debug') ? $e->getMessage() : __('Something went wrong on our side.'), 500),
        };
    }
}
