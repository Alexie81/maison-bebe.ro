<?php
declare(strict_types=1);

namespace MaisonBebe\Core;

final class Response
{
    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function redirect(string $location, int $status = 302): never
    {
        header('Location: ' . url($location), true, $status);
        exit;
    }

    public static function xml(string $xml, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/xml; charset=utf-8');
        echo $xml;
        exit;
    }
}

