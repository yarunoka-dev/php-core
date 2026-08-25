<?php

namespace Yarunoka\Exceptions;

use RuntimeException;

/**
 * A malformed query: a period or an enumeration whose endpoints are
 * reversed (its start lies after its end). An error of a kind distinct
 * from document invalidity — the document stays valid; the question is
 * the side that does not stand. A reversed period arises only from
 * broken caller state or from a clock that moved backwards, and an
 * empty answer would hide exactly that, which is why the answer is an
 * error rather than false. Equal endpoints are legal and never raise
 * this.
 */
class MalformedQueryException extends RuntimeException implements ExceptionInterface {}
