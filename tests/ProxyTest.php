<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;

class ProxyTest extends TestCase
{
    /**
     * @test
     */
    public function get_without_parameters_should_forward_matomo_js_content()
    {
        $response = $this->send();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('this is matomo.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type')[0]);
    }

    /**
     * @test
     */
    public function matomo_js_should_not_be_updated_if_less_than_1_day()
    {
        $response = $this->send(null, new DateTime('-23 hours'));

        $this->assertEquals('', $response->getBody()->getContents());
        $this->assertEquals(304, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function matomo_js_should_be_updated_if_more_than_1_day()
    {
        $response = $this->send(null, new DateTime('-25 hours'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('this is matomo.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type')[0]);
        $this->assertNotNull($response->getHeader('Last-Modified')[0]);
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_matomo_php()
    {
        $response = $this->send('foo=bar');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/gif', $response->getHeader('Content-Type')[0]);
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_query_parameters()
    {
        $response = $this->send('foo=bar');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'foo' => 'bar',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_response_code()
    {
        $response = $this->send('status=204');

        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function error_should_forward_error_code()
    {
        $response = $this->send('status=404');
        $this->assertEquals(404, $response->getStatusCode());

        $response = $this->send('status=500');
        $this->assertEquals(500, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function get_return_matomoJs_without_error_when_server_down()
    {
        $matomoUrl = $this->getMatomoUrl();

        // Remove config file -> matomo.php will use the default value 'http://your-matomo-domain.example.org/matomo/'
        rename(__DIR__ . '/../config.php', __DIR__ . '/../config.php.save');

        $this->assertFalse(file_exists('config.php'), "Failed asserting that file does not exist: config.php");

        try {
            $response = $this->send(null, null, $matomoUrl);
        }
        catch (exception $e) {
            // continue even if guzzle throws errors, otherwise the next test
            // will fail as well due to the missing config file
        }

        // Restore the config file
        rename(__DIR__ . '/../config.php.save', __DIR__ . '/../config.php');
        $this->assertFileExists('config.php');

        $expected = '/* there was an error loading matomo.js */';
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $response->getBody()->getContents());

    }

    /**
     * @test
     */
    public function test_with_http_dnt_header()
    {
        $response = $this->send('foo=bar', null, null, ['DNT' => '1']);

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'foo' => 'bar',
)
array (
  'DNT' => '1',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    /**
     * @test
     */
    public function test_with_http_x_do_not_track_header()
    {
        $response = $this->send('foo=bar', null, null, ['X-Do-Not-Track' => '1']);

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'foo' => 'bar',
)
array (
  'X_DO_NOT_TRACK' => '1',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_plugin_config_php_proxied_correctly()
    {
        $response = $this->send('idsite=35&trackerid=123456', null, null, null, '/plugins/HeatmapSessionRecording/configs.php');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
in plugins/HeatmapSessionRecording/configs.php
array (
  'idsite' => '35',
  'trackerid' => '123456',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_post_requests_are_proxied_correctly()
    {
        $response = $this->send('foo=bar', null, null, ['content-type' => 'application/x-www-form-urlencoded'], null, 'POST', 'baz=buz');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'foo' => 'bar',
)
array (
  'baz' => 'buz',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_post_requests_without_client_token_get_no_proxy_token_and_params_are_kept()
    {
        $response = $this->send(
            'foo=bar',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            'country=ru&region=77&city=Moscow&lat=55.75&long=37.61&cdt=2020-01-01+00%3A00%3A00&action_name=spoof'
        );

        $responseBody = $this->getBody($response);

        // The client supplied auth-protected params but no token, so the proxy must NOT lend its
        // token_auth. The params are forwarded untouched; Matomo rejects the request itself.
        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'foo' => 'bar',
)
array (
  'country' => 'ru',
  'region' => '77',
  'city' => 'Moscow',
  'lat' => '55.75',
  'long' => '37.61',
  'cdt' => '2020-01-01 00:00:00',
  'action_name' => 'spoof',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_post_requests_with_client_token_auth_keep_params_and_get_no_proxy_token()
    {
        $response = $this->send(
            'foo=bar',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            'token_auth=client-token&country=ru&region=77&city=Moscow&lat=55.75&long=37.61&cdt=2020-01-01+00%3A00%3A00&action_name=spoof'
        );

        $responseBody = $this->getBody($response);

        // The client authenticates itself: the proxy keeps its own token out (so Matomo uses the
        // client token) and forwards the params unchanged.
        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'foo' => 'bar',
)
array (
  'token_auth' => 'client-token',
  'country' => 'ru',
  'region' => '77',
  'city' => 'Moscow',
  'lat' => '55.75',
  'long' => '37.61',
  'cdt' => '2020-01-01 00:00:00',
  'action_name' => 'spoof',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_get_request_with_admin_param_without_token_gets_cip_but_no_token()
    {
        $response = $this->send('idsite=1&country=ru');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'idsite' => '1',
  'country' => 'ru',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_get_request_with_cdo_without_token_gets_cip_but_no_token()
    {
        // cdo (custom datetime offset) backdates the visit and requires auth in Matomo, so it must
        // also withhold our token.
        $response = $this->send('idsite=1&cdo=200000');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'idsite' => '1',
  'cdo' => '200000',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_get_request_with_client_cip_is_not_overridden_and_gets_no_token()
    {
        $response = $this->send('idsite=1&cip=6.6.6.6&country=ru');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'idsite' => '1',
  'cip' => '6.6.6.6',
  'country' => 'ru',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_empty_token_auth_alone_is_tracked_with_proxy_token()
    {
        // An empty token_auth is "no token" (as Matomo reads it). With no override present the
        // proxy injects its cip+token (and drops the empty client token so it can't clobber ours).
        $response = $this->send('idsite=1&token_auth=');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'idsite' => '1',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_array_token_auth_alone_is_tracked_with_proxy_token()
    {
        // Same as above with an array-typed token_auth, which Matomo also ignores.
        $response = $this->send('idsite=1&token_auth[]=x');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'idsite' => '1',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_empty_token_auth_with_override_does_not_receive_proxy_token()
    {
        // Empty token + an override: the override alone must withhold our token (type-juggling
        // bypass), so Matomo rejects the cip instead of us authorizing it.
        $response = $this->send('idsite=1&token_auth=&cip=6.6.6.6');

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('<token>', $responseBody);
    }

    public function test_array_token_auth_with_override_does_not_receive_proxy_token()
    {
        $response = $this->send('idsite=1&token_auth[]=x&country=ru');

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('<token>', $responseBody);
    }

    public function test_post_requests_forward_body_rebuilt_from_parsed_post()
    {
        $response = $this->send(
            'foo=bar&raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            'action_name=hello%20world'
        );

        $responseBody = $this->getBody($response);

        // The forwarded body is rebuilt from the parsed $_POST (so it only ever contains what the
        // proxy inspected); the value is unchanged, only the space re-encodes as '+'.
        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'foo' => 'bar',
  'raw_input' => '1',
)
array (
  'action_name' => 'hello world',
)
RAW: action_name=hello+world
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_bulk_request_injects_cip_and_top_level_token_into_clean_batch()
    {
        $body = '{"requests":["?idsite=1&rec=1&action_name=one","?idsite=1&rec=1&action_name=two"],"send_image":0}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // A fully clean batch gets cip injected per entry, and our token once at the JSON body top
        // level - one token authorizes the whole clean batch and satisfies Matomo's bulk auth gate.
        $this->assertStringContainsString('action_name=one&cip=', $responseBody);
        $this->assertStringContainsString('action_name=two&cip=', $responseBody);
        // The token is set at the top level (sanitized to <token>), not per entry.
        $this->assertStringContainsString('"token_auth":"<token>"', $responseBody);
        $this->assertEquals(0, substr_count($responseBody, 'token_auth=<token>'));

        // The outer query carries only what the client sent - no cip/token injected at that level.
        $expectedGet = <<<GET
array (
  'raw_input' => '1',
)
GET;
        $this->assertStringContainsString($expectedGet, $responseBody);
    }

    public function test_bulk_request_leaves_offending_string_entry_untouched()
    {
        $body = '{"requests":["?idsite=1&rec=1&action_name=clean","?idsite=1&rec=1&country=ru"]}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // The clean entry is injected; the offending entry (country, no token) is left verbatim so
        // Matomo rejects it - it receives no cip/token from us.
        $this->assertStringContainsString('action_name=clean&cip=', $responseBody);
        $this->assertStringContainsString('"?idsite=1&rec=1&country=ru"', $responseBody);
        $this->assertEquals(1, substr_count($responseBody, 'token_auth=<token>'));
    }

    public function test_bulk_request_with_offending_entry_does_not_set_top_level_token()
    {
        // One clean entry + one offending entry (country, no token). A top-level token would
        // authorize EVERY entry, so the offending entry must keep us from setting one - we fall back
        // to per-entry injection on the clean entry only.
        $body = '{"requests":["?idsite=1&rec=1&action_name=clean","?idsite=1&rec=1&country=ru"]}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // The clean entry still gets its own per-entry cip + token (URL form), the offending entry is
        // left verbatim, and crucially there is no batch-level token (JSON form) authorizing them all.
        $this->assertStringContainsString('action_name=clean&cip=', $responseBody);
        $this->assertEquals(1, substr_count($responseBody, 'token_auth=<token>'));
        $this->assertStringContainsString('"?idsite=1&rec=1&country=ru"', $responseBody);
        $this->assertStringNotContainsString('"token_auth":"<token>"', $responseBody);
    }

    public function test_bulk_request_leaves_offending_object_entry_untouched()
    {
        $body = '{"requests":[{"idsite":"1","rec":"1","action_name":"clean"},{"idsite":"1","cip":"6.6.6.6"}]}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // Clean object entry gets cip + token injected (cip key appended right after action_name).
        $this->assertStringContainsString('"action_name":"clean","cip":', $responseBody);
        $this->assertStringContainsString('"token_auth":"<token>"', $responseBody);
        // The offending object entry (cip, no token) is left exactly as sent.
        $this->assertStringContainsString('{"idsite":"1","cip":"6.6.6.6"}', $responseBody);
    }

    public function test_bulk_request_with_top_level_token_injects_cip_only()
    {
        $body = '{"requests":["?idsite=1&rec=1&action_name=one"],"token_auth":"client-token"}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // The client authenticates the batch with its body token: clean entries get cip (which
        // that token authorizes) but NOT our token; the client's body token is preserved.
        $this->assertStringContainsString('action_name=one&cip=', $responseBody);
        $this->assertStringNotContainsString('token_auth=', $responseBody);
        $this->assertStringContainsString('"token_auth":"client-token"', $responseBody);
    }

    public function test_bulk_request_with_url_token_is_relocated_into_body()
    {
        $body = '{"requests":["?idsite=1&rec=1&action_name=one"],"send_image":0}';

        $response = $this->send(
            'raw_input=1&token_auth=client-token',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // The client authenticates via the URL token. Matomo reads the bulk token only from the body,
        // so we relocate the client's token into the body top level (and drop it from the outer query).
        // Clean entries get cip - which that token authorizes - but never our own token.
        $this->assertStringContainsString('action_name=one&cip=', $responseBody);
        $this->assertStringNotContainsString('<token>', $responseBody);
        $this->assertStringContainsString('"token_auth":"client-token"', $responseBody);
        // The client token was moved out of the outer query into the body, not left in $_GET.
        $this->assertStringNotContainsString("'token_auth' => 'client-token'", $responseBody);
    }

    public function test_forward_header_mode_does_not_inject_cip_or_token_for_single_request()
    {
        $response = $this->send(
            'idsite=1&action_name=clean',
            null,
            null,
            ['X-Test-Ip-Forward-Header' => 'X-Forwarded-For']
        );

        $responseBody = $this->getBody($response);

        // With an IP-forward header configured the proxy injects nothing; the visitor IP is sent
        // in the configured header (for GET too), and Matomo derives the IP from the connection.
        $expected = <<<RESPONSE
array (
  'idsite' => '1',
  'action_name' => 'clean',
)
array (
  'X_FORWARDED_FOR' => '127.0.0.1',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_forward_header_mode_forwards_bulk_body_verbatim()
    {
        $body = '{"requests":["?idsite=1&rec=1&country=ru"],"send_image":0}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded', 'X-Test-Ip-Forward-Header' => 'X-Forwarded-For'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // No rewriting in forward-header mode: the body (including the offending entry) is passed
        // through unchanged and the proxy injects no cip/token.
        $this->assertStringContainsString('RAW: ' . $body, $responseBody);
        $this->assertStringNotContainsString('cip=', $responseBody);
        $this->assertStringNotContainsString('token_auth=', $responseBody);
    }

    public function test_bulk_request_leaves_offending_object_entry_with_country_untouched()
    {
        $body = '{"requests":[{"idsite":"1","action_name":"clean"},{"idsite":"1","country":"ru"}]}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        // Clean object entry gets cip + token; the offending object entry (country) is verbatim.
        $this->assertStringContainsString('"action_name":"clean","cip":', $responseBody);
        $this->assertStringContainsString('"token_auth":"<token>"', $responseBody);
        $this->assertStringContainsString('{"idsite":"1","country":"ru"}', $responseBody);
    }

    public function test_post_form_with_client_cip_is_not_overridden_and_gets_no_token()
    {
        $response = $this->send(
            'foo=bar',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            'cip=6.6.6.6&action_name=x'
        );

        $responseBody = $this->getBody($response);

        // Client cip in the POST body: the proxy adds neither its own cip nor a token.
        $expected = <<<RESPONSE
array (
  'foo' => 'bar',
)
array (
  'cip' => '6.6.6.6',
  'action_name' => 'x',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_bulk_request_with_invalid_json_is_forwarded_unchanged()
    {
        // Contains "requests" (so it's treated as bulk) but is not decodable: forward verbatim.
        $body = '{"requests":[}';

        $response = $this->send(
            'raw_input=1',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded'],
            null,
            'POST',
            $body
        );

        $responseBody = $this->getBody($response);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('RAW: ' . $body, $responseBody);
        $this->assertStringNotContainsString('cip=', $responseBody);
        $this->assertStringNotContainsString('token_auth=', $responseBody);
    }

    public function test_forward_header_mode_does_not_inject_for_single_post()
    {
        $response = $this->send(
            'foo=bar',
            null,
            null,
            ['content-type' => 'application/x-www-form-urlencoded', 'X-Test-Ip-Forward-Header' => 'X-Forwarded-For'],
            null,
            'POST',
            'action_name=clean'
        );

        $responseBody = $this->getBody($response);

        // Forward-header mode: nothing injected, body forwarded, visitor IP only in the header.
        $expected = <<<RESPONSE
array (
  'foo' => 'bar',
)
array (
  'action_name' => 'clean',
)
array (
  'X_FORWARDED_FOR' => '127.0.0.1',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_debug_requests_are_scrubbed_properly()
    {
        $response = $this->send('debug=1');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'debug' => '1',
)
HOST: proxy
URL: http://proxy:8080/
TOKEN_AUTH: <token>

RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_indexphp_requests_are_proxied_correctly()
    {
        $response = $this->send('module=CoreAdminHome&action=optOut', null, null, null, '/matomo-proxy.php');

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
in index.php
array (
  'module' => 'CoreAdminHome',
  'action' => 'optOut',
)

RESPONSE;

        $expected .= '...some html here... <script src="' . $this -> getProxyUrl() . 'matomo-proxy.php?file=plugins/CoreAdminHome/javascripts/optOut.js">...more html here...';

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_optOutJsphp_requests_are_proxied_correctly()
    {
        $response = $this->send('file=plugins/CoreAdminHome/javascripts/optOut.js', null, null, null, '/matomo-proxy.php');

        $responseBody = $this->getBody($response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('this is optOut.js', $responseBody);
    }

    public function test_proxy_works_with_ipv6()
    {
        $response = $this->send('foo=bar', null, null, null, null, 'GET', null, true);

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => '<token>',
  'foo' => 'bar',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    public function test_indexphp_blocked_requests_are_not_proxied()
    {
        $response = $this->send('module=Something&action=else', null, null, null, '/matomo-proxy.php');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_indexphp_blocked_post_requests_are_not_proxied()
    {
        $response = $this->send('module=Something&action=else', null, null, null, '/matomo-proxy.php', 'POST');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_indexphp_requests_with_invalid_file_are_not_proxied_even_if_method_is_allowed()
    {
        $response = $this->send(
            'module=CoreAdminHome&action=optOut&file=plugins/CoreAdminHome/javascripts/notAllowed.js',
            null,
            null,
            null,
            '/matomo-proxy.php'
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_indexphp_post_requests_with_invalid_file_are_not_proxied_even_if_method_is_allowed()
    {
        $response = $this->send(
            'module=CoreAdminHome&action=optOut&file=plugins/CoreAdminHome/javascripts/notAllowed.js',
            null,
            null,
            null,
            '/matomo-proxy.php',
            'POST'
        );
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_indexphp_empty_requests_are_not_proxied()
    {
        $response = $this->send('', null, null, null, '/matomo-proxy.php');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_indexphp_empty_post_requests_are_not_proxied()
    {
        $response = $this->send('', null, null, null, '/matomo-proxy.php', 'POST');
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_proxy_works_with_ipv6_in_header()
    {
        $headers = ['X-Forwarded-For' => '0:0:0:0:0:ffff:172d:4300'];
        $response = $this->send('foo=bar', null, null, $headers, null, 'GET',
            null, true);

        $responseBody = $this->getBody($response);

        $expected = <<<RESPONSE
array (
  'cip' => '0:0:0:0:0:ffff:172d:4300',
  'token_auth' => '<token>',
  'foo' => 'bar',
)
RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $responseBody);
    }

    private function send($query = null, DateTime $modifiedSince = null, $matomoUrl = null, $addHeaders = null, $path = null,
                          $method = 'GET', $body = null, $forceIpV6 = false)
    {
        if(is_null($matomoUrl)) {
            $matomoUrl = $this->getMatomoUrl();
        }

        $client = new Client([
            GuzzleHttp\RequestOptions::HTTP_ERRORS => false
        ]);

        if (!$path) {
            $path = '/matomo.php';
        }

        if ($query) {
            $query = '?' . $query;
        }

        $headers = [];
        if ($modifiedSince) {
            $headers['If-Modified-Since'] = $modifiedSince->format(DateTime::RFC850);
        }

        if($addHeaders) {
            $headers = array_merge($headers, $addHeaders);
        }

        if (!empty($body)) {
            $headers['content-length'] = strlen($body);
        }

        $requestOptions = [
            'headers' => $headers,
            'body' => $body,
        ];

        if ($forceIpV6) {
            $requestOptions['config'] = [
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6],
            ];
        }
        $response = $client->request($method, $matomoUrl . $path . $query, $requestOptions);

        return $response;
    }

    private function getMatomoUrl()
    {
        $pathConfig = "./config.php";
        if(!file_exists($pathConfig)) {
            if(file_exists("./config.php.save")) {
                throw new Exception("Rename config.php.save to config.php and try again.");
            }

            throw new Exception("To run tests, create config.php with following content:
<?php
\$MATOMO_URL = 'http://localhost/tracker-proxy/tests/server/';
\$PROXY_URL = 'http://localhost/tracker-proxy/';
\$TOKEN_AUTH = 'xyz';
\$timeout = 5;
");
        }
        require $pathConfig;
        $MATOMO_URL = str_replace('/tests/server/', '', $MATOMO_URL);
        return $MATOMO_URL;
    }

    private function getProxyUrl()
    {
        $pathConfig = "./config.php";
        if(!file_exists($pathConfig)) {
            if(file_exists("./config.php.save")) {
                throw new Exception("Rename config.php.save to config.php and try again.");
            }

            throw new Exception("To run tests, create config.php with following content:
<?php
\$MATOMO_URL = 'http://localhost/tracker-proxy/tests/server/';
\$PROXY_URL = 'http://localhost/tracker-proxy/';
\$TOKEN_AUTH = 'xyz';
\$timeout = 5;
");
        }
        require $pathConfig;
        return $PROXY_URL;
    }

    private function getBody($response)
    {
        $responseBody = $response->getBody()->getContents();

        // 127.0.0.1 may appear as ::1
        return str_replace('::1', '127.0.0.1', $responseBody);
    }
}
