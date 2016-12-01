<?php
/**
 * @package developer-portal-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\DeveloperPortal;

class Exception extends \Exception
{
    public static function authError($uri, $response, $message = null)
    {
        $error = "Auth error when calling uri $uri.";
        if ($message) {
            $error .= " $message. ";
        }
        $error .= " Response: " . json_encode($response);
        return new static($error, 401);
    }

    public static function error($uri, $message, $code = 0, \Exception $previous = null)
    {
        return new static("Error when calling uri $uri. Response: " . json_encode($message), $code, $previous);
    }

    public static function userError($message)
    {
        return new static($message, 400);
    }
}
