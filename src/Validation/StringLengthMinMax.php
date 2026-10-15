<?php

declare(strict_types=1);

namespace PbClasses\Validation;

use InvalidArgumentException;

/**
 * (This class was revised by claude.ai)
 * Validates that a string's length falls within an inclusive [min, max] range.
 *
 * Example:
 * ```php
 * StringLengthMinMax::validate('hello', [1, 10]);              // true
 * StringLengthMinMax::validate('', [1, 10]);                   // false
 * StringLengthMinMax::validate('', [1, 10], StringLengthFlag::OrEmpty); // true
 * ```
 */
final class StringLengthMinMax
{
    /**
     * Validate that $value is a string whose length lies between
     * $param[0] (min) and $param[1] (max), inclusive.
     *
     * @param mixed              $value The value to validate.
     * @param array{0:int,1:int} $param Two-element array: [min, max].
     * @param StringLengthFlag|null $flags Optional flag. Pass
     *        {@see StringLengthFlag::OrEmpty} to also accept an empty string
     *        regardless of the configured min/max.
     *
     * @return bool True if the value satisfies the length constraint.
     *
     * @throws InvalidArgumentException If $param is not an array of exactly two elements.
     */
    public static function validate(
        mixed $value,
        array $param,
        ?StringLengthFlag $flags = null
    ): bool {
        if (\count($param) !== 2) {
            throw new InvalidArgumentException(
                'The second parameter must be an array with exactly 2 elements: [min, max].'
            );
        }

        [$min, $max] = $param;

        if (!\is_int($min) || !\is_int($max)) {
            throw new InvalidArgumentException('Both min and max must be integers.');
        }

        if (!\is_string($value)) {
            return false;
        }

        if ($flags === StringLengthFlag::OrEmpty && $value === '') {
            return true;
        }

        $length = \mb_strlen($value);

        return $length >= $min && $length <= $max;
    }
}

/**
 * Flags controlling optional behaviour of {@see StringLengthMinMax::validate()}.
 */
enum StringLengthFlag
{
    /**
     * Treat an empty string as valid, bypassing the min/max check.
     */
    case OrEmpty;
}