<?php

namespace Yarunoka\Vocabulary;

use Yarunoka\Expression\DayAtom;

/**
 * Calendar vocabulary (the five layer-model words). weekday / weekend ask
 * the fixed calendar and consult no definition; holiday asks the holidays
 * list alone; business_day / business_holiday are questions to the stacked
 * conclusion of the layers. Usable directly as a day expression atom.
 */
enum CalendarWord: string implements DayAtom
{
    case Weekday = 'weekday';
    case Weekend = 'weekend';
    case Holiday = 'holiday';
    case BusinessDay = 'business_day';
    case BusinessHoliday = 'business_holiday';
}
