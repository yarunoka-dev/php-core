<?php

namespace Yarunoka\Internal;

use Yarunoka\Exceptions\InvalidValueException;

/**
 * The rules the annotation fields (label / description) are held to, at
 * the document and schedule levels alike. Annotations are inert — the
 * language never reads them — so the rules protect only their reading by
 * humans: no control characters (description may break lines with LF),
 * none of the invisible characters that can spoof what a reader sees,
 * and a generous length cap.
 *
 * @internal
 */
final class Annotation
{
    public const int LABEL_MAX = 100;

    public const int DESCRIPTION_MAX = 1000;

    /**
     * ZWSP, the word joiner, the BOM, and the bidi embedding / override /
     * isolate controls. ZWJ/ZWNJ and the bidi marks stay legal: emoji
     * sequences and several scripts cannot be written without them.
     */
    private const string INVISIBLES = '/[\x{200B}\x{2060}\x{FEFF}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u';

    public static function ensureLabel(string $value): void
    {
        self::ensure('label', $value, self::LABEL_MAX, '/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u');
    }

    public static function ensureDescription(string $value): void
    {
        // LF is carved out of the C0 range: the one permitted line break.
        self::ensure('description', $value, self::DESCRIPTION_MAX, '/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}-\x{009F}]/u');
    }

    private static function ensure(string $field, string $value, int $max, string $controls): void
    {
        // The /u probe doubles as UTF-8 validation: preg fails the whole
        // subject on malformed input, so nothing below sees one.
        if (preg_match('/\S/u', $value) !== 1) {
            throw new InvalidValueException("{$field} must contain a non-whitespace character (omit the key for no annotation)");
        }

        // Counted in code points, the unit the spec counts in. preg is
        // used instead of mb_strlen so the package keeps requiring no
        // extensions.
        $length = (int) preg_match_all('/./su', $value);

        if ($length > $max) {
            throw new InvalidValueException("{$field} cannot be longer than {$max} characters: {$length}");
        }

        if (preg_match($controls, $value) === 1) {
            $lineBreak = $field === 'description' ? ' (LF is the only permitted line break)' : '';

            throw new InvalidValueException("{$field} cannot contain control characters{$lineBreak}");
        }

        if (preg_match(self::INVISIBLES, $value) === 1) {
            throw new InvalidValueException("{$field} cannot contain invisible characters (ZWSP, word joiner, BOM, or bidi controls)");
        }
    }
}
