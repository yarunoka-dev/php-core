<?php

namespace Yarunoka\Expression;

/**
 * An atom of a day expression. Marker for the values that can appear in the
 * `days` enumeration, as a `shift` landing condition, or as an `if`
 * condition in the DSL. Nodes carry structure only; evaluation is done by
 * YrnkEvaluator.
 */
interface DayAtom {}
