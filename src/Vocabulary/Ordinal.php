<?php

namespace Yarunoka\Vocabulary;

/**
 * An ordinal word. Usable only as the first element of an ordinal tuple
 * ["3rd", "mon"].
 */
enum Ordinal: string
{
    case First = '1st';
    case Second = '2nd';
    case Third = '3rd';
    case Fourth = '4th';
    case Fifth = '5th';
    case Last = 'last';
}
