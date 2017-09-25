<?php
/**
 * @package developer-portal-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\DeveloperPortal;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

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
    private $guzzle;
    private $guzzleOptions;

    private $username;
    private $password;

    private $token;
    private $accessToken;
    private $refreshToken;

    /**
     * Client constructor
     *
     * $url - API endpoint
     * $options - additional options:
     *
     *      [
     *          'credentials' => [
     *              'token' => DEVELOPER PORTAL API TOKEN
     *              'refreshToken => DEVELOPER PORTAL API REFRESH TOKEN
     *          ],
     *          'options' => GUZZLE HTTP CLIENT OPTIONS
     *      ]
     *
     * @param $url
     * @param array $options
     */
    public function __construct($url = 'https://apps-api.keboola.com/', array $options = [])
    {
        if (substr($url, -1) != '/') {
            $url .= '/';
        }
        $options['options']['base_uri'] = $url;
        $this->guzzleOptions = $options['options'];
        $this->initClient();
        if (!empty($options['credentials'])) {
            $this->setCredentials($options['credentials']);
        }
    }

    private function setCredentials($credentials)
    {
        foreach (['token', 'accessToken', 'refreshToken'] as $key) {
            if (!empty($credentials[$key])) {
                $this->$key = $credentials[$key];
            }
        }
    }

    private function initClient()
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
     * @param $method
     * @param $uri
     * @param array $params
     * @param array $headers
     * @param int $retries
     * @return array
     * @throws Exception
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
                    $this->refreshToken();
                    return $this->request($method, $uri, $params, $headers, $retries-1);
                }

                throw Exception::error($uri, $responseBody, $response->getStatusCode(), $e);
            }

            throw $e;
        }
    }

    /**
     * Auth
     */

    /**
     * @param $username
     * @param $password
     * @return mixed
     * @throws Exception
     */
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
        $this->accessToken = $response['accessToken'];
        $this->refreshToken = $response['accessToken'];

        return $response;
    }

    /**
     * @param $method
     * @param $uri
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function authRequest($method, $uri, $params = [])
    {
        if (!$this->token) {
            throw Exception::userError('Call login for auth requests first');
        }
        return $this->request($method, $uri, $params, [
            'Authorization' => $this->token
        ]);
    }

    public function refreshToken()
    {
        $token = $this->request('GET', 'auth/token', [], [
            'Authorization' => $this->refreshToken
        ]);

        $this->token = $token['token'];
        return $token['token'];
    }

    /**
     * Admin API
     */

    /**
     * @param null $filter
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function adminListAppsPaginated($filter = null, $offset = 0, $limit = 1000)
    {
        return $this->authRequest('GET', 'admin/apps', [
            'filter' => $filter,
            'offset' => $offset,
            'limit' => $limit
        ]);
    }

    /**
     * @param null $filter
     * @return array
     */
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

    /**
     * @param $id
     * @return array
     */
    public function adminGetApp($id)
    {
        return $this->authRequest('GET', 'admin/apps/' . $id);
    }

    /**
     * Vendors
     */

    /**
     * @param $vendor
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function listVendorsAppsPaginated($vendor, $offset = 0, $limit = 1000)
    {
        return $this->authRequest('GET', sprintf('vendors/%s/apps', $vendor), [
            'offset' => $offset,
            'limit' => $limit
        ]);
    }

    /**
     * Apps
     */

    /**
     * Pass attributes of the new app in $params array:
     *
     *      [
     *         'id'     => 'ex-adwords',
     *         'name'   => 'AdWords Reports',
     *         'type'   => 'extractor',
     *          ...
     *      ]
     *
     * @param $vendor
     * @param $params
     * @return array
     */
    public function createApp($vendor, $params)
    {
        return $this->authRequest('POST', sprintf('vendors/%s/apps', $vendor), $params);
    }

    /**
     * Get Apps ECR repository credentials
     *
     * @param $vendor
     * @param $app
     * @return array
     */
    public function getAppRepository($vendor, $app)
    {
        return $this->authRequest('GET', sprintf('vendors/%s/apps/%s/repository', $vendor, $app));
    }

    /**
     * Pass attributes of the app in $params array:
     *
     *      [
     *         'repository' => [
     *              'type' => 'ecr',
     *              'uri'  => 'my.repo.uri',
     *              'tag'  => '1.23.0',
     *              'options' => []
     *          ]
     *          ...
     *      ]
     *
     * @param $vendor
     * @param $id
     * @param $params
     * @return array
     */
    public function updateApp($vendor, $id, $params)
    {
        return $this->authRequest('PATCH', sprintf('vendors/%s/apps/%s', $vendor, $id), $params);
    }

    /**
     * Public API
     */

    /**
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function publicListVendorsPaginated($offset = 0, $limit = 1000)
    {
        return $this->request('GET', 'vendors', [
            'offset' => $offset,
            'limit' => $limit
        ]);
    }

    /**
     * @param $appId
     * @return array
     */
    public function publicGetAppDetail($appId)
    {
        return $this->request('GET', sprintf('apps/%s', $appId));
    }
}
