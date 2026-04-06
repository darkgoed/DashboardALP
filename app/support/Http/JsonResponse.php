<?php

class JsonResponse
{
    public static function send(array $payload, int $statusCode = 200): void
    {
        if (ob_get_length() > 0) {
            ob_clean();
        }

        http_response_code($statusCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
