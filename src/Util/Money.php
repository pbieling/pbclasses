<?php


namespace PbClasses\Util;

/**
 * Description of Money
 *
 * @author Bieling Peter
 */
class Money {

    public static function gMoney($money) {
        return \number_format((float) $money, 2, ',', '');
    }
}
