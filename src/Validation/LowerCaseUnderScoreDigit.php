<?php

namespace PbClasses\Validation;

/**
 * Description of LowerCaseUnderScore
 *
 * @author Peter Bieling - info@media-palette.de
 * Created on : 15.07.2019, 14:05:46
 */
class LowerCaseUnderScoreDigit {

    public static function validate($value) {
        return preg_match('/^[0-9a-z_]+$/', $value);
    }

}
