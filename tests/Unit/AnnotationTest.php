<?php

namespace Yarunoka\Tests\Unit;

use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Schedule\AllDay;
use Yarunoka\YrnkBuilder;
use Yarunoka\YrnkEvaluator;
use Yarunoka\YrnkParser;
use Yarunoka\YrnkSchedule;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AnnotationTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $documentExtra
     * @param  array<string, mixed>  $scheduleExtra
     * @return array<string, mixed>
     */
    private static function document(array $documentExtra = [], array $scheduleExtra = []): array
    {
        return [
            ...$documentExtra,
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'schedules' => [
                [...$scheduleExtra, 'days' => ['mon'], 'times' => ['10:00']],
            ],
        ];
    }

    #[Test]
    public function parses_annotations_on_the_document_and_on_a_schedule(): void
    {
        $document = (new YrnkParser())->parse(self::document(
            ['label' => 'Company calendar', 'description' => "Weekly rules.\nMaintained by ops."],
            ['label' => 'Monday standup', 'description' => 'Every Monday at ten.'],
        ));

        $this->assertSame('Company calendar', $document->label);
        $this->assertSame("Weekly rules.\nMaintained by ops.", $document->description);
        $this->assertSame('Monday standup', $document->schedules[0]->label);
        $this->assertSame('Every Monday at ten.', $document->schedules[0]->description);
    }

    #[Test]
    public function a_labeled_schedule_answers_exactly_like_a_bare_one(): void
    {
        $bare = (new YrnkParser())->parse(self::document());
        $labeled = (new YrnkParser())->parse(self::document([], ['label' => 'Monday standup']));

        foreach (['2026-07-27T10:00:00+09:00', '2026-07-27T11:00:00+09:00'] as $at) {
            $instant = new DateTimeImmutable($at);

            $this->assertSame(
                YrnkEvaluator::fromYrnk($bare)->matches($bare->schedules[0], $instant),
                YrnkEvaluator::fromYrnk($labeled)->matches($labeled->schedules[0], $instant),
            );
        }
    }

    #[Test]
    public function the_builder_round_trips_annotations_and_leads_with_them(): void
    {
        $raw = self::document(
            ['label' => 'Company calendar'],
            ['label' => 'Monday standup', 'description' => 'Every Monday at ten.'],
        );

        $this->assertSame($raw, (new YrnkBuilder())->build((new YrnkParser())->parse($raw)));
    }

    #[Test]
    public function accepts_japanese_and_an_emoji_zwj_sequence(): void
    {
        $document = (new YrnkParser())->parse(self::document([], ['label' => '給料日の振込予定 👨‍👩‍👧']));

        $this->assertSame('給料日の振込予定 👨‍👩‍👧', $document->schedules[0]->label);
    }

    #[Test]
    public function accepts_a_label_of_exactly_the_cap_counted_in_code_points(): void
    {
        $label = str_repeat('あ', 100);
        $document = (new YrnkParser())->parse(self::document([], ['label' => $label]));

        $this->assertSame($label, $document->schedules[0]->label);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function rejectedDocuments(): array
    {
        return [
            'empty label' => [self::document([], ['label' => ''])],
            'whitespace-only label' => [self::document([], ['label' => '   '])],
            'label with a newline' => [self::document([], ['label' => "two\nlines"])],
            'label over 100 code points' => [self::document([], ['label' => str_repeat('a', 101)])],
            'description over 1000 code points' => [self::document(['description' => str_repeat('a', 1001)])],
            'description with CRLF' => [self::document(['description' => "first\r\nsecond"])],
            'label with a bidi override' => [self::document([], ['label' => "pay\u{202E}day"])],
            'label with a zero-width space' => [self::document([], ['label' => "pay\u{200B}day"])],
            'document label with a tab' => [self::document(['label' => "a\tb"])],
            'non-string label' => [self::document([], ['label' => 25])],
            'date_sets key label is reserved' => [[
                'version' => '1.0',
                'timezone' => 'Asia/Tokyo',
                'calendar' => ['date_sets' => ['label' => ['2026-10-01']]],
                'schedules' => [['days' => ['mon'], 'times' => ['10:00']]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    #[Test]
    #[DataProvider('rejectedDocuments')]
    public function rejects_a_document_that_breaks_the_annotation_rules(array $raw): void
    {
        $this->expectException(InvalidYrnkException::class);

        (new YrnkParser())->parse($raw);
    }

    #[Test]
    public function a_built_schedule_is_held_to_the_same_rules_as_a_parsed_one(): void
    {
        $this->expectException(InvalidValueException::class);

        new YrnkSchedule(times: new AllDay(), label: '   ');
    }
}
