<?php

namespace Yarunoka\Exceptions;

use Throwable;

/**
 * Every exception this library throws. Implemented rather than inherited,
 * so each exception is free to extend the SPL type that describes what
 * went wrong — a caller can catch this to mean "Yarunoka failed", or catch
 * the SPL type to treat it alongside failures of the same kind from
 * elsewhere.
 */
interface ExceptionInterface extends Throwable {}
