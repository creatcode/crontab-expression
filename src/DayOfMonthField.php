<?php

namespace Creatcode\Cronexp;

use DateTime;

/**
 * 月内日期字段，支持：*、,、/、-。
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class DayOfMonthField extends AbstractField
{
    /**
     * 判断日期对象的月内日期是否满足表达式。
     *
     * @param DateTime $date  待判断的日期对象
     * @param string   $value 字段表达式
     *
     * @return bool 满足时返回 true，否则返回 false
     */
    public function isSatisfiedBy(DateTime $date, $value)
    {
        return $this->isSatisfied($date->format('d'), $value);
    }

    /**
     * 将日期推进到相邻的一天。
     *
     * @param DateTime $date   待更新的日期对象
     * @param bool     $invert 是否向过去推进
     *
     * @return DayOfMonthField 当前字段对象
     */
    public function increment(DateTime $date, $invert = false)
    {
        if ($invert) {
            $date->modify('previous day');
            $date->setTime(23, 59);
        } else {
            $date->modify('next day');
            $date->setTime(0, 0);
        }

        return $this;
    }

    /**
     * 校验月内日期字段表达式是否合法。
     *
     * 支持 1-31、*、列表、范围和步长。
     *
     * @param string $value
     * @return bool
     */
    public function validate($value)
    {
        return $this->validateNumericExpression($value, 1, 31);
    }
}
