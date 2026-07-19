<?php

namespace Yarunoka\Resolvers;

/**
 * The contract for supplying the holidays layer. The body is the same as
 * the base contract; DI container bindings are keyed by class name, so
 * this marker distinguishes by type which layer is being supplied.
 */
interface YrnkHolidaysResolverInterface extends YrnkResolverInterface {}
