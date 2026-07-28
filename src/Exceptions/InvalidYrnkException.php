<?php

namespace Yarunoka\Exceptions;

use RuntimeException;

/**
 * A document validation error: the structure or a value of a Yrnk document
 * violates the language (unknown key, malformed shape, or invalid value).
 *
 * The base of everything that means "the document is wrong", so catching it
 * covers that whole family. A document arrives at runtime, which is why this
 * side of the hierarchy is not a logic error.
 */
class InvalidYrnkException extends RuntimeException implements ExceptionInterface {}
