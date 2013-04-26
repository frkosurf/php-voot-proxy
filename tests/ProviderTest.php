<?php

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "VootProxy" . DIRECTORY_SEPARATOR . "Provider.php";
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "VootProxy" . DIRECTORY_SEPARATOR . "ProviderException.php";

use \VootProxy\Provider as Provider;
use \VootProxy\ProviderException as ProviderException;

class ProviderTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider validProviders
     */
    public function testValidProviders(array $provider)
    {
        Provider::validate($provider);
    }

    /**
     * @dataProvider doesPassFilter
     */
    public function testDoesPassFilter(array $data, array $filter)
    {
        Provider::passFilter($data, $filter);
    }

    /**
     * @dataProvider invalidProviders
     */
    public function testInvalidProvider(array $provider, $message)
    {
        try {
            Provider::validate($provider);
            $this->assertTrue(FALSE);
        } catch (ProviderException $e) {
            $this->assertEquals($message, $e->getMessage());
        }
    }

    /**
     * @dataProvider doesNotPassFilter
     */
    public function testDoesNotPassFilter(array $data, array $filter)
    {
        try {
            Provider::passFilter($data, $filter);
            $this->assertTrue(FALSE);
        } catch (ProviderException $e) {
            $this->assertEquals("filter not passed", $e->getMessage());
        }
    }

    public function validProviders()
    {
        return array (
            array(
              array ("id" => "foo", "endpoint" => "http://www.example.org/foo"),
            ),
            array(
                array ("id" => "foo", "endpoint" => "http://www.example.org/foo", "contact_email" => "foo@example.org"),
            ),
            array(
                array ("id" => "foo", "endpoint" => "http://www.example.org/foo", "contact_email" => "foo@example.org", "basic_user" => "foo", "basic_pass" => "bar", "filter" => array("x", "y", "z")),
            ),
        );
    }

    public function invalidProviders()
    {
        return array(
            array(
                array(),
                "missing required parameter 'id'"
            ),
            array(
                array("id" => "foo"),
                "missing required parameter 'endpoint'"
            ),
            array(
                array("id" => "foo", "endpoint" => "xyz"),
                "endpoint should be valid URL"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo#xyz"),
                "endpoint cannot contain a fragment"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "contact_email" => "foo"),
                "contact email should be valid email address"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "basic_user" => "xyz:abc"),
                "invalid character in 'basic_user'"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "basic_user" => "é"),
                "invalid character(s) in 'basic_user'"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "basic_user" => "abc", "basic_pass" => "abc:xyz"),
                "invalid character in 'basic_pass'"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "basic_user" => "abc", "basic_pass" => "xyz", "filter" => "abc"),
                "filter must be array"
            ),
            array(
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "basic_user" => "abc", "basic_pass" => "xyz", "filter" => array(0,1,2,3)),
                "elements of filter must be strings"
            ),

        );
    }

    public function doesPassFilter()
    {
        return array(
            array(
                array("foo"),
                array("foo", "bar")
            ),
            array(
                array("foo"),
                array()
            ),
            array(
                array(),
                array()
            ),
        );
    }

    public function doesNotPassFilter()
    {
        return array(
            array(
                array(),
                array("foo", "bar"),
            ),
            array(
                array("foo"),
                array("bar", "baz"),
            ),
        );
    }
}
