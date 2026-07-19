<?php

namespace Yarunoka\Exceptions;

/**
 * A name that cannot be registered as a definition name (it collides with a
 * reserved word, or it looks like a literal — digits only, date-shaped, or
 * time-shaped).
 */
class ReservedNameException extends YarunokaException {}
