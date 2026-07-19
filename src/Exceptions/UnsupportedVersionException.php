<?php

namespace Yarunoka\Exceptions;

/**
 * A declared version this implementation does not know. Rejected rather
 * than silently interpreted — a safeguard against the PHP and TS
 * implementations evolving separately.
 */
class UnsupportedVersionException extends YarunokaException {}
