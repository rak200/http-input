<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

/**
 * The value does not equal the value it must match — raised by the `sameAs`
 * verifier (the cross-field match, e.g. password confirmation).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MismatchInputException extends InvalidInputException {}
