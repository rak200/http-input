<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use Rak200\HttpInput\Exception\InputException;
use Rak200\Utils\Arr;

/**
 * The outcome of validating a tree against a {@see Schema}: the
 * {@see Validator} reporting surface, path-keyed — {@see fails()},
 * {@see errors()}, {@see messages()}, {@see values()} — plus {@see valid()},
 * the fail-fast terminal over a tree. Both of RFC 0013's terminal styles
 * carry over: collect (read the bag) and throw (`valid()`).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Result
{
    /**
     * Built by {@see Schema::validate()}; this constructor is its backing.
     *
     * @param array<string, list<InputException>> $errors path-keyed failures
     * @param array<array-key, mixed>             $values the clean tree
     */
    public function __construct(
        private readonly array $errors,
        private readonly array $values,
    ) {}

    /**
     * Returns true when the tree produced at least one failure.
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * The structured bag: every failure keyed by the path of the offending
     * node (`address.city`, `items.0.qty`), in walk order. A malformed root
     * is keyed by the empty path.
     *
     * @return array<string, list<InputException>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * The flat view of {@see errors()}: the failure messages, path-keyed —
     * `['items.0.qty' => ['must be at least 1'], ...]`.
     *
     * @return array<string, list<string>>
     */
    public function messages(): array
    {
        // Arr::map's generic return (array<K, TResult>) erases the inner
        // list-ness; mapping never disturbs a list's 0..n-1 keys.
        /** @var array<string, list<string>> $messages */
        $messages = Arr::map(
            $this->errors,
            static fn (array $failures): array => Arr::map(
                $failures,
                static fn (InputException $failure): string => $failure->getMessage(),
            ),
        );

        return $messages;
    }

    /**
     * The clean tree: fully typed and structured, one entry per declared
     * key (null for a skipped optional key, best-effort values elsewhere).
     * Meant to be read after {@see fails()} says nothing was recorded.
     *
     * @return array<array-key, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The fail-fast terminal over the tree: returns the clean tree, or
     * throws the first failure in walk order.
     *
     * @return array<array-key, mixed>
     */
    public function valid(): array
    {
        if ($this->errors !== []) {
            throw Arr::first(Arr::first($this->errors));
        }

        return $this->values;
    }
}
