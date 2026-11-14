<?php
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    public function testParsesAwkwardValues(): void
    {
        $file = tempnam(sys_get_temp_dir(), "env");
        file_put_contents($file, "# comment\nOOTD_TEST_A=plain\nOOTD_TEST_B=\"quoted value\"\nOOTD_TEST_C=p@ss!word{}=(x)\n\nnot a line\n");
        environment_load($file);
        unlink($file);
        $this->assertSame("plain", $_ENV['OOTD_TEST_A']);
        $this->assertSame("quoted value", $_ENV['OOTD_TEST_B']);
        $this->assertSame("p@ss!word{}=(x)", $_ENV['OOTD_TEST_C']);
    }
}
