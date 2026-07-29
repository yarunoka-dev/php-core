<?php

namespace Yarunoka\Exceptions;

use UnexpectedValueException;

/**
 * Injected calendar data (date lists, time windows, or a weekly pattern)
 * violates its contract.
 *
 * What a resolver handed back, checked at the moment it was called — so it
 * says nothing about whether the document is well formed.
 */
class InvalidCalendarDataException extends UnexpectedValueException implements ExceptionInterface {}
