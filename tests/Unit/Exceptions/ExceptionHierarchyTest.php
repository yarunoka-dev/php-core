<?php

namespace Yarunoka\Tests\Unit\Exceptions;

use Yarunoka\Exceptions\ExceptionInterface;
use Yarunoka\Exceptions\InvalidCalendarDataException;
use Yarunoka\Exceptions\InvalidValueException;
use Yarunoka\Exceptions\MalformedQueryException;
use Yarunoka\Exceptions\InvalidYrnkException;
use Yarunoka\Exceptions\MissingCalendarDataException;
use Yarunoka\Exceptions\ReservedNameException;
use Yarunoka\Exceptions\UndefinedNameException;
use Yarunoka\Exceptions\UnregisteredResolverException;
use Yarunoka\Exceptions\UnsupportedVersionException;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class ExceptionHierarchyTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<Throwable>}>
     */
    public static function everyException(): iterable
    {
        yield from self::documentContentException();
        yield from self::exceptionOutsideTheDocumentFamily();
        yield 'InvalidYrnkException' => [InvalidYrnkException::class];
    }

    /**
     * @return iterable<string, array{class-string<Throwable>}>
     */
    public static function documentContentException(): iterable
    {
        yield 'ReservedNameException' => [ReservedNameException::class];
        yield 'UndefinedNameException' => [UndefinedNameException::class];
        yield 'MissingCalendarDataException' => [MissingCalendarDataException::class];
        yield 'UnsupportedVersionException' => [UnsupportedVersionException::class];
    }

    /**
     * @return iterable<string, array{class-string<Throwable>}>
     */
    public static function exceptionOutsideTheDocumentFamily(): iterable
    {
        yield 'InvalidValueException' => [InvalidValueException::class];
        yield 'UnregisteredResolverException' => [UnregisteredResolverException::class];
        yield 'InvalidCalendarDataException' => [InvalidCalendarDataException::class];
        // A malformed query is an error of a kind distinct from document
        // invalidity: the document stays valid.
        yield 'MalformedQueryException' => [MalformedQueryException::class];
    }

    /**
     * @return iterable<string, array{class-string<Throwable>}>
     */
    public static function exceptionRaisedByWhatArrivedAtRuntime(): iterable
    {
        yield from self::documentContentException();
        yield 'InvalidYrnkException' => [InvalidYrnkException::class];
        yield 'UnregisteredResolverException' => [UnregisteredResolverException::class];
        yield 'InvalidCalendarDataException' => [InvalidCalendarDataException::class];
        yield 'MalformedQueryException' => [MalformedQueryException::class];
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    #[Test]
    #[DataProvider('everyException')]
    public function every_exception_is_catchable_as_the_library_marker(string $exception): void
    {
        $this->assertInstanceOf(ExceptionInterface::class, new $exception('message'));
    }

    #[Test]
    public function the_marker_is_an_interface_so_no_inheritance_slot_is_spent(): void
    {
        $this->assertTrue(interface_exists(ExceptionInterface::class));
    }

    #[Test]
    public function a_hand_built_node_violating_its_invariant_is_a_programming_error(): void
    {
        $this->assertInstanceOf(InvalidArgumentException::class, new InvalidValueException('message'));
        $this->assertInstanceOf(LogicException::class, new InvalidValueException('message'));
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    #[Test]
    #[DataProvider('exceptionRaisedByWhatArrivedAtRuntime')]
    public function what_arrived_at_runtime_is_never_reported_as_a_programming_error(string $exception): void
    {
        $this->assertInstanceOf(RuntimeException::class, new $exception('message'));
        $this->assertNotInstanceOf(LogicException::class, new $exception('message'));
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    #[Test]
    #[DataProvider('documentContentException')]
    public function what_is_wrong_with_the_document_is_catchable_in_one_go(string $exception): void
    {
        $this->assertInstanceOf(InvalidYrnkException::class, new $exception('message'));
    }

    /**
     * @param  class-string<Throwable>  $exception
     */
    #[Test]
    #[DataProvider('exceptionOutsideTheDocumentFamily')]
    public function what_the_document_did_not_cause_stays_out_of_that_family(string $exception): void
    {
        $this->assertNotInstanceOf(InvalidYrnkException::class, new $exception('message'));
    }

    #[Test]
    public function what_a_resolver_returned_is_an_unexpected_value(): void
    {
        $this->assertInstanceOf(UnexpectedValueException::class, new InvalidCalendarDataException('message'));
    }
}
