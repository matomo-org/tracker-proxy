<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ProxyTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function get_without_parameters_should_forward_piwik_js_content()
    {
        $response = $this->send();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('this is piwik.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type'));
    }

    /**
     * @test
     */
    public function piwik_js_should_not_be_updated_if_less_than_1_day()
    {
        $response = $this->send(null, $modifiedSince = new DateTime('-23 hours'));

        $this->assertEquals('', $response->getBody()->getContents());
        $this->assertEquals(304, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function piwik_js_should_be_updated_if_more_than_1_day()
    {
        $response = $this->send(null, $modifiedSince = new DateTime('-25 hours'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('this is piwik.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type'));
        $this->assertNotNull($response->getHeader('Last-Modified'));
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_piwik_php()
    {
        $response = $this->send('foo=bar');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/gif', $response->getHeader('Content-Type'));
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
        try {
            $this->send('status=404');
            $this->fail('The proxy did not return a 404 response');
        } catch (RequestException $e) {
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }

        try {
            $this->send('status=500');
            $this->fail('The proxy did not return a 500 response');
        } catch (RequestException $e) {
            $this->assertEquals(500, $e->getResponse()->getStatusCode());
        }
    }

    /**
     * @test
     */
    public function get_return_piwikJs_without_error_when_server_down()
    {
        $piwikUrl = $this->getPiwikUrl();

        // Remove config file -> piwik.php will use the default value 'http://your-piwik-domain.example.org/piwik/'
        shell_exec("mv ../config.php ../config.php.save");
        $this->assertTrue(!file_exists('../config.php'));

        $response = $this->send(null, null, $piwikUrl);

        // Restore the config file
        shell_exec("mv ../config.php.save ../config.php");
        $this->assertTrue(file_exists('../config.php'));

        $expected = '/* there was an error loading piwik.js */';
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($expected, $response->getBody()->getContents());

    }

    /**
     * @test
     */
    public function test_with_http_dnt_header()
    {
        $response = $this->send('foo=bar', null, null, array('DNT' => '1'));

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
        $response = $this->send('foo=bar', null, null, array('X-Do-Not-Track' => '1'));

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
URL: http://proxy/
TOKEN_AUTH: <token>

RESPONSE;

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(132, $response->getHeader('content-length'));
        $this->assertEquals($expected, $responseBody);
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

    private function send($query = null, DateTime $modifiedSince = null, $piwikUrl = null, $addHeaders = null, $path = null,
                          $method = 'GET', $body = null, $forceIpV6 = false)
    {
        if(is_null($piwikUrl)) {
            $piwikUrl = $this->getPiwikUrl();
        }

        $client = new Client();

        if (!$path) {
            $path = '/piwik.php';
        }

        if ($query) {
            $query = '?' . $query;
        }

        $headers = array();
        if ($modifiedSince) {
            $headers['If-Modified-Since'] = $modifiedSince->format(DateTime::RFC850);
        }

        if($addHeaders) {
            $headers = array_merge($headers, $addHeaders);
        }

        if (!empty($body)) {
            $headers['content-length'] = strlen($body);
        }

        $requestOptions = array(
            'headers' => $headers,
            'body' => $body,
        );

        if ($forceIpV6) {
            $requestOptions['config'] = [
                'curl' => [CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V6],
            ];
        }

        $request = $client->createRequest($method, $piwikUrl . $path . $query, $requestOptions);
        $response = $client->send($request);

        return $response;
    }

    private function getPiwikUrl()
    {
        $pathConfig = "../config.php";
        if(!file_exists($pathConfig)) {
            if(file_exists("../config.php.save")) {
                throw new Exception("Rename config.php.save to config.php and try again.");
            }

            throw new Exception("To run tests, create config.php with following content:
<?php
\$PIWIK_URL = 'http://localhost/tracker-proxy/tests/server/';
\$TOKEN_AUTH = 'xyz';
\$timeout = 5;
");
        }
        require $pathConfig;
        $PIWIK_URL = str_replace('tests/server/', '', $PIWIK_URL);
        return $PIWIK_URL;
    }

    private function getBody($response)
    {
        $responseBody = $response->getBody()->getContents();

        // 127.0.0.1 may appear as ::1
        return str_replace('::1', '127.0.0.1', $responseBody);
    }
}
