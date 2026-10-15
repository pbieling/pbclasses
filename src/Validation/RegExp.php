<?php

namespace PbClasses\Validation;

class RegExp {

    public static function validate($value, $param, $flags = null) {
        if (!\is_string($param)) {
            throw new \Exception('The second parmeter must be a string (regexp).');
        }

        if (!\is_scalar($value)) {
            throw new \Exception('The first parmeter must be a scalar.');
        }
        if ($flags === 'OR_EMPTY' && $value === '') {
            return true;
        }


        if (\preg_match($param, $value)) {
            return true;
        }


        return false;
    }

}
