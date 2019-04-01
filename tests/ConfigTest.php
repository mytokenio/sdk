<?php
/**
 * File ConfigTest.php
 * @henter
 * Time: 2018-06-06 18:08
 *
 */
namespace Tests;

class ConfigTest extends \Tests\BaseTestCase
{

    public function testConfig()
    {
        $s3 = \SDK::config('s3');
        $this->assertNull($s3);

        $s3 = \SDK::config('s3', 'aaa');
        $this->assertEquals('aaa', $s3);
    }
}