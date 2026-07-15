<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Tests\Fixture;

use Rak200\HttpInput\Rule;

/**
 * A caseless enum: exercises the "nothing can match" programmer error in
 * {@see Rule::enum()}.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum NoCases {}
