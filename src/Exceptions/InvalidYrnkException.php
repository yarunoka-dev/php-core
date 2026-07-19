<?php

namespace Yarunoka\Exceptions;

/**
 * A document validation error: the structure or a value of a Yrnk document
 * violates the language (unknown key, malformed shape, or invalid value).
 */
class InvalidYrnkException extends YarunokaException {}
