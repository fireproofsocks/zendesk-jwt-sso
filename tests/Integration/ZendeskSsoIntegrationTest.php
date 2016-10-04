<?php

namespace ZendeskSsoTest\Integration;

use Firebase\JWT\JWT;
// Alternatively, you can use an alias, e.g. as ZendeskAPI
use Zendesk\API\HttpClient;
use ZendeskSso\ZendeskSso;
use ZendeskSsoTest\TestCase;

class ZendeskSsoIntegrationTest extends TestCase
{
    /**
     * Overrides possible for testing
     * @param JWT|null $jwt
     * @return ZendeskSso
     */
    protected function getInstance(JWT $jwt = null)
    {
        $jwt = ($jwt) ? $jwt : new JWT();
        return new ZendeskSso(getenv('ZENDESK_SUBDOMAIN'), getenv('ZENDESK_SHARED_SECRET'), $jwt);
    }
    
    /**
     * @return HttpClient ZendeskAPI client
     */
    protected function getZendeskApiClient()
    {
        $subdomain = getenv('ZENDESK_SUBDOMAIN');
        $username  = getenv('ZENDESK_API_USER');
        $token     = getenv('ZENDESK_API_TOKEN');
    
        $client = new HttpClient ($subdomain);
        $client->setAuth('basic', ['username' => $username, 'token' => $token]);
        return $client;
    }
    
    /**
     * Delete the test user we created
     * @return bool
     */
    protected function deleteTestUser()
    {
        $api = $this->getZendeskApiClient();
        $response = $api->users()->search(['query' => getenv('ZENDESK_TEST_USER_EMAIL')]);
        
        if (isset($response->users[0])) {
            $user = $response->users[0];
            return $api->users()->delete($user->id);
        }
        return false;
    }
    
    public function testEnvVarsSetInPhpUnitXmlForIntegrationTesting()
    {
        $this->assertNotEmpty(getenv('ZENDESK_SUBDOMAIN'), 'The ZENDESK_SUBDOMAIN env variable must be set in phpunit.xml');
        $this->assertNotEmpty(getenv('ZENDESK_SHARED_SECRET'), 'The ZENDESK_SHARED_SECRET env variable must be set in phpunit.xml');
        $this->assertNotEmpty(getenv('ZENDESK_API_TOKEN'), 'The ZENDESK_API_TOKEN env variable must be set in phpunit.xml');
        $this->assertNotEmpty(getenv('ZENDESK_API_USER'), 'The ZENDESK_API_USER env variable must be set in phpunit.xml');
        $this->assertNotEmpty(getenv('ZENDESK_TEST_USER_NAME'), 'The ZENDESK_TEST_USER_NAME env variable must be set in phpunit.xml');
        $this->assertNotEmpty(getenv('ZENDESK_TEST_USER_EMAIL'), 'The ZENDESK_TEST_USER_EMAIL env variable must be set in phpunit.xml');
        $this->assertNotEmpty(getenv('ZENDESK_TEST_USER_EXTERNAL_ID'), 'The ZENDESK_TEST_USER_EXTERNAL_ID env variable must be set in phpunit.xml');
    }
    
    public function testInstantiation()
    {
        $z = $this->getInstance();
        $this->assertInstanceOf(ZendeskSso::class, $z);
    }
    
    public function testZendeskApiClientInstantiation()
    {
        $api = $this->getZendeskApiClient();
        $this->assertInstanceOf(HttpClient::class, $api);
    }
    
    public function testZendeskApiClientCredentials()
    {
        $api = $this->getZendeskApiClient();
        $response = $api->users()->search(['query' => getenv('ZENDESK_TEST_USER_EMAIL')]);
        $this->assertTrue(is_object($response));
    }
    
    /**
     * The response appears identical for a first time login and a login for an existing account
     */
    public function testSuccessfulSignOn()
    {
        $z = $this->getInstance();
        $url = $z->getUrl(getenv('ZENDESK_TEST_USER_NAME'), getenv('ZENDESK_TEST_USER_EMAIL'));
        $headers = get_headers($url, 1);
        
        // These might change if their redirect process adds or removes layers
        $this->assertNotEmpty($headers[0]);
        $this->assertEquals('HTTP/1.1 302 Found', $headers[0]);
        
        // If you have a custom support domain e.g. help.yourdomain.com, then the exact redirects may be different.
        // e.g. you may have a HTTP/1.1 301 Moved Permanently in here.
        // So we want to focus on whether we got to a 200 eventually
        $got_200_eventually = false;
        for ($i = 1; $i <= 10; $i++) {
            if (isset($headers[$i]) && $headers[$i] == 'HTTP/1.1 200 OK') {
                $got_200_eventually = true;
                break;
            }
        }
        
        $this->assertTrue($got_200_eventually);
        
        $this->assertTrue(array_key_exists('X-Zendesk-Request-Id', $headers));
        $this->assertTrue(array_key_exists('X-Zendesk-Origin-Server', $headers));
        $this->assertTrue(array_key_exists('Set-Cookie', $headers));
        
        // Loop through the cookies to find the following:
        $expected_cookies = [
            '_zendesk_shared_session' => false,
            '_zendesk_authenticated=1' => false,
            '_zendesk_cookie' => false,
            '_zendesk_session' => false
        ];
        foreach ($headers['Set-Cookie'] as $c) {
            foreach ($expected_cookies as $cookie_name => $found_flag) {
                if (strpos($c, $cookie_name) === 0) {
                    $expected_cookies[$cookie_name] = true;
                }
            }
        }
        
        foreach ($expected_cookies as $cookie_name => $found_flag) {
            $this->assertTrue($found_flag, 'The "'.$cookie_name.'" cookie was not set.');
        }
        
        // Cleanup
        $response = $this->deleteTestUser();
        $this->assertEquals(getenv('ZENDESK_TEST_USER_EMAIL'), $response->user->email);
        
    }
    
    public function testSignOnFailsWith404WhenSubdomainIsBad()
    {
        $z = new ZendeskSso(getenv('ZENDESK_SUBDOMAIN').'gunk', getenv('ZENDESK_SHARED_SECRET'));
        $url = $z->getUrl(getenv('ZENDESK_TEST_USER_NAME'), getenv('ZENDESK_TEST_USER_EMAIL'));
        $headers = get_headers($url, 1);
        $this->assertEquals('HTTP/1.1 404 Not Found', $headers[0]);
    }
    
    public function testSignOnFailsSecretIsBad()
    {
        $z = new ZendeskSso(getenv('ZENDESK_SUBDOMAIN'), 'this-is-not-your-secret');
        $url = $z->getUrl(getenv('ZENDESK_TEST_USER_NAME'), getenv('ZENDESK_TEST_USER_EMAIL'));
        
        // This may redirect to your logout url, but the cookie value will
        // be _zendesk_authenticated=; instead of _zendesk_authenticated=1
        $headers = get_headers($url, 1);
    
        $expected_cookies = [
            '_zendesk_authenticated=1' => false,
        ];
        foreach ($headers['Set-Cookie'] as $c) {
            foreach ($expected_cookies as $cookie_name => $found_flag) {
                if (strpos($c, $cookie_name) === 0) {
                    $expected_cookies[$cookie_name] = true;
                }
            }
        }
        foreach ($expected_cookies as $cookie_name => $found_flag) {
            $this->assertFalse($found_flag, 'The "'.$cookie_name.'" cookie should not be set for a failed login.');
        }
        
    }
    
    public function testSignOnIncludingExternalId()
    {
        $z = $this->getInstance();
        $url = $z->getUrl(getenv('ZENDESK_TEST_USER_NAME'), getenv('ZENDESK_TEST_USER_EMAIL'), null, ['external_id' => getenv('ZENDESK_TEST_USER_EXTERNAL_ID')]);
        
        file_get_contents($url); // this issues a request on the URL and creates the user
    
        $api = $this->getZendeskApiClient();
    
        $response = $api->users()->search(['external_id' => getenv('ZENDESK_TEST_USER_EXTERNAL_ID')]);
        $this->assertNotEmpty($response);
        $this->assertTrue(is_array($response->users));
        $this->assertTrue(isset($response->users[0]));
        $this->assertEquals(1, count($response->users));
        $user = $response->users[0];
        $this->assertEquals(getenv('ZENDESK_TEST_USER_EMAIL'), $user->email);
    
        // Cleanup
        $response = $this->deleteTestUser();
        $this->assertEquals(getenv('ZENDESK_TEST_USER_EMAIL'), $response->user->email);

    }
}