<?php

namespace Yarunoka\Tests\Unit\Internal;

use Yarunoka\Internal\FoldResolver;
use Yarunoka\YrnkDate;
use Yarunoka\YrnkDateTime;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FoldResolverTest extends TestCase
{
    #[Test]
    public function a_reading_that_landed_after_the_turn_back_moves_to_the_first_occurrence(): void
    {
        // PHP reads Berlin's ambiguous 02:30 of 2021-10-31 as CET, the
        // second pass of the fold.
        $second = new YrnkDateTime('2021-10-31 02:30:00', new DateTimeZone('Europe/Berlin'));
        $first = FoldResolver::firstOccurrence($second);

        $this->assertSame('2021-10-31T02:30:00+02:00', $first->format('Y-m-d\TH:i:sP'));
        $this->assertSame('Europe/Berlin', $first->getTimezone()->getName());
    }

    #[Test]
    public function a_reading_already_standing_at_the_first_occurrence_is_returned_as_is(): void
    {
        // PHP reads New York's ambiguous 01:30 of 2026-11-01 as EDT, the
        // first pass.
        $first = new YrnkDateTime('2026-11-01 01:30:00', new DateTimeZone('America/New_York'));

        $this->assertSame('2026-11-01T01:30:00-04:00', $first->format('Y-m-d\TH:i:sP'));
        $this->assertSame($first, FoldResolver::firstOccurrence($first));
    }

    #[Test]
    public function an_unambiguous_reading_is_returned_as_is(): void
    {
        $plain = new YrnkDateTime('2026-07-14 10:00:00', new DateTimeZone('Europe/Berlin'));

        $this->assertSame($plain, FoldResolver::firstOccurrence($plain));
    }

    #[Test]
    public function a_reading_pushed_out_of_a_gap_is_returned_as_is(): void
    {
        // 02:30 does not exist on 2021-03-28 in Berlin; PHP already
        // pushes it forward to 03:30 CEST, which is what the spec wants.
        $pushed = new YrnkDateTime('2021-03-28 02:30:00', new DateTimeZone('Europe/Berlin'));

        $this->assertSame('2021-03-28T03:30:00+02:00', $pushed->format('Y-m-d\TH:i:sP'));
        $this->assertSame($pushed, FoldResolver::firstOccurrence($pushed));
    }

    #[Test]
    public function a_zone_without_transitions_is_returned_as_is(): void
    {
        $utc = new YrnkDateTime('2026-07-14 10:00:00', new DateTimeZone('UTC'));

        $this->assertSame($utc, FoldResolver::firstOccurrence($utc));
    }

    #[Test]
    public function a_sub_hour_fold_moves_by_the_width_of_the_fold(): void
    {
        // Lord Howe turns back by half an hour: 02:00 +11:00 → 01:30
        // +10:30 on 2021-04-04, so ambiguous 01:45 moves back 30 minutes.
        $second = new YrnkDateTime('2021-04-04 01:45:00', new DateTimeZone('Australia/Lord_Howe'));

        $this->assertSame(
            '2021-04-04T01:45:00+11:00',
            FoldResolver::firstOccurrence($second)->format('Y-m-d\TH:i:sP'),
        );
    }

    #[Test]
    public function the_class_of_the_reading_is_preserved(): void
    {
        // Asia/Amman turned 01:00 EEST back to 00:00 EET on 2021-10-29,
        // so the day's own midnight reads as the second pass.
        $day = new YrnkDate('2021-10-29', new DateTimeZone('Asia/Amman'));
        $first = FoldResolver::firstOccurrence($day);

        $this->assertInstanceOf(YrnkDate::class, $first);
        $this->assertSame('2021-10-29T00:00:00+03:00', $first->format('Y-m-d\TH:i:sP'));
    }
}
