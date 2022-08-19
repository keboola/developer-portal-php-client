<?php

declare(strict_types=1);

namespace Keboola\DeveloperPortal\Test;

use Keboola\DeveloperPortal\Client;
use Keboola\DeveloperPortal\Exception as ClientException;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    public function testLoginBadUsername(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $this->expectExceptionCode(403);
        $this->expectException(ClientException::class);
        $client->login(uniqid().'@'.uniqid().'.com', (string) getenv('KBDP_PASSWORD'));
    }

    public function testLogin(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $response = $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        self::assertNotEmpty($response);
    }

    public function testRefreshToken(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $token = $client->refreshToken();
        self::assertNotEmpty($token);
    }

    public function testEmptyEmail(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $this->expectExceptionMessage('email must be a string');
        $this->expectExceptionCode(422);
        $this->expectException(ClientException::class);
        $client->login('', (string) getenv('KBDP_PASSWORD'));
    }

    public function testEmptyUrl(): void
    {
        $client = new Client('');
        $this->expectExceptionMessage('The provided API endpoint URL "" is invalid.');
        $this->expectExceptionCode(400);
        $this->expectException(ClientException::class);
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
    }

    public function testSetCredentials(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $tokens = $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));

        $client2 = new Client((string) getenv('KBDP_API_URL'), ['credentials' => $tokens]);
        $apps = $client2->adminListApps();
        self::assertNotEmpty($apps);
    }

    public function testAdminListApps(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $res = $client->adminListApps();
        self::assertNotEmpty($res);
        self::assertArrayHasKey('id', $res[0]);
        self::assertArrayHasKey('name', $res[0]);
        self::assertArrayHasKey('type', $res[0]);
    }

    public function testAdminGetApp(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));

        $resApps = $client->adminListAppsPaginated(null, 0, 1);
        self::assertNotEmpty($resApps);
        self::assertArrayHasKey('id', $resApps[0]);

        $res = $client->adminGetApp($resApps[0]['id']);
        self::assertNotEmpty($res);
        self::assertArrayHasKey('id', $res);
        self::assertArrayHasKey('icon', $res);

        $res = $client->adminGetApp($resApps[0]['id'], ['published' => true]);
        self::assertNotEmpty($res);
        self::assertArrayHasKey('id', $res);
        self::assertArrayHasKey('icon', $res);
    }

    public function testPublicListVendors(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $res = $client->publicListVendorsPaginated();
        self::assertNotEmpty($res);
        self::assertArrayHasKey('id', $res[0]);
        self::assertArrayHasKey('name', $res[0]);
        self::assertArrayHasKey('address', $res[0]);
        self::assertArrayHasKey('email', $res[0]);
    }

    public function testListVendorsApps(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $res = $client->listVendorsAppsPaginated((string) getenv('KBDP_VENDOR'));
        self::assertNotEmpty($res);
        self::assertArrayHasKey('id', $res[0]);
        self::assertArrayHasKey('version', $res[0]);
        self::assertArrayHasKey('name', $res[0]);
        self::assertArrayHasKey('type', $res[0]);
        self::assertArrayHasKey('createdOn', $res[0]);
        self::assertArrayHasKey('createdBy', $res[0]);
        self::assertArrayHasKey('isPublic', $res[0]);
    }

    public function testListVendorsAppsBadVendor(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $this->expectExceptionMessage('Vendor badvendor does not exist');
        $this->expectExceptionCode(404);
        $this->expectException(ClientException::class);
        $client->listVendorsAppsPaginated('badvendor');
    }

    public function testPublicGetAppDetail(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $apps = $client->listVendorsAppsPaginated((string) getenv('KBDP_VENDOR'));
        $res = $client->publicGetAppDetail($apps[0]['id']);
        self::assertArrayHasKey('id', $res);
        self::assertArrayHasKey('name', $res);
        self::assertArrayHasKey('version', $res);
        self::assertArrayHasKey('type', $res);
        self::assertArrayHasKey('shortDescription', $res);
        self::assertArrayHasKey('longDescription', $res);
        self::assertArrayHasKey('licenseUrl', $res);
        self::assertArrayHasKey('documentationUrl', $res);
        self::assertArrayHasKey('requiredMemory', $res);
        self::assertArrayHasKey('processTimeout', $res);
        self::assertArrayHasKey('encryption', $res);
        self::assertArrayHasKey('network', $res);
        self::assertArrayHasKey('defaultBucket', $res);
        self::assertArrayHasKey('defaultBucketStage', $res);
        self::assertArrayHasKey('forwardToken', $res);
        self::assertArrayHasKey('forwardTokenDetails', $res);
        self::assertArrayHasKey('injectEnvironment', $res);
        self::assertArrayHasKey('uiOptions', $res);
        self::assertArrayHasKey('imageParameters', $res);
        self::assertArrayHasKey('testConfiguration', $res);
        self::assertArrayHasKey('configurationSchema', $res);
        self::assertArrayHasKey('configurationDescription', $res);
        self::assertArrayHasKey('configurationFormat', $res);
        self::assertArrayHasKey('emptyConfiguration', $res);
        self::assertArrayHasKey('actions', $res);
        self::assertArrayHasKey('fees', $res);
        self::assertArrayHasKey('limits', $res);
        self::assertArrayHasKey('logger', $res);
        self::assertArrayHasKey('loggerConfiguration', $res);
        self::assertArrayHasKey('stagingStorageInput', $res);
        self::assertArrayHasKey('isPublic', $res);
        self::assertArrayHasKey('uri', $res);
        self::assertArrayHasKey('vendor', $res);
        self::assertArrayHasKey('id', $res['vendor']);
        self::assertArrayHasKey('name', $res['vendor']);
        self::assertArrayHasKey('address', $res['vendor']);
        self::assertArrayHasKey('email', $res['vendor']);
        self::assertArrayHasKey('repository', $res);
        self::assertArrayHasKey('type', $res['repository']);
        self::assertArrayHasKey('uri', $res['repository']);
        self::assertArrayHasKey('tag', $res['repository']);
        self::assertArrayHasKey('options', $res['repository']);
        self::assertArrayHasKey('icon', $res);
        self::assertArrayHasKey('32', $res['icon']);
        self::assertArrayHasKey('64', $res['icon']);
    }

    public function testGetAppDetail(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $apps = $client->listVendorsAppsPaginated((string) getenv('KBDP_VENDOR'));
        $res = $client->getAppDetail((string) getenv('KBDP_VENDOR'), $apps[0]['id']);
        self::assertArrayHasKey('id', $res);
        self::assertArrayHasKey('name', $res);
        self::assertArrayHasKey('version', $res);
    }

    public function testGetAppRepository(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));
        $apps = $client->listVendorsAppsPaginated((string) getenv('KBDP_VENDOR'));
        $res = $client->getAppRepository((string) getenv('KBDP_VENDOR'), $apps[0]['id']);

        self::assertArrayHasKey('registry', $res);
        self::assertArrayHasKey('repository', $res);
        self::assertArrayHasKey('credentials', $res);
        self::assertArrayHasKey('username', $res['credentials']);
        self::assertArrayHasKey('password', $res['credentials']);
    }

    public function testUpdateApp(): void
    {
        $client = new Client((string) getenv('KBDP_API_URL'));
        $client->login((string) getenv('KBDP_USERNAME'), (string) getenv('KBDP_PASSWORD'));

        $apps = $client->listVendorsAppsPaginated((string) getenv('KBDP_VENDOR'));
        $app = $client->getAppDetail((string) getenv('KBDP_VENDOR'), $apps[0]['id']);

        $randomTag = rand(0, 10) . 'DeveloperPortal' . rand(0, 10) . '.' . rand(0, 10);

        $payload = [
            'repository' => [
                'type' => $app['repository']['type'],
                'uri' => $app['repository']['uri'],
                'tag' => $randomTag,
            ],
        ];
        $client->updateApp((string) getenv('KBDP_VENDOR'), $apps[0]['id'], $payload);

        $updatedApp = $client->getAppDetail((string) getenv('KBDP_VENDOR'), $apps[0]['id']);

        // assert changed values
        self::assertEquals($app['repository']['type'], $updatedApp['repository']['type']);
        self::assertEquals($app['repository']['uri'], $updatedApp['repository']['uri']);
        self::assertEquals($randomTag, $updatedApp['repository']['tag']);
        self::assertEquals([], $updatedApp['repository']['options']);
        self::assertEquals($app['version'] + 1, $updatedApp['version']);
        // assert values which should remain same
        $keysToRemove = ['repository', 'version', 'createdOn', 'updatedOn', 'publishedVersion', 'updatedBy'];
        self::assertEquals(
            fn() => array_diff_key($app, array_flip($keysToRemove)),
            fn() => array_diff_key($updatedApp, array_flip($keysToRemove))
        );
    }
}
