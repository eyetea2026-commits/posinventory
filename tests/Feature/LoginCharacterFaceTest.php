<?php

namespace Tests\Feature;

use Tests\TestCase;

// The login character's eyes, eyebrows and mouth are drawn over a faceless
// picture (Images/Emoji.png), placed as % of it. The face runs from the
// hairline (about 18.6% down) to the chin (32%): eyebrows, then eyes just
// under halfway, then the mouth about three quarters down, above the chin.
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

        // Across the face: eyes at 67.4% and 72%, mouth just right of their middle.
        $this->assertStringContainsString('.lv2-eye--right { left: 72%; }', $html);
        $this->assertMatchesRegularExpression('/\.lv2-eye \{\s*position: absolute;\s*left: 67\.4%;/', $html);
        $this->assertMatchesRegularExpression('/\.lv2-mouth \{\s*position: absolute;\s*left: 70\.7%;/', $html);
        // In order down the face, all between the hairline and the chin.
        $this->assertTrue($eyebrow > 18.6 && $eyebrow < $eye && $eye < $mouth && $mouth < 32.0);
    }
}
