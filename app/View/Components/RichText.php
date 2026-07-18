<?php

namespace App\View\Components;

use Illuminate\View\Component;

class RichText extends Component
{
    public string $html;

    public function __construct(public string $text = '')
    {
        $this->html = self::parse($text);
    }

    public static function parse(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return nl2br(self::applyPatterns($escaped));
    }

    private static function applyPatterns(string $text): string
    {
        // *** (bold+italic) before ** before *; .+? allows stacking inner formats
        $pattern = '/`([^`|]+)(?:\|([^`]+))?`|\*\*\*(.+?)\*\*\*|\*\*(.+?)\*\*|__(.+?)__|~~(.+?)~~|\*(.+?)\*/';

        return preg_replace_callback($pattern, function (array $m): string {
            // PCRE reports non-participating groups before the matched one as '' (not absent),
            // so normalize '' → null to know which alternative actually matched.
            $m = array_map(fn ($v) => $v === '' ? null : $v, $m);
            [$full, $quote, $attr, $bolditalic, $bold, $underline, $strike, $italic] = array_pad($m, 8, null);

            if ($quote !== null) {
                return '<span style="display:block;position:relative;margin:0.5rem 0;border:1px solid rgba(161,161,170,0.35);border-left:3px solid rgb(0,123,24);border-radius:0.375rem;background:rgba(161,161,170,0.07);padding:0.7rem 0.9rem 0.6rem 2rem;">'
                    . '<span style="position:absolute;top:0.05rem;left:0.5rem;font-family:Georgia,serif;font-size:1.6rem;line-height:1;color:rgb(0,123,24);opacity:0.55;">&ldquo;</span>'
                    . '<span style="display:block;font-style:italic;color:inherit;">' . $quote . '</span>'
                    . ($attr !== null ? '<span style="display:block;margin-top:0.4rem;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;text-transform:uppercase;opacity:0.55;">&mdash; ' . $attr . '</span>' : '')
                    . '</span>';
            }

            if ($bolditalic !== null) {
                return '<strong style="font-weight:700;color:inherit;"><em>' . self::applyPatterns($bolditalic) . '</em></strong>';
            }

            if ($bold !== null) {
                return '<strong style="font-weight:700;color:inherit;">' . self::applyPatterns($bold) . '</strong>';
            }

            if ($underline !== null) {
                return '<u>' . self::applyPatterns($underline) . '</u>';
            }

            if ($strike !== null) {
                return '<s style="opacity:0.7;">' . self::applyPatterns($strike) . '</s>';
            }

            if ($italic !== null) {
                return '<em>' . self::applyPatterns($italic) . '</em>';
            }

            return $full;
        }, $text) ?? $text;
    }

    public function render()
    {
        return view('components.rich-text');
    }
}
