# Piwik Tracker Proxy

[![Build Status](https://travis-ci.org/piwik/tracker-proxy.svg?branch=master)](https://travis-ci.org/piwik/tracker-proxy)

This script allows to track websites with Piwik **without revealing the Piwik server URL**.

This is useful for users who track multiple websites on the same Piwik server, but don't want to show the Piwik server URL in the source code of all tracked websites.

## Requirements

To run this properly you will need:

- latest version of Piwik installed on a server
- one or several website(s) to track with this Piwik, for example http://trackedsite.com
- the website to track must run on a server with PHP 5.2 or higher
- your `php.ini` you must contain `allow_url_fopen = On`

## Installation

### 1. Create a user in Piwik

In your Piwik server:

- login as Super user
- create a user, set the login for example: "UserTrackingAPI"
- assign this user *admin* permission on all websites you wish to track
- copy the `token_auth` for this user: you will use it later

### 2. Install the proxy

You need to install the proxy on the server where your websites are hosted. You can do it both ways:

- download [`piwik.php`](https://raw.githubusercontent.com/piwik/tracker-proxy/master/piwik.php)
- or install the whole repository with git

#### Manual download of `piwik.php`

- download [`piwik.php`](https://raw.githubusercontent.com/piwik/tracker-proxy/master/piwik.php) to your website root directory, for example at http://trackedsite.com/piwik.php
- edit the file to set the configuration variables:
    - `$PIWIK_URL` should contain the URL to your Piwik server
    - `$TOKEN_AUTH` should contain the `token_auth`

#### With git

- clone the repository: `git clone https://github.com/piwik/tracker-proxy.git piwik` into your website root directory (for example at http://trackedsite.com/piwik/piwik.php)
- copy the configuration template: `cp config.php.example config.php`
- change the configuration in the newly created `config.php`:
    - `$PIWIK_URL` should contain the URL to your Piwik server
    - `$TOKEN_AUTH` should contain the `token_auth`

By using git you will later be able to update by simply running `git pull`.

Be aware that with this method, `piwik.php` is in a `piwik/` subdirectory. Keep that in mind when applying the instructions for the next step.

### 3. Use the proxy in the Javascript tracker

The proxy file (http://trackedsite.com/piwik.php) will be called by the Piwik Javascript tracker instead of calling directly the (secret) Piwik server (http://your-piwik-domain.example.org/piwik/).

To achieve this, change the Piwik Javascript Code that is in the footer of your pages:

- go to *Piwik > Settings > Websites > Show Javascript Tracking Code*.
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
    <!-- End Piwik Code -->
    ```

    What has changed in this code snippet compared to the normal Piwik code?

    - the secret Piwik URL is now replaced by your website URL (the proxy)
    - `piwik.js` becomes `piwik.php` (or `piwik/piwik.php` if you used the *git* method): piwik.php is the proxy script
    - the `<noscript>` part of the code at the end is removed, since it is not currently used by Piwik, and it contains the (secret) Piwik URL which you want to hide
    - make sure to replace `trackedsite-id` with your idsite

- paste the modified Piwik Javascript code in the pages you wish to track.

This modified Javascript code will then track visits/pages/conversions by calling `trackedsite.com/piwik.php`, which will then automatically call your (hidden) Piwik Server URL.

At this stage, example.com should be tracked by your Piwik without showing the Piwik server URL. Repeat the step 3. for each website you wish to track in Piwik.

## Configuration

### Timeout

By default, the `piwik.php` proxy will wait 5 seconds for the Piwik server to return the response. 
You may change this timeout by editing the `$timeout` value in `config.php`.
 
### User-Agent
 
By default, the `piwik.php` proxy will contact your Piwik server with the User-Agent of the client requesting `piwik.php`. 
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
$ vendor/bin/phpunit
```

Be advised that the tests require at least PHP 5.4 to run, but the proxy itself can run with PHP 5.2.
