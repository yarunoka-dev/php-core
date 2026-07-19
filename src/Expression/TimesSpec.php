<?php

namespace Yarunoka\Expression;

/**
 * Marker for the time part of a schedule (FixedTimes | EveryGrid |
 * AllDay). A schedule has exactly one of times / allday.
 */
interface TimesSpec {}
