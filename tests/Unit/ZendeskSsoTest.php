<?php

namespace ZendeskSsoTest\Unit;

use ZendeskSso\ZendeskSso;
use ZendeskSsoTest\TestCase;

class ZendeskSsoTest extends TestCase
{
    protected $subdomain = 'test';
    protected $shared_secret = 'test';
    
    
    protected function getInstance()
    {
        return new ZendeskSso($this->subdomain, $this->shared_secret);
    }
    
    public function testInstantiation()
    {
        $z = $this->getInstance();
        $this->assertInstanceOf(ZendeskSso::class, $z);
    }
    
    public function testGetUrl()
    {
        $z = $this->getInstance();
        $url = $z->getUrl('Test', 'test@test.com');
        
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false);
    
        $parts = parse_url($url);
        $this->assertEquals('https', $parts['scheme']);
        $this->assertEquals('test.zendesk.com', $parts['host']);
        $this->assertEquals('/access/jwt', $parts['path']);
        $this->assertNotEmpty($parts['query']);
    
        parse_str($parts['query'], $query);
        $this->assertTrue(array_key_exists('jwt', $query));
    }
    
    public function testGetUrlWithReturnTo()
    {
        $z = $this->getInstance();
        $return_to = 'http://test.com/return';
        $url = $z->getUrl('Test', 'test@test.com', $return_to);
        
        $this->assertTrue(filter_var($url, FILTER_VALIDATE_URL) !== false);
        
        $parts = parse_url($url);
        $this->assertNotEmpty($parts['query']);
        
        parse_str($parts['query'], $query);
        $this->assertTrue(array_key_exists('return_to', $query));
        $this->assertEquals($return_to, $query['return_to']);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRawTokenThrowsExceptionIfNameIsNotScalar()
    {
        $z = $this->getInstance();
        $badName = new \stdClass();
        $this->callProtectedMethod($z, 'getRawToken', [$badName, 'testemail@test.com']);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRawTokenThrowsExceptionIfNameIsEmpty()
    {
        $z = $this->getInstance();
        $this->callProtectedMethod($z, 'getRawToken', [' ', 'testemail@test.com']);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRawTokenThrowsExceptionIfEmailIsNotScalar()
    {
        $z = $this->getInstance();
        $badEmail = new \stdClass();
        $this->callProtectedMethod($z, 'getRawToken', ['test name', $badEmail]);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRawTokenThrowsExceptionIfEmailIsEmpty()
    {
        $z = $this->getInstance();
        $this->callProtectedMethod($z, 'getRawToken', ['test', ' ']);
    }
    
    /**
     * @expectedException \InvalidArgumentException
     */
    public function testGetRawTokenThrowsExceptionIfUnsupportedOptionalFieldsAreAdded()
    {
        $z = $this->getInstance();
        $this->callProtectedMethod($z, 'getRawToken', ['test', 'testemail@test.com', ['bogus' => 'field']]);
    }
    
    public function testGetRawTokenBasicValues()
    {
        $z = $this->getInstance();
        $token = $this->callProtectedMethod($z, 'getRawToken', ['test', 'testemail@test.com']);
        $this->assertTrue(is_array($token));
        $required_fields = ['jti', 'iat', 'name', 'email'];
        foreach ($required_fields as $f) {
            $this->assertTrue(array_key_exists($f, $token));
            $this->assertNotEmpty($token[$f]);
        }
        $this->assertEquals(count($required_fields), count($token));
    }
    
    public function testGetRawTokenWithOptionalFields()
    {
        $z = $this->getInstance();
        $token = $this->callProtectedMethod($z, 'getRawToken', ['test', 'testemail@test.com', ['external_id' => 123]]);
        $this->assertTrue(is_array($token));
        $this->assertTrue(array_key_exists('external_id', $token));
        $this->assertNotEmpty($token['external_id']);
    }
    
    public function testSendHeader()
    {
        $z = $this->getInstance();
        $result = $this->callProtectedMethod($z, 'sendHeader', ['http://test.org', function($input){ return $input; }]);
        $this->assertEquals('Location: http://test.org', $result);
    }
    
    public function testTerminate()
    {
        $z = $this->getInstance();
        $result = $this->callProtectedMethod($z, 'sendHeader', ['http://test.org', function(){ return true; }]);
        $this->assertTrue($result);
    }
    
    public function testRedirectToUrl()
    {
        $z = new ZendeskSsoOverride($this->subdomain, $this->shared_secret);
        $result = $z->redirectToUrl('Test User', 'test@email.com');
        $this->assertTrue($result);
    }
}

/**
 * Class ZendeskSsoOverride
 *
 * Purpose: override functions that would make the output untestable.
 *
 * @package ZendeskSsoTest\Unit
 */
class ZendeskSsoOverride extends ZendeskSso
{
    protected function sendHeader($location, callable $function = null)
    {
        return 'Location: '. $location;
    }
    
    protected function terminate(callable $function = null)
    {
        return true;
    }
}