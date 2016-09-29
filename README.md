# Zendesk JSON Web Token (JWT) Single Sign-On (SSO)

[![Build Status](https://travis-ci.org/fireproofsocks/zendesk-jwt-sso.svg?branch=master)](https://travis-ci.org/fireproofsocks/zendesk-jwt-sso) [![codecov](https://codecov.io/gh/fireproofsocks/zendesk-jwt-sso/branch/master/graph/badge.svg)](https://codecov.io/gh/fireproofsocks/zendesk-jwt-sso)

This package has one goal: to facilitate automatic login from your application into your Zendesk support account using [JSON web tokens](https://jwt.io/introduction/).  Zendesk's Single Sign-on feature will automatically create new users based on their email addresses the first time they click on the special links created by this package, and it will re-connect users to existing accounts on subsequent visits.

This package does not wrap the [Zendesk API](https://github.com/zendesk/zendesk_api_client_php) or make any API calls, nor does the Zendesk API offer any methods or documentation for generating SSO JSON web tokens.  This package can be used independently or in conjuction with the Zendesk API.

# Installation via Composer

Inside of `composer.json` specify the following (substitute a specific version number from [Packagist](https://packagist.org/packages/fireproofsocks/zendesk-jwt-sso) if necessary):

```
{
  "require": {
    "fireproofsocks/zendesk-jwt-sso": "dev-master"
  }
}
```
Other installation methods are not recommended: there are dependencies for this package.

# Examples

> See the section below about setting up your Zendesk Account to see how and where to get your subdomain and shared secret.

A full example, e.g. inside a controller or on a dedicated PHP page in your web root:

```
require_once 'vendor/autoload.php'

// Retrieve these from .env or config
$subdomain = 'your-zendesk-subdomain';
$shared_secret = 'xxxxxxxxxxxxxx';

$sso = new \ZendeskSso\ZendeskSso($subdomain, $shared_secret);

// Remember: SSO means that YOUR application is handling the login.  Only forward a user over to Zendesk when they are
// logged in on your site. 
if (is_logged_in()) {
    $name = 'Name of Logged in User'; // e.g. from $_SESSION
    $email = 'email.of@user.com';
    $external_id = 123; // user id in your app
    $return_to = 'http://my-domain.com/some/page';
    $options = ['external_id' => $external_id];
    $sso->redirectToUrl($name, $email, $return_to, $options);  // this will redirect and exit
}
else {
    print 'Sorry, you are not logged into our site.  You can visit our Zendesk portal as a guest (if enabled).';
}
```

Or more simply:

```
require_once 'vendor/autoload.php'

// Retrieve these from .env or config
$subdomain = 'your-zendesk-subdomain';
$shared_secret = 'xxxxxxxxxxxxxx';

$sso = new \ZendeskSso\ZendeskSso($subdomain, $shared_secret);

// Remember: SSO means that YOUR application is handling the login.  Only forward a user over to Zendesk when they are
// logged in on your site. 
if (is_logged_in()) {
    $name = 'Name of Logged in User'; // e.g. from $_SESSION
    $email = 'email.of@user.com';
    $sso->redirectToUrl($name, $email);  // this will redirect and exit
}
```

If you need to get the SSO URL, you may use the `getUrl` method, but remember that the JWT expires in a few seconds, so you don't want to print out the URL in an HTML template or in an anchor tag -- you want to redirect to that URL quickly after generating it.

```
require_once 'vendor/autoload.php'

// Retrieve these from .env or config
$subdomain = 'your-zendesk-subdomain';
$shared_secret = 'xxxxxxxxxxxxxx';

$sso = new \ZendeskSso\ZendeskSso($subdomain, $shared_secret);
 
if (is_logged_in()) {
    $name = 'Name of Logged in User'; // e.g. from $_SESSION
    $email = 'email.of@user.com';
    $url = $sso->getUrl($name, $email);
    header('Location: '.$url);
    exit();
}
```

---------------------------------------------------------

# Setup of Your Zendesk Account

You will need to configure your Zendesk account in order to use the SSO feature. To connect to an existing Zendesk account, your application will need the following things:
 
- **Zendesk sub-domain**
- **Shared Secret token**

If the Zendesk account has already been configured and you have those bits of information, you can skip this section and continue on to testing your implementation.  However, if you need to set up your application to test against a [Sandbox environment](https://support.zendesk.com/hc/en-us/articles/203661826-Testing-changes-in-your-sandbox-Enterprise-) (e.g. so that developers do not have access to the live, production tokens or data), then you will need to create a sandbox and enable/configure SSO on the sandbox.  Unfortunately the Sandbox environment is only available on certain subscription plans.  See more about Sandboxes in the "Testing Integration" section below.

> **REMEMBER:** You will have to repeat the setup each time the Sandbox environment is reset!

## What Zendesk Needs

Before you set up your Zendesk account for Single Sign-on, you should know the following items:
 
- **Your application's login URL**
- **Your application's logout URL** _(optional)_
- **Update external IDs?** _(optional)_ - the `external_id` is unique for an account, it can be a useful way of identifying a user if your application allows users to update their email addresses or if your application uses something other than an email address (e.g. a username or primary key)to uniquely identify users.   

## Enabling SSO

> To get or create your Zendesk API key, *you must be an administrator of your Zendesk account.*  If you are not an administrator, you will need to provide an administrator the details above (login URL, etc.).  They can read these setup instructions and to provide to you the sub-domain and the shared secret to use.

Log into your Zendesk account at its URL, e.g. `https://your-company-name.zendesk.com/login`

At the bottom left corner of your admin screens is the gear icon for "Admin".  Click it, then scroll the left-hand column down to the "Settings" heading and look for the "Security" link.

> Heads up!  The layout of the Zendesk admin site can be confusing! There is a left-hand column just to the right of the icons.  This column can be scrolled up and down _separately_ from the main page area.

On the Security page at `/agent/admin/security`, there are several tabs that you need to look at to properly configure how you want SSO to function. Not only can you define and configure SSO differently between your regular and Sandbox account, **you can enable and configure SSO separately for the two main types of users:** 

- Admins and Agents
- End-users


Usually you'll want to enable SSO for End-users, and optionally you may want to enable the SSO feature for your admins and agents.  **REMEMBER:** *these are two completely separate features!*  The configurations of each group can be completely different!

Click on the Enable the "Single Sign On (SSO)" feature, then check the "JSON Web Token" option.  

- Enter in your application's login URL (i.e. the "Remote" login URL).
- Optionally enter in your application's logout URL
- Optionally enable the updating of external IDs.
- Copy the Shared secret token and save it to a secure location.

## Recomended Security Settings

While you're in your Zendesk admin security settings at `/agent/admin/security`, you may wish to enable the following features under the "Global" tab for added security:
 
- **Automatic Redaction** -- automatically _X_ out credit card numbers.  If your account is ever compromised, you will reduce your legal liability if the hacker couldn't get their hands on credit card numbers.  Your support staff can still identify card numbers from the remaining digits (e.g. the last 4).
- **Two Factor Authentication** -- requiring this is one of the single most important things you can do to improve your security posture.  When active, it almost eliminates the possibility that a nefarious user could gain access to your Zendesk application and its treasure-trove of customer data and credit card numbers (you did check the box for "Automatic Redaction", didnt' you?)

--------------------------------------

# Testing Integration

## Setup a Sandbox Account

Zendesk Enterprise plan offers a [Sandbox environment](https://support.zendesk.com/hc/en-us/articles/203661826-Testing-changes-in-your-sandbox-Enterprise-) for safely testing its API, but it may not be obvious that the Sandbox can also be used to test SSO logins.  Log into your Zendesk admin dashboard and head to the "Admin" gear at the bottom left, then select **Manage > Sandbox**.  If you haven't already, click on "Create my Sandbox", or if you need to reset things, you can "Reset My Sandbox" instead. 

**When you create or refresh a Sandbox, the user profiles and tickets and the SSO settings are not copied!**  You will need to log into your Sandbox as an admin and then configure/enable JSON Web Token SSO.  

Unfortunately, Zendesk only makes use of one Sandbox and it can only be configured with a single login and logout URL; this may be problematic if your application is using multiple dev environments, each with unique hostnames and URLs.

Once you have an environment set up that you want to test against (either your Sandbox or your real environment), you can make try creating SSO URLs and visiting them.  

More savvy developers may wish to copy the `phpunit.xml.dist` to `phpunit.xml`, add credentials to it, and try running the integration tests:
`phpunit tests/Integration/`


--------------------------------------

# JSON Web Token Supported Attributes

The following values are included as a reference.  All optional values may be included as keys in the `$options` array passed as the 4th argument to the `getUrl` and `redirectToUrl` methods.

From https://support.zendesk.com/hc/en-us/articles/203663816-Setting-up-single-sign-on-with-JWT-JSON-Web-Token-

| **Attribute** | **Mandatory** | **Description** |
| --- | --- | --- |
| `iat` | Yes | Issued At. The time the token was generated, this is used to help ensure that a given token gets used shortly after it's generated. The value must be the number of seconds since UNIX epoch. Zendesk allows up to two minutes clock skew, so make sure to configure NNTP or similar on your servers. |
| `jti` | Yes | JSON Web Token ID. A unique id for the token, used by Zendesk to prevent token replay attacks. |
| `email` | Yes | Email of the user being signed in, used to uniquely identify the user record in Zendesk. |
| `name` | Yes | The name of this user. The user in Zendesk will be created or updated in accordance with this. |
| `external_id` | No | If your users are uniquely identified by something other than an email address, and their email addresses are subject to change, send the unique id from your system. Specify the id as a string. |
| `locale` (for end-users) `locale_id` (for agents) | No | The locale in Zendesk, specified as a number. |
| `organization` | No | The name of an organization to add the user to. |
| `organization_id` | No | The organization's external ID in the Zendesk API. If both organization and organization_id are supplied, organization is ignored. |
| `phone` | No | A phone number, specified as a string. |
| `tags` | No | This is a JSON array of tags to set on the user. These tags will replace any other tags that may exist in the user's profile.
| `remote_photo_url` | No | URL for a photo to set on the user profile. |
| `role` | No | The user's role. Can be set to "user", "agent", or "admin". Default is "user". If the user's role is different in Zendesk, the role is changed in Zendesk. |
| `custom_role_id` | No | Applicable only if the role of the user is agent. |
| `user_fields` | No | A JSON hash of user field key and values to set on the user. The user field must exist in order to set the field value. Each user field is identified by its field key found in the user fields admin settings. The format of date values is `yyyy-mm-dd`. |

> If a user field key or value is invalid, updating the field will fail silently and the user will still login successfully. For more information about custom user fields, see Adding custom fields to users.

>Note: Sending null values in the the user_fields attribute will remove any existing values in the corresponding fields.

--------------------------------------------------

# Troubleshooting

## Login Trouble

If you get stuck in a redirect loop for any reason (e.g. because you misconfigured the SSO options), you can bypass the SSO login and login "normally" at the "secret" URL: `https://your-company-name.zendesk.com/access/normal`

If your "regular" login was via OAuth (e.g. Google Mail), then you can request a password reset to generate a password for yourself.

## Test Users

The SSO flow will automatically create users.  During your setup and testing, you may end up creating test user accounts.  If you wish to delete these, you can do this from the Zendesk admin portal under the "Manage" --> "People" page at `/agent/admin/people`.  To delete a single user, select the user's name (you will get a detailed view of the user and their tickets), then choose the "Delete" option from the dropdown arrow menu at the top right.  To delete multiple users in one go, find the link for "Bulk end-user Delete" in the right hand column to take you to `/people/bulk_delete`

## Failed Tests

The unit tests should be safe to run without any network connectivity.  The integration tests, however, _do_ go over the nework and they _do_ hit your account.
At times, sometimes networking errors can be experienced, e.g. "php_network_getaddresses: getaddrinfo failed: nodename nor servname provided, or not known", or "failed to open stream: Operation timed out".  This is outside of our control, so you'd best wait a few minutes and try again to see if the problem goes away.  I'm unsure if this is because of API throttling or a firewall or something else entirely.