<?php

namespace Yarunoka\Exceptions;

/**
 * A name referenced by a schedule exists neither in the built-in vocabulary
 * nor among the user definitions. A resolver name the host never bound is
 * the other story and has its own exception.
 */
class UndefinedNameException extends InvalidYrnkException {}
