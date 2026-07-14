<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The value has the wrong length — raised by the `minLen` / `maxLen` /
 * `lenBetween` verifiers (characters for strings, element count for arrays).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class LengthInputException extends InvalidInputException {}
