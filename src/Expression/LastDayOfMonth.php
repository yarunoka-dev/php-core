<?php

namespace Yarunoka\Expression;

/**
 * The end-of-month atom. The end of the month is the only month boundary
 * that moves, so it is the one special word.
 */
final readonly class LastDayOfMonth implements DayAtom {}
