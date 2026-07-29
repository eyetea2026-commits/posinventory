<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\HtmlString;

trait FormatsMailBadge
{
    private function badgeHtml(string $text, string $color): HtmlString
    {
        return new HtmlString(
            "<span style=\"display:inline-block;background:{$color};color:#fff;padding:4px 14px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:0.03em;\">"
            . e($text) . '</span>'
        );
    }
}
