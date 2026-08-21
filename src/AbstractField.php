<?php

namespace Creatcode\Cronexp;

/**
 * Cron 表达式字段抽象基类。
 */
abstract class AbstractField implements FieldInterface
{
    /**
     * 校验通用数值 Cron 字段，支持单值、列表、范围和步长。
     *
     * @param string $value 字段表达式
     * @param int    $min   最小值
     * @param int    $max   最大值
     *
     * @return bool
     */
    protected function validateNumericExpression($value, $min, $max)
    {
        foreach (explode(',', $value) as $expression) {
            if (!preg_match('/^(\*|\d+)(?:-(\d+))?(?:\/(\d+))?$/', $expression, $matches)) {
                return false;
            }

            $start = $matches[1];
            $end = isset($matches[2]) && $matches[2] !== '' ? $matches[2] : null;
            $step = isset($matches[3]) && $matches[3] !== '' ? $matches[3] : null;
            if (($start !== '*' && ($start < $min || $start > $max)) ||
                ($end !== null && ($end < $min || $end > $max || $start === '*' || $start > $end)) ||
                ($step !== null && $step < 1)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 判断字段值是否满足表达式。
     *
     * @param string $dateValue 待判断的日期字段值
     * @param string $value     Cron 字段表达式
     *
     * @return bool
     */
    public function isSatisfied($dateValue, $value)
    {
        if ($this->isIncrementsOfRanges($value)) {
            return $this->isInIncrementsOfRanges($dateValue, $value);
        } elseif ($this->isRange($value)) {
            return $this->isInRange($dateValue, $value);
        }

        return $value == '*' || $dateValue == $value;
    }

    /**
     * 判断字段表达式是否为范围。
     *
     * @param string $value 字段表达式
     *
     * @return bool
     */
    public function isRange($value)
    {
        return strpos($value, '-') !== false;
    }

    /**
     * 判断字段表达式是否包含步长。
     *
     * @param string $value 字段表达式
     *
     * @return bool
     */
    public function isIncrementsOfRanges($value)
    {
        return strpos($value, '/') !== false;
    }

    /**
     * 判断字段值是否位于指定范围内。
     *
     * @param string $dateValue 待判断的日期字段值
     * @param string $value     范围表达式
     *
     * @return bool
     */
    public function isInRange($dateValue, $value)
    {
        $parts = array_map('trim', explode('-', $value, 2));

        return $dateValue >= $parts[0] && $dateValue <= $parts[1];
    }

    /**
     * 判断字段值是否满足步长范围表达式（起点[-终点]/步长）。
     *
     * @param string $dateValue 待判断的日期字段值
     * @param string $value     步长范围表达式
     *
     * @return bool
     */
    public function isInIncrementsOfRanges($dateValue, $value)
    {
        $parts = array_map('trim', explode('/', $value, 2));
        $stepSize = isset($parts[1]) ? (int) $parts[1] : 0;

        if ($stepSize === 0) {
            return false;
        }

        if (($parts[0] == '*' || $parts[0] === '0')) {
            return (int) $dateValue % $stepSize == 0;
        }

        $range = explode('-', $parts[0], 2);
        $offset = $range[0];
        $to = isset($range[1]) ? $range[1] : $dateValue;
        // 先确认日期字段值位于指定范围内，避免无效匹配。
        if ($dateValue < $offset || $dateValue > $to) {
            return false;
        }

        if ($dateValue > $offset && 0 === $stepSize) {
          return false;
        }

        for ($i = $offset; $i <= $to; $i+= $stepSize) {
            if ($i == $dateValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * 根据 Cron 字段表达式展开为数值列表。
     *
     * @param string $expression 待展开的字段表达式
     * @param int    $max        范围允许的最大值
     *
     * @return array
     */
    public function getRangeForExpression($expression, $max)
    {
        $values = array();

        if ($this->isRange($expression) || $this->isIncrementsOfRanges($expression)) {
            if (!$this->isIncrementsOfRanges($expression)) {
                list ($offset, $to) = explode('-', $expression);
                $stepSize = 1;
            }
            else {
                $range = array_map('trim', explode('/', $expression, 2));
                $stepSize = isset($range[1]) ? $range[1] : 0;
                $range = $range[0];
                $range = explode('-', $range, 2);
                $offset = $range[0];
                $to = isset($range[1]) ? $range[1] : $max;
            }
            $offset = $offset == '*' ? 0 : $offset;
            for ($i = $offset; $i <= $to; $i += $stepSize) {
                $values[] = $i;
            }
            sort($values);
        }
        else {
            $values = array($expression);
        }

        return $values;
    }

}
