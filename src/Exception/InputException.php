<?php

declare(strict_types=1);

namespace Rak200\HttpInput\Exception;

use RuntimeException;

/**
 * Base type of every input failure the library raises.
 *
 * A failure is an InputException that has not (yet) been thrown: the chain's
 * terminal decides its fate — `value()` throws the first, `get()` records all
 * into the validator, `orNull()`/`orElse()` discard. Catching this type is the
 * library-scoped catch: it covers {@see MissingInputException},
 * {@see InvalidInputException}, and every per-constraint subtype.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class InputException extends RuntimeException {}
