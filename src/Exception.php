<?php

declare(strict_types=1);

namespace Keboola\DeveloperPortal;

use Exception as BaseException;
use Throwable;

final class Exception extends BaseException
{
    public static function authError(string $uri, array $response, ?string $message = null): Exception
    {
        $error = "Auth error when calling uri $uri.";
        if ($message) {
            $error .= " $message. ";
        }
        $error .= ' Response: ' . json_encode($response);
        return new static($error, 401);
    }

    public static function error(
        string $uri,
        array $message,
        int $code = 0,
        ?Throwable $previous = null,
    ): Exception {
        return new static("Error when calling uri $uri. Response: " . json_encode($message), $code, $previous);
    }

    public static function userError(string $message): Exception
    {
        return new static($message, 400);
    }
}
