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
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testLogin()
    {
        $client = new Client(KBDP_API_URL);
        $response = $client->login(KBDP_USERNAME, KBDP_PASSWORD);
        $this->assertNotEmpty($response);
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

    public function testAdminGeApp()
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
}
