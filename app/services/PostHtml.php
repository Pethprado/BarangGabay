<?php
declare(strict_types=1);

namespace App\Services;

/**
 * The one HTMLPurifier configuration for post bodies.
 *
 * Extracted from AnnouncementController so that anything writing a post body —
 * the admin form, the import path, the sample-content seeder — sanitises it
 * exactly the same way. A second copy of this whitelist that drifted from the
 * first would be a security hole that only shows up on one route.
 *
 * The whitelist names every tag AND every attribute that may survive, so
 * `style` is dropped wherever it appears. That is load-bearing twice over:
 *
 *   - XSS, the obvious one.
 *   - The theme. Quill keeps the inline styling of anything pasted into it,
 *     and a caption copied out of Facebook arrives carrying
 *     style="color: rgb(0,0,0)". Stored, that paints the post near-black on
 *     the dark card, and no stylesheet can reach an inline style. Post content
 *     must inherit the reader's theme, never bring its own palette.
 */
final class PostHtml
{
    /**
     * Tags and attributes a post body may contain.
     *
     * Note what is absent: `span`, `div`, `style`, `class`. Quill emits spans
     * carrying inline colour for pasted text, and they are dropped here.
     */
    private const ALLOWED =
        'p,br,strong,em,u,s,ul,ol,li,h1,h2,h3,h4,blockquote,a[href|target],img[src|alt|width|height]';

    /**
     * Sanitise rich text for storage.
     *
     * Falls back to returning the input untouched when HTMLPurifier is not
     * installed — the historical behaviour, kept so a missing dev dependency
     * degrades rather than fatals. In any real deployment the library is
     * present (it is a hard requirement in composer.json).
     */
    public static function purify(string $html): string
    {
        if (!class_exists(\HTMLPurifier::class)) {
            return $html;
        }

        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', self::ALLOWED);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        // Belt and braces: even if the whitelist above is ever widened to admit
        // style, colour declarations from pasted content stay out.
        $config->set('CSS.AllowedProperties', []);
        $config->set('Cache.DefinitionImpl', null);

        return (new \HTMLPurifier($config))->purify($html);
    }
}
