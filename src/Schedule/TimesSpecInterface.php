<?php

namespace Yarunoka\Schedule;

/**
 * Marker for the time part of a schedule: the two spellings of times
 * (FixedTimes | EveryGrid), AllDay, and EverySequence (the interval
 * every). A schedule has exactly one of times / allday / every.
 */
interface TimesSpecInterface {}
