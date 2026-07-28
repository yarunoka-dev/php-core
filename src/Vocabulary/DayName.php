<?php

namespace Yarunoka\Vocabulary;

/**
 * A day-of-week name. An atom of the DSL and, at the same time, the
 * representation of a date's day of week.
 */
enum DayName: string
{
    case Mon = 'mon';
    case Tue = 'tue';
    case Wed = 'wed';
    case Thu = 'thu';
    case Fri = 'fri';
    case Sat = 'sat';
    case Sun = 'sun';
}
