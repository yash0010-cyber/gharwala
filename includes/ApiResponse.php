<?php
/**
 * API Response Handler
 * Formats API responses with proper headers and status codes
 */

class ApiResponse {
    /**
     * Send success response
     */
    public static function success($data = null, string $message = 'Success', int $status = 200): void {
        self::sendResponse([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Send error response
     */
    public static function error(string $message = 'Error', int $status = 400, $data = null): void {
        self::sendResponse([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Send validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void {
        self::sendResponse([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], 422);
    }

    /**
     * Send not found response
     */
    public static function notFound(string $message = 'Resource not found'): void {
        self::sendResponse([
            'success' => false,
            'message' => $message,
        ], 404);
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::sendResponse([
            'success' => false,
            'message' => $message,
        ], 401);
    }

    /**
     * Send forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): void {
        self::sendResponse([
            'success' => false,
            'message' => $message,
        ], 403);
    }

    /**
     * Send paginated response
     */
    public static function paginated(array $data, int $page, int $perPage, int $total): void {
        self::sendResponse([
            'success' => true,
            'message' => 'Success',
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => ceil($total / $perPage),
            ],
        ], 200);
    }

    /**
     * Send response
     */
    private static function sendResponse(array $response, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
