<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Rak200\HttpInput\Exception\InputException;

/**
 * A field-agnostic constraint failure: the human-readable message plus,
 * optionally, the {@see InputException} subtype the failure should surface
 * as. Constraints return one from {@see Constraint::check()}; the accessor
 * binds it to the concrete `(source, key)` and turns it into the exception
 * the terminal throws, records, or discards. When $exceptionClass is null
 * the accessor raises the default `InvalidInputException`.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Violation
{
    /**
     * @param null|class-string<InputException> $exceptionClass raised instead
     *                                                          of the default InvalidInputException when given
     */
    public function __construct(
        public readonly string $message,
        public readonly ?string $exceptionClass = null,
    ) {}
}
