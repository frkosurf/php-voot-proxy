<?php

require_once dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "_autoload.php";

use \VootProxy\Provider as Provider;
use \VootProxy\RemoteProvider as RemoteProvider;

class RemoteProviderTest extends PHPUnit_Framework_TestCase
{

    public function testGroupsCall()
    {
        $p = Provider::fromArray(array("id" => "foo", "endpoint" => "file://" . dirname(__DIR__) . DIRECTORY_SEPARATOR . "data"));
        $r = new RemoteProvider(NULL);
        $groups = $r->getGroups($p, "admin");
        $this->assertEquals("member", $groups[0]['id']);
        $this->assertEquals("employee", $groups[1]['id']);
        $this->assertEquals("networkadmin", $groups[2]['id']);
    }

    public function testMembersCall()
    {
        $p = Provider::fromArray(array("id" => "foo", "endpoint" => "file://" . dirname(__DIR__) . DIRECTORY_SEPARATOR . "data"));
        $r = new RemoteProvider(NULL);
        $groups = $r->getPeople($p, "admin", "employee");
        $this->assertEquals("teacher", $groups[0]['id']);
        $this->assertEquals("admin", $groups[1]['id']);
    }
}
