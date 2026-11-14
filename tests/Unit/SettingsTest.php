<?php
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testChoicesParsed(): void
    {
        $this->assertSame(array("closed", "invite", "open"), setting_choices("choice:closed|invite|open"));
        $this->assertSame(array(), setting_choices("string"));
        $this->assertSame(array(), setting_choices("bool"));
    }

    public function testChoiceKeepsCurrentWhenPostedValueIsUnknown(): void
    {
        $this->assertSame("open", setting_clean("choice:closed|invite|open", "open", "invite"));
        $this->assertSame("invite", setting_clean("choice:closed|invite|open", "everyone", "invite"));
    }

    public function testLengthLimits(): void
    {
        $this->assertSame(100, mb_strlen(setting_clean("string", str_repeat("é", 300), "")));
        $this->assertSame(5000, mb_strlen(setting_clean("text", str_repeat("a", 9000), "")));
        $this->assertSame("trimmed", setting_clean("string", "  trimmed  ", ""));
    }
}
