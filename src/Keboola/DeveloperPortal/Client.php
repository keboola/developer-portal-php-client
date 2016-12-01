<?php
/**
 * @package developer-portal-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\DeveloperPortal;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Client
{
    /**
     * Number of retries for one API call
     */
    const RETRIES_COUNT = 5;
    /**
     * Back off time before retrying API call
     */
    const BACKOFF_INTERVAL = 10;

    const DEFAULT_CLIENT_SETTINGS = [
        'timeout' => 600,
        'headers' => [
            'accept' => 'application/json',
            'content-type' => 'application/json; charset=utf-8'
        ]
    ];

    /** @var  \GuzzleHttp\Client */
    protected $guzzle;
    protected $guzzleOptions;

    protected $username;
    protected $password;
    protected $token;

    public function __construct($url, array $options = [])
    {
        $options['base_uri'] = $url;
        $this->guzzleOptions = $options;
        $this->initClient();
    }

    protected function initClient()
    {
        $handlerStack = HandlerStack::create();

        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                return $response && $response->getStatusCode() == 503;
            },
            function ($retries) {
                return rand(60, 600) * 1000;
            }
        ));
        /** @noinspection PhpUnusedParameterInspection */
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ResponseInterface $response = null, $error = null) {
                if ($retries >= self::RETRIES_COUNT) {
                    return false;
                } elseif ($response && $response->getStatusCode() > 499) {
                    return true;
                } elseif ($error) {
                    return true;
                } else {
                    return false;
                }
            },
            function ($retries) {
                return (int) pow(2, $retries - 1) * 1000;
            }
        ));

        $this->guzzle = new \GuzzleHttp\Client(array_merge([
            'handler' => $handlerStack,
            'cookies' => true
        ], $this->guzzleOptions));
    }

    public function getUsername()
    {
        return $this->username;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return ResponseInterface
     */
    public function request($method, $uri, $params = [], $headers = [], $retries = 5)
    {
        $options = self::DEFAULT_CLIENT_SETTINGS;
        if ($params) {
            if ($method == 'GET' || $method == 'DELETE') {
                $options['query'] = $params;
            } else {
                $options['json'] = $params;
            }
        }

        if ($headers) {
            $options['headers'] = $headers;
        }

        try {
            $response = $this->guzzle->request($method, $uri, $options);
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $response = $e instanceof RequestException && $e->hasResponse() ? $e->getResponse() : null;
            if ($response) {
                $responseBody = json_decode($response->getBody(), true);
                if ($response->getStatusCode() == 401) {
                    if ($uri == 'auth/login') {
                        throw Exception::authError($uri, $responseBody);
                    }
                    if ($retries <= 0) {
                        throw $e;
                    }
                    $this->login($this->username, $this->password);
                    return $this->request($method, $uri, $params, $headers, $retries-1);
                }

                throw Exception::error($uri, $responseBody, $response->getStatusCode(), $e);
            }

            throw $e;
        }
    }

    public function login($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        $response = $this->request('POST', 'auth/login', [
            'email' => $this->username,
            'password' => $this->password,
        ]);
        if (!isset($response['token'])) {
            throw Exception::authError('auth/login', $response, 'Missing token');
        }
        $this->token = $response['token'];
        return $response['token'];
    }

    public function authRequest($method, $uri, $params = [])
    {
        if (!$this->token) {
            throw Exception::userError('Call login for auth requests first');
        }
        return $this->request($method, $uri, $params, [
            'Authorization' => $this->token
        ]);
    }

    public function adminListAppsPaginated($filter = null, $offset = 0, $limit = 1000)
    {
        return $this->authRequest('GET', 'admin/apps', [
            'filter' => $filter,
            'offset' => $offset,
            'limit' => $limit
        ]);
    }

    public function adminListApps($filter = null)
    {
        $offset = 0;
        $limit = 1000;
        $result = [];
        do {
            $partialResult = $this->adminListAppsPaginated($filter, $offset, $limit);
            $result = array_merge($result, $partialResult);
            $offset += $limit;
        } while (count($partialResult));
        return $result;
    }

    public function adminGetApp($id)
    {
        return $this->authRequest('GET', 'admin/apps/' . $id);
    }
}
