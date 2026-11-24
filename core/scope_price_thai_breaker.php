<?php
declare(strict_types=1);
/**
 * Invisible WORD-BREAK OPPORTUNITIES for Thai in Microsoft Word DOCX.
 * Never inserts paragraphs or hard line breaks, and never edits the saved form.
 * ICU if enabled; otherwise a focused, included Thai dictionary matching this memo.
 */
function scopeApprovalThaiSoftBreaks(string $text): string
{
    // We add hints only in exported Word, never save them to database.
    $text = str_replace("\u{200B}", '', $text);
    return preg_replace_callback('/[\x{0E01}-\x{0E3A}\x{0E40}-\x{0E4E}]+/u',
        static function (array $match): string {
            $wordRun = $match[0];
            if (class_exists('IntlBreakIterator')) {
                $iter = IntlBreakIterator::createWordInstance('th_TH');
                if ($iter instanceof IntlBreakIterator) {
                    $iter->setText($wordRun);
                    $pieces = [];
                    $prev = 0;
                    foreach ($iter as $offset) {
                        $offset = (int) $offset; // PHP ICU returns UTF-8 byte positions
                        if ($offset <= $prev) continue;
                        $piece = substr($wordRun, $prev, $offset - $prev);
                        if ($piece !== '') $pieces[] = $piece;
                        $prev = $offset;
                    }
                    if ($prev < strlen($wordRun)) $pieces[] = substr($wordRun, $prev);
                    if ($pieces && implode('', $pieces) === $wordRun) {
                        return implode("\u{200B}", $pieces);
                    }
                }
            }
            return scopeApprovalThaiDictionaryBreaks($wordRun);
        }, $text) ?? $text;
}

/** Lexical maximum matching; ambiguous/unknown glyphs remain intact. */
function scopeApprovalThaiDictionaryBreaks(string $text): string
{
    static $dictionary = null;
    static $maxChars = 0;
    if ($dictionary === null) {
        $dictionary = [];
        $file = __DIR__ . '/scope_price_thai_words.txt';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $word) {
                $dictionary[$word] = true;
                $maxChars = max($maxChars, preg_match_all('/./us', $word));
            }
        }
    }
    if ($dictionary === []) return $text;
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars) || count($chars) < 2) return $text;
    $length = count($chars);
    $pieces = [];
    $unknown = '';
    for ($index = 0; $index < $length;) {
        $matched = null;
        $step = 0;
        for ($size = min($maxChars, $length - $index); $size >= 1; $size--) {
            $candidate = implode('', array_slice($chars, $index, $size));
            if (isset($dictionary[$candidate])) {
                $matched = $candidate;
                $step = $size;
                break;
            }
        }
        if ($matched === null) {
            $unknown .= $chars[$index++];
            continue;
        }
        if ($unknown !== '') {
            // Keep unknown prefixes with the next lexical word: do not split an unknown name.
            $matched = $unknown . $matched;
            $unknown = '';
        }
        $pieces[] = $matched;
        $index += $step;
    }
    if ($unknown !== '') {
        if ($pieces) $pieces[count($pieces) - 1] .= $unknown;
        else $pieces[] = $unknown;
    }
    return implode("\u{200B}", $pieces);
}
