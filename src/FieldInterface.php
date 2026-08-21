<?php


namespace Creatcode\Cronexp;
use DateTime;

/**
 * Cron 字段接口。
 */
interface FieldInterface
{
    /**
     * 判断日期对象中对应字段的值是否满足 Cron 表达式。
     *
     * @param DateTime $date  待判断的日期对象
     * @param string   $value Cron 字段表达式
     *
     * @return bool 满足表达式时返回 true，否则返回 false
     */
    public function isSatisfiedBy(DateTime $date, $value);

    /**
     * 当前字段不满足表达式时，按字段单位递增或递减日期对象。
     *
     * @param DateTime $date   待调整的日期对象
     * @param bool     $invert 为 true 时递减，否则递增
     *
     * @return FieldInterface
     */
    public function increment(DateTime $date, $invert = false);

    /**
     * 校验字段表达式是否合法。
     *
     * @param string $value 待校验的 Cron 字段表达式
     *
     * @return bool 合法时返回 true，否则返回 false
     */
    public function validate($value);
}
