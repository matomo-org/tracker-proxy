# Matomo Tracker Proxy

[![Build Status](https://app.travis-ci.com/matomo-org/tracker-proxy.svg?branch=master)](https://app.travis-ci.com/matomo-org/tracker-proxy)

This script allows to track websites with Matomo **without revealing the Matomo server URL**.

This is useful for users who track multiple websites on the same Matomo server, but don't want to show the Matomo server URL in the source code of all tracked websites.

## Requirements

To run this properly you will need:

- latest version of Matomo installed on a server (or Matomo Cloud)
- one or several website(s) to track with this Matomo, for example `http://{site_to_be_tracked}`
- the website to track must run on a server with PHP 5.3 or higher
- PHP must have either the CURL extension enabled or `allow_url_fopen=On`

## Installation

### 1. Create a user in Matomo

In your Matomo server:

- login as Super user
- create a user, set the login for example: "UserTrackingAPI"
- assign this user **write** or **admin** permission on all websites you wish to track (otherwise the visitor IP address and other things won't be tracked correctly)
- log in as this newly created user and [create an auth token](https://matomo.org/faq/general/faq_114/)

### 2. Install the proxy

You need to install the proxy on the server where your websites are hosted. You can do it both ways:

- download the files manually
- or install the whole repository with git


| :zap:        Important note about where to install the proxy   |
|-----------------------------------------|
| To ensure the highest data accuracy possible, and that your Matomo cookies are set correctly, please install the proxy in your main website domain name and  web server. This proxy should be ideally installed on your webserver directly under `{site_to_be_tracked}`. If you installed the proxy in a sub-domain under `analytics.{site_to_be_tracked}` then this would cause data to be less accurate. (Why? because if the sub-domain `analytics.{site_to_be_tracked}` was to resolve to a CNAME that does _not_ match `{site_to_be_tracked}` OR if it was to resolve to A/AAAA addresses that do not match the first half of the A/AAAA addresses running `{site_to_be_tracked}`, then the cookies set by the Matomo Tracker Proxy in the response would only have a lifetime of maximum 7 days on Safari >= 16.4.) |

#### Manual download of `matomo.php`

- download [`matomo.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/matomo.php), download [`piwik.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/piwik.php), [`proxy.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/proxy.php), [`matomo-proxy.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/matomo-proxy.php)  & if you are using the Heatmaps and Session recordings plugin also download [`plugins/HeatmapSessionRecording/configs.php`](https://raw.githubusercontent.com/matomo-org/tracker-proxy/master/plugins/HeatmapSessionRecording/configs.php)
  to your website root directory, for example at `http://{site_to_be_tracked}/matomo.php`, `http://{site_to_be_tracked}/piwik.php`, `http://{site_to_be_tracked}/proxy.php`, `http://{site_to_be_tracked}/matomo-proxy.php` & `http://{site_to_be_tracked}/plugins/HeatmapSessionRecording/configs.php`
- edit the file to set the configuration variables:
    - `$MATOMO_URL` should contain the URL to your Matomo server
    - `$PROXY_URL` should contain the URL to the tracker-proxy server
    - `$TOKEN_AUTH` should contain the `token_auth`

#### With git

- clone the repository: `git clone https://github.com/matomo-org/tracker-proxy.git matomo` into your website root directory (for example at `http://{site_to_be_tracked}/matomo/matomo.php`)
- copy the configuration template: `cp config.php.example config.php`
- change the configuration in the newly created `config.php`:
    - `$MATOMO_URL` should contain the URL to your Matomo server
    - `$PROXY_URL` should contain the URL to the tracker-proxy server
    - `$TOKEN_AUTH` should contain the `token_auth`

By using git you will later be able to update by simply running `git pull`.

Be aware that with this method, `matomo.php` and other files are in a `matomo/` subdirectory. Keep that in mind when applying the instructions for the next step.

### 3. Use the proxy in the Javascript tracker

The proxy file (`http://{site_to_be_tracked}/matomo.php`) will be called by the Matomo Javascript tracker instead of calling directly the (secret) Matomo server (`http://your-matomo-domain.example.org/matomo/`).

To achieve this, change the Matomo Javascript Code that is in the footer of your pages:

- go to *Matomo > Settings > Websites > Show Javascript Tracking Code*.
- copy the Javascript snippet and change the last lines to the following:

    ```javascript
    [...]
    (function() {
        var u="//{site_to_be_tracked}/";
        _paq.push(["setTrackerUrl", u+"matomo.php"]);
        _paq.push(["setSiteId", "tracked-site-id-here"]);
        var d=document, g=d.createElement("script"), s=d.getElementsByTagName("script")[0];
        g.type="text/javascript"; g.async=true; g.defer=true; g.src=u+"matomo.php"; s.parentNode.insertBefore(g,s);
    })();
    </script>
    <!-- End Matomo Code -->
    ```

    What has changed in this code snippet compared to the normal Matomo code?

    - any reference(s) to the secret Matomo URL are now replaced by your website URL (the proxy).
    - `matomo.js` becomes `matomo.php` (or `matomo/matomo.php` if you used the *git* method): matomo.php is the proxy script
    - make sure to replace `tracked-site-id-here` with your idsite
    - if the `<noscript>` is present in your tracking code, you can remove it (it contains the secret Matomo URL which you want to hide)

- paste the modified Matomo Javascript code in the pages you wish to track.

This modified Javascript code will then track visits/pages/conversions by calling `{site_to_be_tracked}/matomo.php`, which will then automatically call your (hidden) Matomo Server URL.

At this stage, example.com should be tracked by your Matomo without showing the Matomo server URL. Repeat the step 3. for each website you wish to track in Matomo.

### 4. (optional) Use the proxy with Matomo's Opt Out form

The tracker-proxy also supports proxying the matomo opt out form. To use this, change the URL used in the opt out iframe to use
the tracker proxy's `matomo-proxy.php` file.

_Note: you can get the opt out iframe from inside the Administration > Privacy > Users opt-out page in your Matomo instance._

## Configuration

### Timeout

By default, the `matomo.php` proxy will wait 5 seconds for the Matomo server to return the response.
You may change this timeout by editing the `$timeout` value in `config.php`.

### User-Agent

By default, the `matomo.php` proxy will contact your Matomo server with the User-Agent of the client requesting `matomo.php`.
You may force the proxy script to use a particular User-Agent by  editing the `$user_agent` value in `config.php`.

## Contributing

If you have found a bug, you are welcome to submit a pull request.

### Running the tests

Before running the tests, create a config.php file w/ the following contents in the root repository directory:

```
<?php
$MATOMO_URL = 'http://localhost/tests/server/';
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

Be advised that the tests require at least PHP 5.4 to run, but the proxy itself can run with PHP 5.3.
