<?php

namespace ZendeskSso;

use InvalidArgumentException;
use Firebase\JWT\JWT;

/**
 * Class ZendeskSso
 *
 * Generate JSON Web Tokens for signing into a Zendesk account using the
 * Single Sign-On (SSO) feature.
 *
 * Zendesk allows up to two minutes clock skew, so make sure to configure NNTP or
 * similar on your servers.
 *
 * @package ZendeskSso
 */
class ZendeskSso
{
    protected $subdomain;
    protected $shared_secret;
    protected $JWT;
    
    /**
     * See https://support.zendesk.com/hc/en-us/articles/203663816-Setting-up-single-sign-on-with-JWT-JSON-Web-Token-
     * for a list of what the allowed fields are.
     * @var array
     */
    protected $optional_jwt_fields = [
        'external_id',
        'locale',
        'locale_id',
        'organization',
        'organization_id',
        'phone',
        'tags',
        'remote_photo_url',
        'role',
        'custom_role_id',
        'user_fields'
    ];
    
    /**
     * ZendeskSso constructor
     *
     * @param $subdomain string your Zendesk account identifier
     * @param $shared_secret string token from SSO
     * @param $jwt JWT optional dependency injection
     */
    public function __construct($subdomain, $shared_secret, JWT $jwt = null)
    {
        $this->subdomain = $subdomain;
        $this->shared_secret = $shared_secret;
        $this->JWT = ($jwt) ? $jwt : new JWT();
    }
    
    /**
     * Gets the SSO URL with the signed JWT attached.
     * @param string $name
     * @param string $email
     * @param string $return_to Fully qualified URL including http:// or https://. Do not urlencode the URL
     * @param $options array any additional parameters to include in the JWT
     * @return string
     */
    public function getUrl($name, $email, $return_to=null, array $options=[])
    {
        $jwt = $this->JWT->encode($this->getRawToken($name, $email, $options), $this->shared_secret);
        
        $location = 'https://' . $this->subdomain . '.zendesk.com/access/jwt?jwt=' . $jwt;
        
        if ($return_to) {
            $location .= "&return_to=" . urlencode($return_to);
        }
        
        return $location;

    }
    
    /**
     * Gets the SSO URL with the signed JWT attached and redirects to this URL.
     * @param string $name
     * @param string $email
     * @param string $return_to Fully qualified URL including http:// or https://. Do not urlencode the URL
     * @param $options array any additional parameters to include in the JWT
     * @return boolean
     */
    public function redirectToUrl($name, $email, $return_to=null, $options = [])
    {
        $url = $this->getUrl($name, $email, $return_to, $options);
        $this->sendHeader($url);
        return $this->terminate(); // exit
    }
    
    /**
     * Gets the raw (unencrypted) data to be used when constructing the JSON Web Token.
     * See https://support.zendesk.com/hc/en-us/articles/203663816-Setting-up-single-sign-on-with-JWT-JSON-Web-Token-
     *
     * @param string $name
     * @param string $email
     * @param array $options
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getRawToken($name, $email, array $options = [])
    {
        // Some filtering / validation first
        if (!is_scalar($name)) {
            throw new InvalidArgumentException('JWT name must be a string value');
        }
        
        $name = trim($name);
        $email = (is_scalar($email)) ? trim($email) : $email;
        
        if (empty($name)) {
            throw new InvalidArgumentException('JWT name must not be empty');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('JWT email must be an email address');
        }
        
        $now = time();
        
        // Set up all the required fields
        $token = [
            'jti'   => md5($now . rand()),
            'iat'   => $now,
            'name'  => $name,
            'email' => $email
        ];
        
        // Work with the optional fields
        $new_options_keys = array_intersect($this->optional_jwt_fields, array_keys($options));
        
        if ($invalid_keys = array_diff(array_keys($options), $this->optional_jwt_fields)) {
            throw new InvalidArgumentException('The following tokens keys are not allowed: '. implode(', ', $invalid_keys). '. Valid optional keys are '.implode(', ',$this->optional_jwt_fields));
        }
        
        foreach ($new_options_keys as $k) {
            $token[$k] = $options[$k];
        }
        
        return $token;
    }
    
    /**
     * In untestable parlance, this would simply be:
     *
     *      header('Location: ' . $location);
     *
     * @param $location string fully qualified URL
     * @param callable $function header (injected here to achieve testability)
     * @return mixed
     */
    protected function sendHeader($location, callable $function = null)
    {
        $function = ($function) ? $function : 'header';
        return call_user_func($function, 'Location: '. $location);
    }
    
    /**
     * Used to terminate the script and exit.  Written here for testability
     *
     * @param callable $function
     * @return mixed
     */
    protected function terminate(callable $function = null)
    {
        $function = ($function) ? $function : 'exit';
        return call_user_func($function);
    }
}