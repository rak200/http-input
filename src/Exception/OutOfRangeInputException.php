<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The value falls outside an ordered range — raised by the `min` / `max` /
 * `between` verifiers over any ordered value the coercer produced (numbers,
 * temporal values).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class OutOfRangeInputException extends InvalidInputException {}
