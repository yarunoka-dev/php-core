<?php

namespace Yarunoka\Exceptions;

use RuntimeException;

/**
 * A definition names a resolver the host never bound. The document is well
 * formed and says what it wants; what is missing is the binding handed to
 * the parser or the evaluator, which is why this is not a document error.
 */
class UnregisteredResolverException extends RuntimeException implements ExceptionInterface {}
