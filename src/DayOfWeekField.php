<?php

namespace Creatcode\Cronexp;

use DateTime;

/**
 * 星期字段，支持：*、/、,、-。
 *
 * 星期可用 0-7 表示，其中 0 和 7 均表示星期日；也可使用 SUN、MON、TUE、WED、
 * THU、FRI、SAT 等三字母缩写。
 *
 */
class DayOfWeekField extends AbstractField
{
    /**
     * 判断日期对象的星期是否满足表达式。
     *
     * @param DateTime $date  待判断的日期对象
     * @param string   $value 字段表达式
     *
     * @return bool 满足时返回 true，否则返回 false
     */
    public function isSatisfiedBy(DateTime $date, $value)
    {
        // 将星期缩写转换为数值。
        $value = $this->convertLiterals($value);

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

    /**
     * 将日期推进到相邻的一天。
     *
     * @param DateTime $date   待更新的日期对象
     * @param bool     $invert 是否向过去推进
     *
     * @return DayOfWeekField 当前字段对象
     */
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

    /**
     * 校验星期字段表达式是否合法。
     *
     * @param string $value 字段表达式
     *
     * @return bool 合法时返回 true，否则返回 false
     */
    public function validate($value)
    {
        $value = $this->convertLiterals($value);

        foreach (explode(',', $value) as $expr) {
            if (!$this->validateNumericExpression($expr, 0, 7)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 将星期英文缩写转换为数字。
     *
     * @param string $string 字段表达式
     *
     * @return string 转换后的字段表达式
     */
    private function convertLiterals($string)
    {
        return str_ireplace(
            array('SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'),
            range(0, 6),
            $string
        );
    }
}
