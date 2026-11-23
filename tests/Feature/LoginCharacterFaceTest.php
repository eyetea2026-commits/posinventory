<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginCharacterFaceTest extends TestCase
{
    public function test_the_eyes_and_mouth_sit_inside_the_face(): void
    {
        $html = $this->get(route('welcome'))->assertOk()->getContent();

        $top = function (string $selector) use ($html): float {
            preg_match('/'.preg_quote($selector, '/').' \{\s*position: absolute;\s*left: [\d.]+%;\s*top: ([\d.]+)%;/', $html, $match);
            $this->assertNotEmpty($match, "No position for $selector");

            return (float) $match[1];
        };

        $eyebrow = $top('.lv2-eyebrow');
        $eye = $top('.lv2-eye');
        $mouth = $top('.lv2-mouth');

        $this->assertSame([21.5, 23.7, 29.3], [$eyebrow, $eye, $mouth]);

        $this->assertStringContainsString('.lv2-eye--right { left: 72%; }', $html);
        $this->assertMatchesRegularExpression('/\.lv2-eye \{\s*position: absolute;\s*left: 67\.4%;/', $html);
        $this->assertMatchesRegularExpression('/\.lv2-mouth \{\s*position: absolute;\s*left: 70\.7%;/', $html);
        $this->assertTrue($eyebrow > 18.6 && $eyebrow < $eye && $eye < $mouth && $mouth < 32.0);
    }
}
