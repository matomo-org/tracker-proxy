# Matomo Tracker Proxy

[![Build Status](https://travis-ci.org/matomo-org/tracker-proxy.svg?branch=master)](https://travis-ci.org/matomo-org/tracker-proxy)

This script allows to track websites with Matomo **without revealing the Matomo server URL**.

This is useful for users who track multiple websites on the same Matomo server, but don't want to show the Matomo server URL in the source code of all tracked websites.

## Requirements

To run this properly you will need:

- latest version of Matomo installed on a server
- one or several website(s) to track with this Matomo, for example http://trackedsite.com
- the website to track must run on a server with PHP 5.2 or higher
- PHP must have either the CURL extension enabled or `allow_url_fopen=On`

## Installation

### 1. Create a user in Matomo

In your Matomo server:

- login as Super user
- create a user, set the login for example: "UserTrackingAPI"
- assign this user *admin* permission on all websites you wish to track
- copy the `token_auth` for this user: you will use it later

### 2. Install the proxy

You need to install the proxy on the server where your websites are hosted. You can do it both ways:

- download [`piwik.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/piwik.php)
- or install the whole repository with git

#### Manual download of `piwik.php`

- download [`piwik.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/piwik.php) to your website root directory, for example at http://trackedsite.com/piwik.php
- edit the file to set the configuration variables:
    - `$PIWIK_URL` should contain the URL to your Matomo server
    - `$TOKEN_AUTH` should contain the `token_auth`

#### With git

- clone the repository: `git clone https://github.com/matomo-org/tracker-proxy.git matomo` into your website root directory (for example at http://trackedsite.com/matomo/piwik.php)
- copy the configuration template: `cp config.php.example config.php`
- change the configuration in the newly created `config.php`:
    - `$PIWIK_URL` should contain the URL to your Matomo server
    - `$TOKEN_AUTH` should contain the `token_auth`

By using git you will later be able to update by simply running `git pull`.

Be aware that with this method, `piwik.php` is in a `matomo/` subdirectory. Keep that in mind when applying the instructions for the next step.

### 3. Use the proxy in the Javascript tracker

The proxy file (http://trackedsite.com/piwik.php) will be called by the Matomo Javascript tracker instead of calling directly the (secret) Matomo server (http://your-matomo-domain.example.org/matomo/).

To achieve this, change the Matomo Javascript Code that is in the footer of your pages:

- go to *Matomo > Settings > Websites > Show Javascript Tracking Code*.
- copy the Javascript snippet and change the last lines to the following:

    ```javascript
    [...]
    (function() {
        var u="//trackedsite.com/";
        _paq.push(["setTrackerUrl", u+"piwik.php"]);
        _paq.push(["setSiteId", "trackedsite-id"]);
        var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0];
        g.type="text/javascript"; g.async=true; g.defer=true; g.src=u+"piwik.php"; s.parentNode.insertBefore(g,s);
    })();
    </script>
    <!-- End Matomo Code -->
    ```

    What has changed in this code snippet compared to the normal Matomo code?

    - the secret Matomo URL is now replaced by your website URL (the proxy)
    - `piwik.js` becomes `piwik.php` (or `matomo/piwik.php` if you used the *git* method): piwik.php is the proxy script
    - the `<noscript>` part of the code at the end is removed, since it is not currently used by Matomo, and it contains the (secret) Matomo URL which you want to hide
    - make sure to replace `trackedsite-id` with your idsite

- paste the modified Matomo Javascript code in the pages you wish to track.

This modified Javascript code will then track visits/pages/conversions by calling `trackedsite.com/piwik.php`, which will then automatically call your (hidden) Matomo Server URL.

At this stage, example.com should be tracked by your Matomo without showing the Matomo server URL. Repeat the step 3. for each website you wish to track in Matomo.

## Configuration

### Timeout

By default, the `piwik.php` proxy will wait 5 seconds for the Matomo server to return the response. 
You may change this timeout by editing the `$timeout` value in `config.php`.
 
### User-Agent
 
By default, the `piwik.php` proxy will contact your Matomo server with the User-Agent of the client requesting `piwik.php`. 
You may force the proxy script to use a particular User-Agent by  editing the `$user_agent` value in `config.php`.

## Contributing

If you have found a bug, you are welcome to submit a pull request.

### Running the tests

Before running the tests, create a config.php file w/ the following contents in the root repository directory:

```
<?php
$PIWIK_URL = 'http://localhost/tests/server/';
$TOKEN_AUTH = 'xyz';
$timeout = 5;
```

The tests need a webserver to be pointed to the root of this repository. The simplest way is to just use Vagrant:

```
$ vagrant up
$ vagrant ssh
$ cd /vagrant/tests
$ composer install
```
#### Running the tests with CURL wrapper

* Set `allow_url_fopen = Off` in your webserver php.ini
* Check in phpinfo() that `allow_url_fopen = Off`
* Run: `vendor/bin/phpunit`

#### Running the tests with Fopen wrapper

* Set `allow_url_fopen = On` in your webserver php.ini
* Check in phpinfo() that `allow_url_fopen = On`
* Run: `vendor/bin/phpunit`

Be advised that the tests require at least PHP 5.4 to run, but the proxy itself can run with PHP 5.2.
