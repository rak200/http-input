<?php

declare(strict_types=1);

namespace Rak200\HttpInput;

use LogicException;
use Rak200\HttpInput\Exception\InputException;
use Rak200\HttpInput\Exception\InvalidInputException;
use Rak200\Utils\Arr;

/**
 * Structured JSON / schema validation (RFC 0014): the two structural
 * combinators over RFC 0013's leaves.
 *
 * {@see object()} declares a keyed shape — each key maps to a {@see Rule}
 * leaf or a nested Schema; keys present in the payload with no rule are
 * rejected by default ({@see allowUnknownKeys()} opts out per object).
 * {@see listOf()} declares a homogeneous list — every element matches one
 * sub-schema or leaf rule. Leaves run with decoded-tree semantics
 * (`Rule::apply(..., typed: true)`): a bare leaf asserts the decoded PHP
 * type, `coerce()` re-enables lossless conversion.
 *
 * {@see validate()} is the pure core — a decoded tree in, a {@see Result}
 * out, failures keyed by the path of the offending node (`items.0.qty`).
 * The impure shortcut for a request body is {@see Input::json()}. Objects
 * are validated as the associative arrays `Json::decode` returns; the known
 * `{}` / `[]` decode ambiguity is accepted (RFC 0014).
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Schema
{
    /**
     * The object form's shape; null when this schema is a listOf.
     *
     * @var null|array<string, Rule|Schema>
     */
    private ?array $shape = null;

    /**
     * The listOf form's element; null when this schema is an object.
     */
    private Rule|Schema|null $element = null;

    private bool $allowUnknown = false;

    private function __construct() {}

    /**
     * Declares a keyed shape: each key maps to a {@see Rule} leaf or a
     * nested Schema. A key present in the payload with no rule is an error
     * keyed by its path — the safe default for closed APIs; opt out per
     * object with {@see allowUnknownKeys()}. An absent key follows the
     * leaf's presence rules (`required()` → Missing, otherwise skipped with
     * a null value); an absent nested object is skipped as a whole.
     *
     * @param array<string, Rule|Schema> $shape
     */
    public static function object(array $shape): self
    {
        $schema = new self();
        $schema->shape = $shape;

        return $schema;
    }

    /**
     * Declares a homogeneous list: every element must match $element — a
     * leaf rule or a nested schema. Element failures are keyed by their
     * index within the path (`items.0.qty`).
     */
    public static function listOf(Rule|self $element): self
    {
        $schema = new self();
        $schema->element = $element;

        return $schema;
    }

    /**
     * Opts this object schema out of unknown-key rejection: keys without a
     * rule are ignored (and never copied into the clean tree).
     *
     * @throws LogicException on a listOf schema — only objects have keys
     */
    public function allowUnknownKeys(): self
    {
        if ($this->shape === null) {
            throw new LogicException('Only an object schema has keys — allowUnknownKeys() does not apply to listOf.');
        }
        $schema = clone $this;
        $schema->allowUnknown = true;

        return $schema;
    }

    /**
     * The pure core: validates an already-decoded tree against this schema
     * and returns a {@see Result} — failures keyed by the offending node's
     * path, plus the clean (typed, structured) tree. A malformed root is
     * keyed by the empty path.
     */
    public function validate(mixed $tree): Result
    {
        [$values, $failures] = $this->apply($tree);
        $errors = [];
        foreach ($failures as $failure) {
            $errors[$failure->at() ?? ''][] = $failure;
        }

        return new Result($errors, Arr::is($values) ? $values : []);
    }

    /**
     * Validates one node: `[cleanValue, failures]`, the failures carrying
     * their path relative to this node.
     *
     * @return array{0: mixed, 1: list<InputException>}
     */
    private function apply(mixed $node): array
    {
        return $this->shape !== null
            ? $this->applyObject($this->shape, $node)
            : $this->applyList($node);
    }

    /**
     * Walks a keyed shape: declared keys run their rule or nested schema
     * (absent keys follow the leaf's presence rules), undeclared keys are
     * rejected unless {@see allowUnknownKeys()}.
     *
     * @param array<string, Rule|Schema> $shape
     *
     * @return array{0: mixed, 1: list<InputException>}
     */
    private function applyObject(array $shape, mixed $node): array
    {
        if (!Arr::is($node) || ($node !== [] && !Arr::isAssoc($node))) {
            return [null, [new InvalidInputException('must be an object')]];
        }
        $clean = [];
        $failures = [];
        foreach ($shape as $key => $child) {
            if (!Arr::hasKey($node, $key)) {
                $absent = $child instanceof Rule ? $child->applyAbsent(typed: true) : null;
                $clean[$key] = $absent?->value;
                if ($absent !== null) {
                    foreach ($absent->failures as $failure) {
                        $failures[] = $failure->nest($key);
                    }
                }

                continue;
            }
            [$value, $childFailures] = self::applyChild($child, $node[$key]);
            $clean[$key] = $value;
            foreach ($childFailures as $failure) {
                $failures[] = $failure->nest($key);
            }
        }
        if (!$this->allowUnknown) {
            foreach (Arr::keys($node) as $key) {
                if (!Arr::hasKey($shape, $key)) {
                    $failures[] = new InvalidInputException('is not allowed')->nest((string) $key);
                }
            }
        }

        return [$clean, $failures];
    }

    /**
     * Walks a homogeneous list: every element through the element rule or
     * nested schema, failures index-keyed.
     *
     * @return array{0: mixed, 1: list<InputException>}
     */
    private function applyList(mixed $node): array
    {
        if ($this->element === null) {
            return [null, [new InvalidInputException('must be a list')]];   // defensive: unreachable
        }
        if (!Arr::is($node) || !Arr::isList($node)) {
            return [null, [new InvalidInputException('must be a list')]];
        }
        $clean = [];
        $failures = [];
        foreach ($node as $index => $item) {
            [$value, $itemFailures] = self::applyChild($this->element, $item);
            $clean[] = $value;
            foreach ($itemFailures as $failure) {
                $failures[] = $failure->nest((string) $index);
            }
        }

        return [$clean, $failures];
    }

    /**
     * One child node: a Rule leaf runs with decoded-tree semantics, a
     * nested schema recurses.
     *
     * @return array{0: mixed, 1: list<InputException>}
     */
    private static function applyChild(Rule|self $child, mixed $value): array
    {
        if ($child instanceof Rule) {
            $outcome = $child->apply($value, typed: true);

            return [$outcome->value, $outcome->failures];
        }

        return $child->apply($value);
    }
}
