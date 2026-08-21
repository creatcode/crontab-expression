<?php

namespace Creatcode\Cronexp;

use DateTime;

/**
 * 月字段，支持：*、,、/、-。
 */
class MonthField extends AbstractField
{
    public function isSatisfiedBy(DateTime $date, $value)
    {
        // 将月份缩写转换为数值。
        $value = str_ireplace(
            array(
                'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
                'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'
            ),
            range(1, 12),
            $value
        );

        return $this->isSatisfied($date->format('m'), $value);
    }

    public function increment(DateTime $date, $invert = false)
    {
        if ($invert) {
            $date->modify('last day of previous month');
            $date->setTime(23, 59);
        } else {
            $date->modify('first day of next month');
            $date->setTime(0, 0);
        }

        return $this;
    }

    public function validate($value)
    {
        $value = str_ireplace(
            array(
                'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
                'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'
            ),
            range(1, 12),
            $value
        );

        return $this->validateNumericExpression($value, 1, 12);
    }
}
