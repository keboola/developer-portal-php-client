<?php
/**
 * @package developer-portal-php-client
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\DeveloperPortal\Test;

use Keboola\DeveloperPortal\Client;
use Keboola\DeveloperPortal\Exception;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testLoginBadUsername()
    {
        $client = new Client(KBDP_API_URL);
        try {
            $client->login(uniqid().'@'.uniqid().'.com', KBDP_PASSWORD);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(403, $e->getCode());
        }
    }

    public function testLogin()
    {
        $client = new Client(KBDP_API_URL);
        $response = $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $this->assertNotEmpty($response);
    }

    public function testSetCredentials()
    {
        $client = new Client(KBDP_API_URL);
        $tokens = $client->login(KBDP_USERNAME, KBDP_PASSWORD);

        $client2 = new Client(KBDP_API_URL, ['credentials' => $tokens]);
        $apps = $client2->adminListApps();
        $this->assertNotEmpty($apps);
    }

    public function testAdminListApps()
    {
        $client = new Client(KBDP_API_URL);
        $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $res = $client->adminListApps();
        $this->assertNotEmpty($res);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertArrayHasKey('name', $res[0]);
        $this->assertArrayHasKey('type', $res[0]);
    }

    public function testAdminGetApp()
    {
        $client = new Client(KBDP_API_URL);
        $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $res = $client->adminListAppsPaginated(null, 0, 1);
        $this->assertNotEmpty($res);
        $this->assertArrayHasKey('id', $res[0]);
        $res = $client->adminGetApp($res[0]['id']);
        $this->assertNotEmpty($res);
        $this->assertArrayHasKey('id', $res);
        $this->assertArrayHasKey('icon', $res);
    }

    public function testPublicListVendors()
    {
        $client = new Client(KBDP_API_URL);
        $res = $client->publicListVendorsPaginated();
        $this->assertNotEmpty($res);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertArrayHasKey('name', $res[0]);
        $this->assertArrayHasKey('address', $res[0]);
        $this->assertArrayHasKey('email', $res[0]);
    }

    public function testListVendorsApps()
    {
        $client = new Client(KBDP_API_URL);
        $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $res = $client->listVendorsAppsPaginated(KBDP_VENDOR);
        $this->assertNotEmpty($res);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertArrayHasKey('version', $res[0]);
        $this->assertArrayHasKey('name', $res[0]);
        $this->assertArrayHasKey('type', $res[0]);
        $this->assertArrayHasKey('createdOn', $res[0]);
        $this->assertArrayHasKey('createdBy', $res[0]);
        $this->assertArrayHasKey('isPublic', $res[0]);
    }

    public function testPublicGetAppDetail()
    {
        $client = new Client(KBDP_API_URL);
        $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $apps = $client->listVendorsAppsPaginated(KBDP_VENDOR);
        $res = $client->publicGetAppDetail($apps[0]['id']);
        $this->assertArrayHasKey('id', $res);
        $this->assertArrayHasKey('name', $res);
        $this->assertArrayHasKey('version', $res);
        $this->assertArrayHasKey('type', $res);
        $this->assertArrayHasKey('shortDescription', $res);
        $this->assertArrayHasKey('longDescription', $res);
        $this->assertArrayHasKey('licenseUrl', $res);
        $this->assertArrayHasKey('documentationUrl', $res);
        $this->assertArrayHasKey('requiredMemory', $res);
        $this->assertArrayHasKey('processTimeout', $res);
        $this->assertArrayHasKey('encryption', $res);
        $this->assertArrayHasKey('network', $res);
        $this->assertArrayHasKey('defaultBucket', $res);
        $this->assertArrayHasKey('defaultBucketStage', $res);
        $this->assertArrayHasKey('forwardToken', $res);
        $this->assertArrayHasKey('forwardTokenDetails', $res);
        $this->assertArrayHasKey('injectEnvironment', $res);
        $this->assertArrayHasKey('cpuShares', $res);
        $this->assertArrayHasKey('uiOptions', $res);
        $this->assertArrayHasKey('imageParameters', $res);
        $this->assertArrayHasKey('testConfiguration', $res);
        $this->assertArrayHasKey('configurationSchema', $res);
        $this->assertArrayHasKey('configurationDescription', $res);
        $this->assertArrayHasKey('configurationFormat', $res);
        $this->assertArrayHasKey('emptyConfiguration', $res);
        $this->assertArrayHasKey('actions', $res);
        $this->assertArrayHasKey('fees', $res);
        $this->assertArrayHasKey('limits', $res);
        $this->assertArrayHasKey('logger', $res);
        $this->assertArrayHasKey('loggerConfiguration', $res);
        $this->assertArrayHasKey('stagingStorageInput', $res);
        $this->assertArrayHasKey('isPublic', $res);
        $this->assertArrayHasKey('uri', $res);
        $this->assertArrayHasKey('vendor', $res);
        $this->assertArrayHasKey('id', $res['vendor']);
        $this->assertArrayHasKey('name', $res['vendor']);
        $this->assertArrayHasKey('address', $res['vendor']);
        $this->assertArrayHasKey('email', $res['vendor']);
        $this->assertArrayHasKey('repository', $res);
        $this->assertArrayHasKey('type', $res['repository']);
        $this->assertArrayHasKey('uri', $res['repository']);
        $this->assertArrayHasKey('tag', $res['repository']);
        $this->assertArrayHasKey('options', $res['repository']);
        $this->assertArrayHasKey('icon', $res);
        $this->assertArrayHasKey('32', $res['icon']);
        $this->assertArrayHasKey('64', $res['icon']);
    }

    public function testGetAppRepository()
    {
        $client = new Client(KBDP_API_URL);
        $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $apps = $client->listVendorsAppsPaginated(KBDP_VENDOR);
        $res = $client->getAppRepository(KBDP_VENDOR, $apps[0]['id']);

        $this->assertArrayHasKey('registry', $res);
        $this->assertArrayHasKey('repository', $res);
        $this->assertArrayHasKey('credentials', $res);
        $this->assertArrayHasKey('username', $res['credentials']);
        $this->assertArrayHasKey('password', $res['credentials']);
    }

    public function testUpdateApp()
    {
        $client = new Client(KBDP_API_URL);
        $client->login(KBDP_USERNAME, KBDP_PASSWORD);

        $apps = $client->listVendorsAppsPaginated(KBDP_VENDOR);
        $app = $client->publicGetAppDetail($apps[0]['id']);

        $randomTag = rand(0, 10) . "." . rand(0, 10) . "." . rand(0, 10);

        $payload = [
            "repository" => [
                "type" => $app['repository']['type'],
                "uri" => $app['repository']['uri'],
                "tag" => $randomTag
            ]
        ];
        $client->updateApp(KBDP_VENDOR, $apps[0]['id'], $payload);

        $updatedApp = $client->publicGetAppDetail($apps[0]['id']);

        // assert changed values
        $this->assertEquals($app['repository']['type'], $updatedApp['repository']['type']);
        $this->assertEquals($app['repository']['uri'], $updatedApp['repository']['uri']);
        $this->assertEquals($randomTag, $updatedApp['repository']['tag']);
        $this->assertEquals([], $updatedApp['repository']['options']);
        $this->assertEquals($app['version'] + 1, $updatedApp['version']);
        // assert values which should remain same
        function removeKeys($array, $keys)
        {
            return array_diff_key($array, array_flip($keys));
        }
        $keysToRemove = ['repository', 'version'];
        $this->assertEquals(removeKeys($app, $keysToRemove), removeKeys($updatedApp, $keysToRemove));
    }
}
