<?php

declare(strict_types=1);

namespace Keboola\DeveloperPortal;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class Client
{
    /**
     * Number of retries for one API call
     */
    private const RETRIES_COUNT = 5;

    private const DEFAULT_CLIENT_SETTINGS = [
        'timeout' => 600,
        'headers' => [
            'accept' => 'application/json',
            'content-type' => 'application/json; charset=utf-8',
        ],
    ];

    private GuzzleClient $guzzle;
    private array $guzzleOptions;

    private string $username;
    private string $password;

    private string $token;
    private string $accessToken;
    private string $refreshToken;

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
     * @param string $url
     * @param array $options
     * @throws Exception
     */
    public function __construct(string $url = 'https://apps-api.keboola.com/', array $options = [])
    {
        if (empty(trim($url))) {
            throw Exception::userError(sprintf('The provided API endpoint URL "%s" is invalid.', $url));
        }
        if (substr($url, -1) !== '/') {
            $url .= '/';
        }
        $options['options']['base_uri'] = $url;
        $this->guzzleOptions = $options['options'];
        $this->initClient();
        if (!empty($options['credentials'])) {
            $this->setCredentials($options['credentials']);
        }
    }

    /**
     * @param array{token: string, accessToken: string, refreshToken:string} $credentials
     */
    private function setCredentials(array $credentials): void
    {
        foreach (['token', 'accessToken', 'refreshToken'] as $key) {
            if (!empty($credentials[$key])) {
                $this->$key = $credentials[$key];
            }
        }
    }

    private function initClient(): void
    {
        $handlerStack = HandlerStack::create();

        $handlerStack->push(Middleware::retry(
            function (
                int $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?string $error = null
            ) {
                return $response && $response->getStatusCode() === 503;
            },
            function (int $retries) {
                return rand(60, 600) * 1000;
            },
        ));
        $handlerStack->push(Middleware::retry(
            function ($retries, RequestInterface $request, ?ResponseInterface $response = null, ?string $error = null) {
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
            },
        ));

        $this->guzzle = new GuzzleClient(array_merge([
            'handler' => $handlerStack,
            'cookies' => true,
        ], $this->guzzleOptions));
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function request(
        string $method,
        string $uri,
        array $params = [],
        array $headers = [],
        int $retries = 5
    ): array {
        $options = self::DEFAULT_CLIENT_SETTINGS;
        if ($params) {
            if ($method === 'GET' || $method === 'DELETE') {
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
            return (array) json_decode($response->getBody()->getContents(), true);
        } catch (Throwable $e) {
            $response = $e instanceof RequestException && $e->hasResponse() ? $e->getResponse() : null;
            if ($response) {
                $responseBody = (array) json_decode($response->getBody()->getContents(), true);
                if ($response->getStatusCode() === 401) {
                    if ($uri === 'auth/login') {
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

    public function login(string $username, string $password): array
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
        $this->refreshToken = $response['refreshToken'];

        return $response;
    }

    public function authRequest(string $method, string $uri, array $params = []): array
    {
        if (!$this->token) {
            throw Exception::userError('Call login for auth requests first');
        }
        return $this->request($method, $uri, $params, [
            'Authorization' => $this->token,
        ]);
    }

    public function refreshToken(): string
    {
        $token = $this->request('GET', 'auth/token', [], [
            'Authorization' => $this->refreshToken,
        ]);

        $this->token = $token['token'];
        return $token['token'];
    }

    /**
     * Admin API
     */

    public function adminListAppsPaginated(?string $filter = null, int $offset = 0, int $limit = 1000): array
    {
        return $this->authRequest('GET', 'admin/apps', [
            'filter' => $filter,
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    public function adminListApps(?string $filter = null): array
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
     * @param string $id
     * @param array{published: bool} $options
     * @return array
     * @throws Exception
     */
    public function adminGetApp(string $id, array $options = ['published' => false]): array
    {
        $uri = 'admin/apps/' . $id;
        if (!empty($options['published'])) {
            $uri .= '?published=true';
        }
        return $this->authRequest('GET', $uri);
    }

    /**
     * Vendors
     */

    public function listVendorsAppsPaginated(string $vendor, int $offset = 0, int $limit = 1000): array
    {
        return $this->authRequest('GET', sprintf('vendors/%s/apps', $vendor), [
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    public function getAppDetail(string $vendor, string $id): array
    {
        return $this->authRequest('GET', "vendors/$vendor/apps/$id");
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
    public function createApp(string $vendor, array $params): array
    {
        return $this->authRequest('POST', sprintf('vendors/%s/apps', $vendor), $params);
    }

    /**
     * Get Apps ECR repository credentials
     *
     * @param string $vendor
     * @param string $app
     * @return array
     * @throws Exception
     */
    public function getAppRepository(string $vendor, string $app): array
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
     * @param string $vendor
     * @param string $id
     * @param array $params
     * @return array
     * @throws Exception
     */
    public function updateApp(string $vendor, string $id, array $params): array
    {
        return $this->authRequest('PATCH', sprintf('vendors/%s/apps/%s', $vendor, $id), $params);
    }

    /**
     * Public API
     */

    public function publicListVendorsPaginated(int $offset = 0, int $limit = 1000): array
    {
        return $this->request('GET', 'vendors', [
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    public function publicGetAppDetail(string $appId): array
    {
        return $this->request('GET', sprintf('apps/%s', $appId));
    }
}
