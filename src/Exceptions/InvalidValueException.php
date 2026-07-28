<?php

namespace Yarunoka\Exceptions;

use InvalidArgumentException;

/**
 * A value such as a date or a time of day violates the literal format rules.
 *
 * A logic error rather than a runtime one: every path that feeds document
 * data into a node repacks this into an InvalidYrnkException or an
 * InvalidCalendarDataException, so it reaches a caller only when the caller
 * built the node itself with an argument that cannot be right.
 */
class InvalidValueException extends InvalidArgumentException implements ExceptionInterface {}
