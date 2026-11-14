<?php
use PHPUnit\Framework\TestCase;

class ThemeTest extends TestCase
{
    private function clean($css, $blog = true, $import = null, $kept = array())
    {
        return ThemeCss::clean($css, $blog, $import, $kept);
    }

    public function testKeepsAllowedRules(): void
    {
        list($css, $problems) = $this->clean(".header h1 { color: #C00; letter-spacing: 0.05em }\n.btn{border-radius:4px; border: 2px solid var(--accent)}");
        $this->assertSame(".header h1 { color: #c00; letter-spacing: 0.05em; }\n.btn { border-radius: 4px; border: 2px solid var(--accent); }", $css);
        $this->assertSame(array(), $problems);
    }

    public function testDropsDangerousThings(): void
    {
        $hostile = array(
            "@import url(https://evil.example/x.css); h1 { color: red }",
            "h1 { color: red } </style><script>alert(1)</script>",
            "h1 { background-color: expression(alert(1)) }",
            "h1 { colo\\72: red }",
            "h1 { color: #fff; } input[value^=a] { background-image: url(https://evil.example/a) }",
            "@font-face { font-family: x; src: url(https://evil.example/f.woff) }",
            "h1 { behavior: url(x.htc); -moz-binding: url(x) }",
            "h1 { content: '<script>' }",
            "h1 { font-family: \"Comic Sans\" }",
            "h1/**/{ color: red }",
            "body { color: red }"
        );
        foreach ($hostile as $input) {
            list($css) = $this->clean($input, false);
            $this->assertStringNotContainsStringIgnoringCase("evil", $css, $input);
            $this->assertStringNotContainsStringIgnoringCase("<", $css, $input);
            $this->assertStringNotContainsStringIgnoringCase("expression", $css, $input);
            $this->assertStringNotContainsStringIgnoringCase("@", $css, $input);
            $this->assertStringNotContainsStringIgnoringCase("\\", $css, $input);
            $this->assertStringNotContainsStringIgnoringCase("body", $css, $input);
            $this->assertStringNotContainsStringIgnoringCase("content", $css, $input);
        }
    }

    public function testBlogOnlySelectors(): void
    {
        list($blog) = $this->clean("body { background-color: #eee } .top-bar { border-bottom: 0 }", true);
        list($person, $problems) = $this->clean("body { background-color: #eee } .top-bar { border-bottom: 0 }", false);
        $this->assertStringContainsString("body {", $blog);
        $this->assertSame("", $person);
        $this->assertCount(2, $problems);
    }

    public function testImagesGoThroughTheImporter(): void
    {
        $seen = array();
        $import = function ($url) use (&$seen) {
            $seen[] = $url;
            if (strpos($url, "bad") !== false) {
                throw new Exception("it isn't a PNG, JPEG, GIF or WebP image");
            }
            return "/media/" . str_repeat("a", 32) . "/display";
        };
        list($css, $problems, $images) = $this->clean(".header { background-image: url(\"https://ok.example/a.png\") }\n.blog { background-image: url(https://bad.example/x) }", true, $import);
        $this->assertSame(array("https://ok.example/a.png", "https://bad.example/x"), $seen);
        $this->assertSame('.header { background-image: url("/media/' . str_repeat("a", 32) . '/display"); }', $css);
        $this->assertSame(array("/media/" . str_repeat("a", 32) . "/display"), $images);
        $this->assertStringContainsString("isn't a PNG", $problems[0]);
    }

    public function testKeptImagesAreNotFetchedAgainAndOthersLocalOnesAreRefused(): void
    {
        $mine = "/media/" . str_repeat("b", 32) . "/display";
        $theirs = "/media/" . str_repeat("c", 32) . "/display";
        $import = function ($url) {
            throw new Exception("Only https addresses can be fetched");
        };
        list($css, $problems) = $this->clean(".header { background-image: url($mine) } .blog { background-image: url($theirs) }", true, $import, array($mine));
        $this->assertSame('.header { background-image: url("' . $mine . '"); }', $css);
        $this->assertCount(1, $problems);
    }

    public function testImporterMustReturnALocalPath(): void
    {
        list($css) = $this->clean(".header { background-image: url(https://x.example/a.png) }", true, function () {
            return "https://x.example/a.png";
        });
        $this->assertSame("", $css);
    }

    public function testScopeKeepsPeopleInsideTheirPage(): void
    {
        $scoped = ThemeCss::scope(".header h1 { color: #c00; }\na:hover { color: #00f; }");
        $prefix = "body > :not(.top-bar):not(.main-nav):not(footer)";
        $this->assertSame($prefix . ":is(.header) h1, " . $prefix . " .header h1 { color: #c00; }\n"
            . $prefix . ":is(a:hover), " . $prefix . " a:hover { color: #00f; }", $scoped);
    }

    public function testColoursAndContrast(): void
    {
        $this->assertSame("#a0b1c2", theme_hex("#A0B1C2"));
        $this->assertNull(theme_hex("red"));
        $this->assertNull(theme_hex("#fff;}"));
        $this->assertEqualsWithDelta(21.0, theme_contrast("#000000", "#ffffff"), 0.01);
        $this->assertLessThan(THEME_MIN_CONTRAST, theme_contrast("#777777", "#888888"));
    }

    public function testFontNames(): void
    {
        $this->assertSame("Playfair Display", theme_font_name("  Playfair   Display "));
        $this->assertSame("", theme_font_name("Roboto\"; } body { x"));
        $this->assertSame("", theme_font_name("Roboto&family=Evil"));
        $this->assertSame("", theme_font_name(str_repeat("a", 41)));
    }

    public function testFramePolicyAllowsOnlyGoogleFonts(): void
    {
        $policy = frame_policy(array());
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.googleapis.com", $policy);
        $this->assertStringContainsString("font-src 'self' data: https://fonts.gstatic.com", $policy);
    }

    private function png($extra = "")
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        return ob_get_clean() . $extra;
    }

    public function testImageHeaders(): void
    {
        $this->assertSame("image/png", ThemeImage::check_headers(array("content-type" => "image/png", "content-length" => "100")));
        $bad = array(
            array("content-type" => "image/svg+xml"),
            array("content-type" => "text/html"),
            array(),
            array("content-type" => "image/png", "content-disposition" => "attachment; filename=x.png"),
            array("content-type" => "image/png", "set-cookie" => "id=1"),
            array("content-type" => "image/png", "refresh" => "0; url=https://evil.example"),
            array("content-type" => "image/png", "content-encoding" => "gzip"),
            array("content-type" => "image/png", "content-length" => "99999999"),
            array("content-type" => "image/png", "content-length" => "12, 13")
        );
        foreach ($bad as $headers) {
            try {
                ThemeImage::check_headers($headers);
                $this->fail("accepted " . json_encode($headers));
            } catch (Exception $e) {
                $this->assertNotSame("", $e->getMessage());
            }
        }
    }

    public function testImageBodies(): void
    {
        ThemeImage::check_body($this->png(), "image/png");
        $bad = array(
            array($this->png("PK\x03\x04polyglot zip"), "image/png"),
            array($this->png("<script>alert(1)</script>"), "image/png"),
            array("<html><body>hi</body></html>", "image/png"),
            array($this->png(), "image/jpeg"),
            array("", "image/png"),
            array("GIF89a" . str_repeat("x", 20), "image/gif")
        );
        foreach ($bad as $case) {
            try {
                ThemeImage::check_body($case[0], $case[1]);
                $this->fail("accepted a bad body as " . $case[1]);
            } catch (Exception $e) {
                $this->assertNotSame("", $e->getMessage());
            }
        }
    }

    public function testJpegAndGifEnds(): void
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagejpeg($image);
        $jpeg = ob_get_clean();
        ob_start();
        imagegif($image);
        $gif = ob_get_clean();
        ThemeImage::check_body($jpeg, "image/jpeg");
        ThemeImage::check_body($gif, "image/gif");
        $this->assertFalse(ThemeImage::ends_cleanly($jpeg . "PK\x03\x04zip", "image/jpeg"));
        $this->assertFalse(ThemeImage::ends_cleanly($gif . "trailing", "image/gif"));
    }

    public function testOwnServerIsRefused(): void
    {
        $this->assertTrue(ThemeImage::is_own_ip("194.164.126.182", array("CLOUD_SERVER_IP" => "194.164.126.182")));
        $this->assertTrue(ThemeImage::is_own_ip("93.184.216.34", array("APP_URL" => "https://93.184.216.34")));
        $this->assertFalse(ThemeImage::is_own_ip("93.184.216.35", array("APP_URL" => "https://93.184.216.34", "CLOUD_SERVER_IP" => "194.164.126.182")));
    }
}
