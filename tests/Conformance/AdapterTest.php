<?php

namespace Yarunoka\Tests\Conformance;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Verifies the conformance kit adapter: each of the three eval queries
 * answered in the wire shape of the kit's protocol, union composition
 * over the schedules list, the invalid answer for a document the
 * implementation rejects, and breakage (an exception) for everything the
 * adapter does not support. The date facts follow the actual 2026
 * calendar (7/25 Sat).
 */
class AdapterTest extends TestCase
{
    // ---- the judgment at a point ----

    #[Test]
    public function answers_true_for_a_point_query_on_an_occurrence(): void
    {
        $response = (new Adapter())->handle($this->request(
            $this->document(['days' => [25], 'times' => ['10:00']]),
            ['type' => 'point', 'at' => '2026-07-25T10:00:00+09:00'],
        ));

        $this->assertSame(['result' => true], $response);
    }

    #[Test]
    public function answers_false_for_a_point_query_off_every_occurrence(): void
    {
        $response = (new Adapter())->handle($this->request(
            $this->document(['days' => [25], 'times' => ['10:00']]),
            ['type' => 'point', 'at' => '2026-07-25T10:00:01+09:00'],
        ));

        $this->assertSame(['result' => false], $response);
    }

    #[Test]
    public function a_point_query_asks_every_schedule_of_the_union(): void
    {
        $response = (new Adapter())->handle($this->request(
            $this->document(
                ['days' => [1], 'times' => ['09:00']],
                ['days' => [25], 'times' => ['10:00']],
            ),
            ['type' => 'point', 'at' => '2026-07-25T10:00:00+09:00'],
        ));

        $this->assertSame(['result' => true], $response);
    }

    // ---- the judgment over a period ----

    #[Test]
    public function answers_the_period_judgment(): void
    {
        $document = $this->document(['days' => [25], 'times' => ['10:00']]);

        $covering = (new Adapter())->handle($this->request($document, [
            'type' => 'period',
            'after' => '2026-07-24T00:00:00+09:00',
            'through' => '2026-07-26T00:00:00+09:00',
        ]));
        $missing = (new Adapter())->handle($this->request($document, [
            'type' => 'period',
            'after' => '2026-07-26T00:00:00+09:00',
            'through' => '2026-07-28T00:00:00+09:00',
        ]));

        $this->assertSame(['result' => true], $covering);
        $this->assertSame(['result' => false], $missing);
    }

    // ---- the enumeration ----

    #[Test]
    public function an_enumeration_merges_the_schedules_in_ascending_order(): void
    {
        $response = (new Adapter())->handle($this->request(
            $this->document(
                ['days' => [26], 'times' => ['09:00']],
                ['days' => [25], 'allday' => true],
                ['days' => [25], 'times' => ['10:00']],
            ),
            ['type' => 'enumeration', 'from' => '2026-07-01T00:00:00+09:00', 'through' => '2026-07-31T23:59:59+09:00'],
        ));

        $this->assertSame(['result' => [
            '2026-07-25',
            '2026-07-25T10:00:00+09:00',
            '2026-07-26T09:00:00+09:00',
        ]], $response);
    }

    #[Test]
    public function an_enumeration_answers_a_shared_occurrence_once(): void
    {
        $response = (new Adapter())->handle($this->request(
            $this->document(
                ['days' => [25], 'times' => ['10:00']],
                ['days' => ['sat'], 'times' => ['10:00']],
            ),
            ['type' => 'enumeration', 'from' => '2026-07-25T00:00:00+09:00', 'through' => '2026-07-25T23:59:59+09:00'],
        ));

        $this->assertSame(['result' => ['2026-07-25T10:00:00+09:00']], $response);
    }

    #[Test]
    public function an_enumeration_keeps_an_allday_day_before_a_timed_point_at_its_start(): void
    {
        // The two kinds never merge: the day and the 00:00 instant are
        // distinct occurrences, the day first.
        $response = (new Adapter())->handle($this->request(
            $this->document(
                ['days' => [25], 'times' => ['00:00']],
                ['days' => [25], 'allday' => true],
            ),
            ['type' => 'enumeration', 'from' => '2026-07-25T00:00:00+09:00', 'through' => '2026-07-25T23:59:59+09:00'],
        ));

        $this->assertSame(['result' => [
            '2026-07-25',
            '2026-07-25T00:00:00+09:00',
        ]], $response);
    }

    // ---- bindings ----

    #[Test]
    public function registers_each_binding_as_a_resolver_answering_its_list(): void
    {
        $response = (new Adapter())->handle($this->request(
            $this->document(['days' => ['company-closures'], 'allday' => true]) + ['resolvers' => ['company-closures']],
            ['type' => 'enumeration', 'from' => '2026-08-01T00:00:00+09:00', 'through' => '2026-08-31T23:59:59+09:00'],
            ['company-closures' => ['2026-08-05', '2026-08-14']],
        ));

        $this->assertSame(['result' => ['2026-08-05', '2026-08-14']], $response);
    }

    #[Test]
    public function answers_invalid_when_a_binding_name_cannot_be_bound(): void
    {
        // Registering is the implementation rejecting, so a name the
        // container refuses ("mon" is reserved) is an invalid answer, not
        // breakage.
        $response = (new Adapter())->handle($this->request(
            $this->document(['days' => ['mon'], 'allday' => true]) + ['resolvers' => ['mon']],
            ['type' => 'point', 'at' => '2026-07-27T10:00:00+09:00'],
            ['mon' => ['2026-07-27']],
        ));

        $this->assertSame(['invalid' => true], $response);
    }

    // ---- the invalid answer ----

    #[Test]
    public function answers_invalid_for_a_document_the_parser_rejects(): void
    {
        $document = $this->document(['days' => [25], 'times' => ['10:00']]);
        $document['version'] = '9.9';

        $response = (new Adapter())->handle($this->request(
            $document,
            ['type' => 'point', 'at' => '2026-07-25T10:00:00+09:00'],
        ));

        $this->assertSame(['invalid' => true], $response);
    }

    // ---- emit ----

    #[Test]
    public function answers_an_emit_request_with_the_round_tripped_document(): void
    {
        $document = $this->document(['days' => [25], 'times' => ['10:00']]);

        $response = (new Adapter())->handle(['action' => 'emit', 'document' => $document]);

        $this->assertSame(['document' => $document], $response);
    }

    #[Test]
    public function an_emit_request_parses_with_its_bindings(): void
    {
        // A document declaring resolvers does not even parse without its
        // bindings, so emit requests carry them too (the kit's protocol).
        $document = [
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'resolvers' => ['company-closures'],
            'schedules' => [['days' => ['company-closures'], 'allday' => true]],
        ];

        $response = (new Adapter())->handle([
            'action' => 'emit',
            'document' => $document,
            'bindings' => ['company-closures' => ['2026-08-05']],
        ]);

        $this->assertSame(['document' => $document], $response);
    }

    #[Test]
    public function answers_invalid_for_an_emit_request_the_parser_rejects(): void
    {
        $document = $this->document(['days' => [25], 'times' => ['10:00']]);
        $document['version'] = '9.9';

        $response = (new Adapter())->handle(['action' => 'emit', 'document' => $document]);

        $this->assertSame(['invalid' => true], $response);
    }

    // ---- breakage ----

    #[Test]
    public function an_unknown_query_type_is_breakage(): void
    {
        $this->expectException(RuntimeException::class);

        (new Adapter())->handle($this->request(
            $this->document(['days' => [25], 'times' => ['10:00']]),
            ['type' => 'cardinality', 'at' => '2026-07-25T10:00:00+09:00'],
        ));
    }

    /**
     * @param  array<string, mixed>  ...$schedules
     * @return array<string, mixed>
     */
    private function document(array ...$schedules): array
    {
        return [
            'version' => '1.0',
            'timezone' => 'Asia/Tokyo',
            'schedules' => array_values($schedules),
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $query
     * @param  array<string, list<string>>|null  $bindings
     * @return array<string, mixed>
     */
    private function request(array $document, array $query, ?array $bindings = null): array
    {
        $request = ['action' => 'eval', 'document' => $document, 'query' => $query];

        if ($bindings !== null) {
            $request['bindings'] = $bindings;
        }

        return $request;
    }
}
