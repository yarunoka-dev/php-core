<?php

namespace Yarunoka\Exceptions;

/**
 * A definition required by a calendar word has not been injected. Raised at
 * build or resolution time — never a silent "no match".
 */
class MissingCalendarDataException extends YarunokaException {}
