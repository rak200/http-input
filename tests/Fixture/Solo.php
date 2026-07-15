<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Fixture;

use Rak200\HttpInput\Rule;

/**
 * A single-case backed enum: pins {@see Rule::enum()} to
 * the first case (`$cases[0]`) when it inspects the backing type.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum Solo: int
{
    case Only = 1;
}
