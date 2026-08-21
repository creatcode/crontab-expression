<?php

namespace Creatcode\Cronexp;

use DateTime;

/**
 * 月内日期字段，支持：*、,、/、-、?、L、W。
 *
 * L 表示每月最后一天。
 *
 * W 表示指定日期最近的工作日（周一至周五）。例如 15W：15 日为周六时在 14 日
 * 执行，为周日时在 16 日执行，为工作日时在 15 日执行。1W 在 1 日为周六时会在
 * 当月 3 日执行，不会跨月寻找工作日。W 只能与单个日期组合，不能用于范围或列表。
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class DayOfMonthField extends AbstractField
{
    /**
     * 获取指定月内日期最近的工作日。
     *
     * @param int $currentYear  年份
     * @param int $currentMonth 月份
     * @param int $targetDay    月内目标日期
     *
     * @return \DateTime 最近的工作日日期
     */
    private static function getNearestWeekday($currentYear, $currentMonth, $targetDay)
    {
        $tday = str_pad($targetDay, 2, '0', STR_PAD_LEFT);
        $target = DateTime::createFromFormat('Y-m-d', "$currentYear-$currentMonth-$tday");
        $currentWeekday = (int) $target->format('N');

        if ($currentWeekday < 6) {
            return $target;
        }

        $lastDayOfMonth = $target->format('t');

        foreach (array(-1, 1, -2, 2) as $i) {
            $adjusted = $targetDay + $i;
            if ($adjusted > 0 && $adjusted <= $lastDayOfMonth) {
                $target->setDate($currentYear, $currentMonth, $adjusted);
                if ($target->format('N') < 6 && $target->format('m') == $currentMonth) {
                    return $target;
                }
            }
        }
    }

    public function isSatisfiedBy(DateTime $date, $value)
    {
        // ? 表示忽略月内日期字段的限制。
        if ($value == '?') {
            return true;
        }

        $fieldValue = $date->format('d');

        // 判断是否为每月最后一天。
        if ($value == 'L') {
            return $fieldValue == $date->format('t');
        }

        // 判断是否为指定日期最近的工作日。
        if (strpos($value, 'W')) {
            // 提取 W 前的目标日期。
            $targetDay = substr($value, 0, strpos($value, 'W'));
            // 比较当前日期是否为最近的工作日。
            return $date->format('j') == self::getNearestWeekday(
                $date->format('Y'),
                $date->format('m'),
                $targetDay
            )->format('j');
        }

        return $this->isSatisfied($date->format('d'), $value);
    }

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
     * 默认支持 1-31、*、L、?；支持用 , 表示列表、用 - 表示范围，以及用 [0-9]W
     * 表示最近的工作日。
     *
     * @param string $value
     * @return bool
     */
    public function validate($value)
    {
        // 允许通配符、忽略符和单个 L。
        if ($value === '?' || $value === '*' || $value === 'L') {
            return true;
        }

        // W 只能配合月份中的单个合法日期使用
        if ((bool) preg_match('/^(\d{1,2})W$/', $value, $matches)) {
            return $matches[1] >= 1 && $matches[1] <= 31;
        }

        return $this->validateNumericExpression($value, 1, 31);
    }
}
