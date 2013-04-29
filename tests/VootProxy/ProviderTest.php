<?php

require_once dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "_autoload.php";

use \VootProxy\Provider as Provider;
use \VootProxy\ProviderException as ProviderException;

class ProviderTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider validProviders
     */
    public function testValidProviders(array $provider)
    {
        Provider::fromArray($provider);
    }

    /**
     * @dataProvider doesPassFilter
     */
    public function testDoesPassFilter(array $toFilter, array $filter)
    {
        $p = Provider::fromArray(array("id" => "foo", "endpoint" => "http://example.org/foo", "filter" => $filter));
        $this->assertTrue($p->passFilter($toFilter));
    }

    /**
     * @dataProvider invalidProviders
     */
    public function testInvalidProvider(array $provider, $message)
    {
        try {
            Provider::fromArray($provider);
            $this->assertTrue(FALSE);
        } catch (ProviderException $e) {
            $this->assertEquals($message, $e->getMessage());
        }
    }

    /**
     * @dataProvider doesNotPassFilter
     */
    public function testDoesNotPassFilter(array $toFilter, array $filter)
    {
        $p = Provider::fromArray(array("id" => "foo", "endpoint" => "http://example.org/foo", "filter" => $filter));
        $this->assertFalse($p->passFilter($toFilter));
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
                "id must be set"
            ),
            array(
                array("id" => "foo"),
                "endpoint must be set"
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
                "contact_email must be a valid email address"
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
                array("id" => "foo", "endpoint" => "http://www.example.org/foo", "basic_user" => "abc", "basic_pass" => "xyz", "filter" => array(0,1,2,3)),
                "filter entries must be strings"
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
