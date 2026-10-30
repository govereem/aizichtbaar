<?php

declare(strict_types=1);

namespace App\Scan;

final class HtmlText
{
    /**
     * The text a crawler sees without running JavaScript: scripts, styles and tags removed.
     */
    public static function visibleText(string $html): string
    {
        $withoutCode = preg_replace('#<(script|style|noscript)\b.*?</\1>#si', ' ', $html);

        return html_entity_decode(strip_tags((string) $withoutCode), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function wordCount(string $text): int
    {
        $trimmed = trim($text);

        return '' === $trimmed ? 0 : count(preg_split('/\s+/u', $trimmed));
    }
}
