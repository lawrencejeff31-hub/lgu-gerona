<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * Return a success response.
     */
    public static function success($data = null, string $message = 'Success', int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => $statusCode,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $response['timestamp'] = now()->toISOString();

        return response()->json($response, $statusCode);
    }

    /**
     * Return an error response.
     */
    public static function error(string $message = 'An error occurred', int $statusCode = Response::HTTP_BAD_REQUEST, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
            'status_code' => $statusCode,
            'timestamp' => now()->toISOString(),
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Return a validation error response.
     */
    public static function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * Return an unauthorized response.
     */
    public static function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return a forbidden response.
     */
    public static function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Return a not found response.
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Return a server error response.
     */
    public static function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return self::error($message, Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Return a created response.
     */
    public static function created($data = null, string $message = 'Resource created successfully'): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Return a no content response.
     */
    public static function noContent(string $message = 'No content'): JsonResponse
    {
        return self::success(null, $message, Response::HTTP_NO_CONTENT);
    }

    /**
     * Return a paginated response.
     */
    public static function paginated($paginatedData, string $message = 'Data retrieved successfully'): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => Response::HTTP_OK,
            'data' => $paginatedData->items(),
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'last_page' => $paginatedData->lastPage(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem(),
                'has_more_pages' => $paginatedData->hasMorePages(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        return response()->json($response, Response::HTTP_OK);
    }

    /**
     * Return a collection response.
     */
    public static function collection($data, string $message = 'Data retrieved successfully', array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'status_code' => Response::HTTP_OK,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, Response::HTTP_OK);
    }
}