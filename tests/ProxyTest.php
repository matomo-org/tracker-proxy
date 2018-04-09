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
        $response = $this->get();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('this is piwik.js', $response->getBody()->getContents());
        $this->assertEquals('application/javascript; charset=UTF-8', $response->getHeader('Content-Type'));
    }

    /**
     * @test
     */
    public function piwik_js_should_not_be_updated_if_less_than_1_day()
    {
        $response = $this->get(null, $modifiedSince = new DateTime('-23 hours'));

        $this->assertEquals('', $response->getBody()->getContents());
        $this->assertEquals(304, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function piwik_js_should_be_updated_if_more_than_1_day()
    {
        $response = $this->get(null, $modifiedSince = new DateTime('-25 hours'));

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
        $response = $this->get('foo=bar');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/gif', $response->getHeader('Content-Type'));
    }

    /**
     * @test
     */
    public function get_with_parameters_should_forward_query_parameters()
    {
        $response = $this->get('foo=bar');

        $responseBody = $response->getBody()->getContents();

        // 127.0.0.1 may appear as ::1
        $responseBody = str_replace('::1', '127.0.0.1', $responseBody);

        $expected = <<<RESPONSE
array (
  'cip' => '127.0.0.1',
  'token_auth' => 'xyz',
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
        $response = $this->get('status=204');

        $this->assertEquals(204, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function error_should_forward_error_code()
    {
        try {
            $this->get('status=404');
            $this->fail('The proxy did not return a 404 response');
        } catch (RequestException $e) {
            $this->assertEquals(404, $e->getResponse()->getStatusCode());
        }

        try {
            $this->get('status=500');
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

        $response = $this->get(null, null, $piwikUrl);

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
    public function test_without_http_dnt_and_x_do_not_track_header()
    {
        $response = $this->get();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('DNT'));
        $this->assertFalse($response->hasHeader('X_DO_NOT_TRACK'));
    }

    /**
     * @test
     */
    public function test_with_http_dnt_header()
    {
        $response = $this->get(null, null, null, array('DNT' => '1'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1', $response->getHeader('DNT'));
        $this->assertFalse($response->hasHeader('X_DO_NOT_TRACK'));
    }

    /**
     * @test
     */
    public function test_with_http_x_do_not_track_header()
    {
        $response = $this->get(null, null, null, array('X_DO_NOT_TRACK' => '1'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('DNT'));
        $this->assertEquals('1', $response->getHeader('X_DO_NOT_TRACK'));
    }


    private function get($query = null, DateTime $modifiedSince = null, $piwikUrl = null, $addHeaders = null)
    {
        if(is_null($piwikUrl)) {
            $piwikUrl = $this->getPiwikUrl();
        }

        $client = new Client();

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

        $response = $client->get($piwikUrl . '/piwik.php' . $query, array(
            'headers' => $headers,
        ));

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
}
