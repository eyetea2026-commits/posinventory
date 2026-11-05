<?php

namespace Tests\Feature;

use Tests\TestCase;

// The login greeting is read aloud by the browser's voice. Browsers block
// sound until the visitor clicks, taps or types, so when the first try on
// load doesn't start, the greeting is spoken on the first interaction.
class LoginGreetingVoiceTest extends TestCase
{
    public function test_the_greeting_is_spoken_on_load_or_on_the_first_click_tap_or_key(): void
    {
        $html = $this->get(route('welcome'))->assertOk()->getContent();

        $this->assertStringContainsString("const GREETING = 'Hello, There! Welcome to CCTV Express Tacurong';", $html);
        $this->assertStringNotContainsString('Solution', $html);
        $this->assertStringContainsString('new SpeechSynthesisUtterance(GREETING)', $html);
        // Spoken once: a voice that starts stops the retry.
        $this->assertStringContainsString("utterance.onstart = function () {\n                        greetingSpoken = true;\n                        removeVoiceRetry();", $html);
        // The retry listens for the first click, tap or key press anywhere.
        $this->assertSame(2, substr_count($html, "['pointerdown', 'keydown', 'touchstart'].forEach"));
        $this->assertStringContainsString('document.addEventListener(type, speakOnFirstInteraction, true);', $html);
        $this->assertStringContainsString('document.removeEventListener(type, speakOnFirstInteraction, true);', $html);
    }
}
