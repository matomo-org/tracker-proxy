# Matomo Tracker Proxy

[![Build Status](https://app.travis-ci.com/matomo-org/tracker-proxy.svg?branch=master)](https://app.travis-ci.com/matomo-org/tracker-proxy)

This script allows to track websites with Matomo **without revealing the Matomo server URL**.

This is useful for users who track multiple websites on the same Matomo server, but don't want to show the Matomo server URL in the source code of all tracked websites.

## Requirements

To run this properly you will need:

- latest version of Matomo installed on a server (or Matomo Cloud)
- one or several website(s) to track with this Matomo, for example `http://{site_to_be_tracked}`
- the website to track must run on a server with PHP 7.2 or higher
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

### Visitor IP forwarding

Because the proxy sits between your visitors and Matomo, it has to tell Matomo the real visitor IP — otherwise Matomo would record the proxy's IP. There are two ways this works:

- **Default — via `cip` + `token_auth`:** the proxy sends the visitor IP to Matomo as the `cip` tracking parameter, authorized by the `$TOKEN_AUTH` you configured (this is why the proxy user needs **write** or **admin** permission). Works out of the box with no Matomo-side configuration, for both single requests and bulk requests (the Matomo JavaScript tracker batches several actions into a single bulk request by default).
- **Header-only — via `$http_ip_forward_header`:** set `$http_ip_forward_header` in `config.php` (for example to `X-Forwarded-For`) to forward the visitor IP in that header instead. In this mode the proxy injects **no** `cip`/`token_auth` at all and relies solely on the header for the visitor IP — so it doesn't even need a write/admin token. **This only works if Matomo is configured to trust the header:** both the web server in front of Matomo (Apache [mod_remoteip](https://httpd.apache.org/docs/2.4/mod/mod_remoteip.html), nginx [realip](https://www.nginx.com/resources/wiki/start/topics/examples/forwarded/)) **and** Matomo's trusted-proxy settings (`proxy_client_headers[]` / `proxy_ips[]` in its `config.ini.php`). If it isn't, Matomo records the proxy's IP for every visitor.

> ⚠️ **Breaking change:** previously `$http_ip_forward_header` was sent *in addition* to `cip`+`token_auth`; the proxy now treats it as the *sole* IP mechanism and injects nothing else. If you already set it, make sure Matomo's trusted-proxy configuration above is in place — otherwise leave it empty to keep using `cip`.

### Cookie forwarding

By default, the proxy forwards the visitor's entire `Cookie` header to Matomo unchanged. If your site also sets other cookies (session, consent-management, A/B testing, etc.) alongside Matomo's, those are forwarded too.

To restrict this, set `$COOKIE_ALLOWLIST` in `config.php` to an array of cookie names. Each entry is matched either as an **exact name** (e.g. `mtm_consent`) or, if it ends with `*`, as a **prefix** (e.g. `_pk_id*` matches both `_pk_id` and `_pk_id.1.1fff`). Prefix entries are needed for Matomo's default id/session/referrer/custom-variable cookies, since the JavaScript tracker appends a per-site/per-domain suffix to their base name. Cookies matching no entry are stripped before the request reaches Matomo. Matching is case-sensitive; a prefix match is a plain string prefix, not delimiter-aware (so `_pk_id*` could in theory also match an unrelated cookie sharing that prefix, though unlikely in practice).

> Note: leaving `$COOKIE_ALLOWLIST` unset keeps today's behavior of forwarding all cookies unchanged. Setting it — even to an empty array — switches the proxy into allowlist mode; an empty array forwards no cookies at all. A bare `*` or empty-string entry is a no-op (matches nothing), not "allow everything", so a stray `array('*')` can't defeat the allowlist.

> ⚠️ **Always keep `matomo_ignore`** (or whatever cookie your Matomo's opt-out/consent setup relies on) in the allowlist. Dropping it silently re-enables tracking for visitors who opted out, with no visible error.

> ⚠️ **Check your Matomo tracker config before copying the example list below.** A custom `setCookieNamePrefix()` replaces `_pk_*` with your own prefix, and enabled plugins (e.g. HeatmapSessionRecording) or third-party tracking code may set additional cookies not covered by the example.
>
> A dropped tracking cookie doesn't error — Matomo just stops recognizing returning visitors, silently inflating visit counts. After configuring this, load a tracked page twice and confirm Matomo logs one visit, not two.

### Auth-protected tracking parameters

Some tracking parameters (`cip`, `cdt`, `cdo`, `country`, `region`, `city`, `lat`, `long`) are only honored by Matomo for an authenticated request. The proxy never lends its `$TOKEN_AUTH` to a request — or to an individual entry of a bulk request — that carries one of these override parameters or its own `token_auth`:

- **Carries an override parameter, no token:** forwarded without the proxy's token, so Matomo rejects/skips it exactly as if it had been sent directly without authentication — rather than being silently tracked with the client-supplied override. To set these parameters legitimately, send your own valid `token_auth`.
- **Carries its own `token_auth`:** the proxy adds no token of its own and lets the client's token govern. It still forwards the visitor IP as `cip`, so that token must have write access to authorize it (otherwise the request/entry is rejected).

> ⚠️ **Behavior change:** if you add any of these parameters via `appendToTrackingUrl` (or otherwise) without your own `token_auth`, those requests are now **rejected** by Matomo. Previously the proxy stripped the parameter and tracked the rest of the hit; it no longer does. Send a valid `token_auth` if you need these parameters.

> Note: if your Matomo server sets `bulk_requests_require_authentication = 1`, it requires a single batch-level `token_auth` for the whole bulk request. The proxy supplies that batch-level token only when **every** entry in the batch is clean; if any entry carries an override parameter or its own `token_auth`, the proxy withholds it (a batch-level token would wrongly authorize that entry) and Matomo then rejects the **entire** bulk request — clean entries included. Under that configuration, send your own batch-level `token_auth` or avoid mixing override entries into proxied bulk requests.

> Note: bulk tracking requests must be sent with an `application/x-www-form-urlencoded` content type (as the Matomo JavaScript tracker does). Bulk request bodies sent as `application/json` are not forwarded by the proxy.

> Note: the proxy rebuilds the forwarded request from the parameters PHP parsed (`$_GET`/`$_POST`, or the decoded JSON for bulk), so it only ever sends Matomo what it inspected. Make sure the proxy host's PHP `post_max_size` is large enough for your biggest (bulk) tracking requests — a body exceeding it is dropped by PHP and not forwarded.

## Contributing

If you have found a bug, you are welcome to submit a pull request.

### Running the tests

Before running the tests, create a config.php file w/ the following contents in the root repository directory:

```
<?php
$MATOMO_URL = 'http://localhost/tests/server/';
$PROXY_URL = 'http://localhost/';
$TOKEN_AUTH = 'xyz';
$timeout = 5;

// Test-only request headers (below) let the suite exercise config variations per-request.
// Gated on the local test-server URL so a stray copy to production is inert.
$isTestServer = strpos($MATOMO_URL, '/tests/server/') !== false;

// Exercise IP-forward-header handling.
if ($isTestServer && !empty($_SERVER['HTTP_X_TEST_IP_FORWARD_HEADER'])) {
    $http_ip_forward_header = $_SERVER['HTTP_X_TEST_IP_FORWARD_HEADER'];
}

// Exercise cookie-allowlist filtering (comma-separated entries; empty value = explicit empty allowlist).
if ($isTestServer && isset($_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST'])) {
    $COOKIE_ALLOWLIST = $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST'] === ''
        ? array()
        : explode(',', $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST']);
}

// Exercise misconfiguration handling (a non-array $COOKIE_ALLOWLIST).
if ($isTestServer && isset($_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST_INVALID'])) {
    $COOKIE_ALLOWLIST = $_SERVER['HTTP_X_TEST_COOKIE_ALLOWLIST_INVALID'];
}
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

Be advised that the proxy and its tests require PHP 7.2 or higher.
