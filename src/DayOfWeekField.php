<?php

namespace Creatcode\Cronexp;

use DateTime;
use InvalidArgumentException;


/**
 * 星期字段，支持：*、/、,、-、?、L、#。
 *
 * 星期可用 0-7 表示，其中 0 和 7 均表示星期日；也可使用 SUN、MON、TUE、WED、
 * THU、FRI、SAT 等三字母缩写。
 *
 * L 表示最后一次，可用于指定“每月最后一个星期五”等规则。
 *
 * # 后必须为 1-5，可用于指定“每月第二个星期五”等规则。
 */
class DayOfWeekField extends AbstractField
{
    public function isSatisfiedBy(DateTime $date, $value)
    {
        if ($value == '?') {
            return true;
        }

        // 将星期缩写转换为数值。
        $value = $this->convertLiterals($value);

        $currentYear = $date->format('Y');
        $currentMonth = $date->format('m');
        $lastDayOfMonth = $date->format('t');

        // 判断是否为当月最后一个指定星期。
        if (strpos($value, 'L')) {
            $weekday = str_replace('7', '0', substr($value, 0, strpos($value, 'L')));
            $tdate = clone $date;
            $tdate->setDate($currentYear, $currentMonth, $lastDayOfMonth);
            while ($tdate->format('w') != $weekday) {
                $tdateClone = new DateTime();
                $tdate = $tdateClone
                    ->setTimezone($tdate->getTimezone())
                    ->setDate($currentYear, $currentMonth, --$lastDayOfMonth);
            }

            return $date->format('j') == $lastDayOfMonth;
        }

        // 处理 # 规则。
        if (strpos($value, '#')) {
            list($weekday, $nth) = explode('#', $value);

            // 0 and 7 are both Sunday, however 7 matches date('N') format ISO-8601
            if ($weekday === '0') {
                $weekday = 7;
            }

            // 校验 # 规则中的星期和序号。
            if ($weekday < 0 || $weekday > 7) {
                throw new InvalidArgumentException("Weekday must be a value between 0 and 7. {$weekday} given");
            }
            if ($nth > 5) {
                throw new InvalidArgumentException('There are never more than 5 of a given weekday in a month');
            }
            // 当前星期必须与目标星期一致。
            if ($date->format('N') != $weekday) {
                return false;
            }

            $tdate = clone $date;
            $tdate->setDate($currentYear, $currentMonth, 1);
            $dayCount = 0;
            $currentDay = 1;
            while ($currentDay < $lastDayOfMonth + 1) {
                if ($tdate->format('N') == $weekday) {
                    if (++$dayCount >= $nth) {
                        break;
                    }
                }
                $tdate->setDate($currentYear, $currentMonth, ++$currentDay);
            }

            return $date->format('j') == $currentDay;
        }

        // 处理星期范围表达式。
        if (strpos($value, '-')) {
            $parts = explode('-', $value);
            if ($parts[0] == '7') {
                $parts[0] = '0';
            } elseif ($parts[1] == '0') {
                $parts[1] = '7';
            }
            $value = implode('-', $parts);
        }

        // 根据表达式确定以 0 还是 7 表示星期日。
        $format = in_array(7, str_split($value)) ? 'N' : 'w';
        $fieldValue = $date->format($format);

        return $this->isSatisfied($fieldValue, $value);
    }

    public function increment(DateTime $date, $invert = false)
    {
        if ($invert) {
            $date->modify('-1 day');
            $date->setTime(23, 59, 0);
        } else {
            $date->modify('+1 day');
            $date->setTime(0, 0, 0);
        }

        return $this;
    }

    public function validate($value)
    {
        $value = $this->convertLiterals($value);

        if ($value === '?') {
            return true;
        }

        foreach (explode(',', $value) as $expr) {
            if (preg_match('/^[0-7](L|#[1-5])$/', $expr)) {
                continue;
            }

            if (!$this->validateNumericExpression($expr, 0, 7)) {
                return false;
            }
        }

        return true;
    }

    private function convertLiterals($string)
    {
        return str_ireplace(
            array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'),
            range(0, 6),
            $string
        );
    }
}
