<?php

namespace Yarunoka\Internal\Parser;

use Yarunoka\Exceptions\InvalidYrnkException;

/**
 * The scan that rejects duplicate member names in a document's JSON
 * text. Decoding quietly keeps one of the duplicates, so this reads the
 * text itself, before the decoded value is trusted. Names compare after
 * escape resolution — JSON decides member equality on the resolved
 * characters, never on the written bytes — which the scan gets by
 * decoding each name token on its own.
 *
 * The input is required to be valid JSON (the caller decodes it first),
 * so the scan only walks the grammar and never diagnoses malformed text.
 *
 * @internal
 */
final class DuplicateMemberScanner
{
    private int $position = 0;

    private function __construct(private readonly string $json) {}

    public static function scan(string $json): void
    {
        (new self($json))->value();
    }

    private function value(): void
    {
        $this->skipWhitespace();

        match ($this->json[$this->position] ?? '') {
            '{' => $this->object(),
            '[' => $this->list(),
            '"' => $this->string(),
            default => $this->scalar(),
        };
    }

    private function object(): void
    {
        $this->position++;
        $this->skipWhitespace();

        if (($this->json[$this->position] ?? '') === '}') {
            $this->position++;

            return;
        }

        $seen = [];

        while (true) {
            $this->skipWhitespace();
            $name = $this->string();

            if (isset($seen[$name])) {
                throw new InvalidYrnkException("An object writes the same member name twice: {$name}");
            }

            $seen[$name] = true;

            $this->skipWhitespace();
            $this->position++; // the colon
            $this->value();
            $this->skipWhitespace();

            if ($this->json[$this->position++] === '}') {
                return;
            }
        }
    }

    private function list(): void
    {
        $this->position++;
        $this->skipWhitespace();

        if (($this->json[$this->position] ?? '') === ']') {
            $this->position++;

            return;
        }

        while (true) {
            $this->value();
            $this->skipWhitespace();

            if ($this->json[$this->position++] === ']') {
                return;
            }
        }
    }

    /**
     * Walks over a string token and answers its resolved characters (the
     * token decoded on its own, so escape spellings compare equal).
     */
    private function string(): string
    {
        $start = $this->position;
        $this->position++;

        while (true) {
            $byte = $this->json[$this->position];

            if ($byte === '\\') {
                $this->position += 2;

                continue;
            }

            $this->position++;

            if ($byte === '"') {
                break;
            }
        }

        /** @var string $name The token is a JSON string, so its own decode is one */
        $name = json_decode(substr($this->json, $start, $this->position - $start));

        return $name;
    }

    private function scalar(): void
    {
        while ($this->position < strlen($this->json)
            && ! str_contains(",]} \t\n\r", $this->json[$this->position])) {
            $this->position++;
        }
    }

    private function skipWhitespace(): void
    {
        while (str_contains(" \t\n\r", $this->json[$this->position] ?? '-')) {
            $this->position++;
        }
    }
}
