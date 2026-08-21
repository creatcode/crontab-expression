<?php

namespace Creatcode\Cronexp;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Cron 表达式的解析、校验与执行时间计算器。
 *
 * 支持 Unix 五段表达式、包含秒字段的六段表达式，以及包含年份字段的七段表达式。
 * 可计算指定基准时间后的下一次或上一次执行时间，并判断指定时刻是否命中表达式。
 *
 * @link http://en.wikipedia.org/wiki/Cron
 */
class CronExpression
{
    const SECOND  = 0;
    const MINUTE  = 1;
    const HOUR    = 2;
    const DAY     = 3;
    const MONTH   = 4;
    const WEEKDAY = 5;
    const YEAR    = 6;

    /**
     * @var array 规范化后的 Cron 字段列表
     */
    private $cronParts;

    /**
     * @var bool 是否使用 Unix 五段表达式
     */
    private $isUnixExpression = false;

    /**
     * @var FieldFactory Cron 字段对象工厂
     */
    private $fieldFactory;

    /**
     * @var int 搜索下一次或上一次执行时间时的最大迭代次数
     */
    private $maxIterationCount = 1000;

    /**
     * @var array Cron 字段的匹配顺序，按高位字段优先。
     */
    private static $order = array(self::YEAR, self::MONTH, self::DAY, self::WEEKDAY, self::HOUR, self::MINUTE, self::SECOND);

    /**
     * 根据 Cron 表达式创建解析对象。
     *
     * $expression 支持预定义别名：
     *
     *      `@yearly`、`@annually`：每年 1 月 1 日零点执行。
     *      `@monthly`：每月 1 日零点执行。
     *      `@weekly`：每周日零点执行。
     *      `@daily`：每天零点执行。
     *      `@hourly`：每小时第 0 分钟执行。
     * @param string       $expression   Cron 表达式或预定义别名
     * @param FieldFactory $fieldFactory 自定义字段对象工厂
     *
     * @return CronExpression
     */
    public static function factory($expression, FieldFactory $fieldFactory = null)
    {
        $mappings = array(
            '@yearly'   => '0 0 0 1 1 *',
            '@annually' => '0 0 0 1 1 *',
            '@monthly'  => '0 0 0 1 * *',
            '@weekly'   => '0 0 0 * * 0',
            '@daily'    => '0 0 0 * * *',
            '@hourly'   => '0 0 * * * *',
            '@minutely' => '0 * * * * *'
        );

        if (isset($mappings[$expression])) {
            $expression = $mappings[$expression];
        }

        return new static($expression, $fieldFactory ?: new FieldFactory());
    }

    /**
     * 校验 Cron 表达式的字段数量和字段取值是否合法。
     *
     * @param string $expression 待校验的 Cron 表达式
     *
     * @return bool 合法时返回 true，否则返回 false
     * @see \Cron\CronExpression::factory
     */
    public static function isValidExpression($expression)
    {
        try {
            self::factory($expression);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * 初始化 Cron 表达式解析对象。
     *
     * @param string       $expression   Cron 表达式
     * @param FieldFactory $fieldFactory 字段对象工厂
     */
    public function __construct($expression, FieldFactory $fieldFactory)
    {
        $this->fieldFactory = $fieldFactory;
        $this->setExpression($expression);
    }

    /**
     * 设置 Cron 表达式并完成字段校验。
     *
     * @param string $value Cron 表达式
     *
     * @return CronExpression
     * @throws \InvalidArgumentException 表达式不合法时抛出
     */
    public function setExpression($value)
    {
        $this->cronParts = preg_split('/\s/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $partCount = count($this->cronParts);
        if ($partCount < 5 || $partCount > 7) {
            throw new InvalidArgumentException(
                $value . ' is not a valid CRON expression'
            );
        }

        $this->isUnixExpression = $partCount === 5;

        if ($this->isUnixExpression && ($this->cronParts[2] === '?' || $this->cronParts[4] === '?')) {
            throw new InvalidArgumentException(
                $value . ' is not a valid Unix CRON expression'
            );
        }

        // Unix 五段格式不含秒，统一补零后复用秒级调度逻辑。
        if ($partCount === 5) {
            array_unshift($this->cronParts, '0');
        }

        foreach ($this->cronParts as $position => $part) {
            $this->setPart($position, $part);
        }

        return $this;
    }

    /**
     * 更新指定位置的 Cron 字段。
     *
     * @param int    $position 字段位置
     * @param string $value    字段表达式
     *
     * @return CronExpression
     * @throws \InvalidArgumentException 字段值不合法时抛出
     */
    public function setPart($position, $value)
    {
        if (!$this->fieldFactory->getField($position)->validate($value)) {
            throw new InvalidArgumentException(
                'Invalid CRON field value ' . $value . ' at position ' . $position
            );
        }

        $this->cronParts[$position] = $value;

        return $this;
    }

    /**
     * 设置执行时间搜索的最大迭代次数，防止无效日期导致无限循环。
     *
     * @param int $maxIterationCount 最大迭代次数
     *
     * @return CronExpression
     */
    public function setMaxIterationCount($maxIterationCount)
    {
        $this->maxIterationCount = $maxIterationCount;

        return $this;
    }

    /**
     * 计算指定基准时间之后的第 N 次执行时间。
     *
     * @param string|\DateTime $currentTime      计算基准时间
     * @param int              $nth              跳过的匹配次数，0 表示下一次匹配
     * @param bool             $allowCurrentDate 当前时间命中时是否直接返回
     * @param null|string      $timeZone         时区，未传时使用系统默认时区
     *
     * @return \DateTime
     * @throws \RuntimeException 在最大迭代次数内无法找到匹配时间时抛出
     */
    public function getNextRunDate($currentTime = 'now', $nth = 0, $allowCurrentDate = false, $timeZone = null)
    {
        return $this->getRunDate($currentTime, $nth, false, $allowCurrentDate, $timeZone);
    }

    /**
     * 计算指定基准时间之前的第 N 次执行时间。
     *
     * @param string|\DateTime $currentTime      计算基准时间
     * @param int              $nth              跳过的匹配次数，0 表示上一次匹配
     * @param bool             $allowCurrentDate 当前时间命中时是否直接返回
     * @param null|string      $timeZone         时区，未传时使用系统默认时区
     *
     * @return \DateTime
     * @throws \RuntimeException 在最大迭代次数内无法找到匹配时间时抛出
     * @see \Cron\CronExpression::getNextRunDate
     */
    public function getPreviousRunDate($currentTime = 'now', $nth = 0, $allowCurrentDate = false, $timeZone = null)
    {
        return $this->getRunDate($currentTime, $nth, true, $allowCurrentDate, $timeZone);
    }

    /**
     * 批量计算指定基准时间前后的一组执行时间。
     *
     * @param int              $total            需要返回的时间数量
     * @param string|\DateTime $currentTime      计算基准时间
     * @param bool             $invert           为 true 时向过去搜索
     * @param bool             $allowCurrentDate 当前时间命中时是否包含在结果中
     * @param null|string      $timeZone         时区，未传时使用系统默认时区
     *
     * @return array 执行时间对象列表
     */
    public function getMultipleRunDates($total, $currentTime = 'now', $invert = false, $allowCurrentDate = false, $timeZone = null)
    {
        $matches = array();
        for ($i = 0; $i < max(0, $total); $i++) {
            try {
                $matches[] = $this->getRunDate($currentTime, $i, $invert, $allowCurrentDate, $timeZone);
            } catch (RuntimeException $e) {
                break;
            }
        }

        return $matches;
    }

    /**
     * 获取完整 Cron 表达式或指定字段的表达式。
     *
     * @param int|null $part 字段位置；为 null 时返回完整表达式
     *
     * @return string|null 完整表达式、指定字段值或不存在时的 null
     */
    public function getExpression($part = null)
    {
        if (null === $part) {
            return implode(' ', $this->cronParts);
        } elseif (array_key_exists($part, $this->cronParts)) {
            return $this->cronParts[$part];
        }

        return null;
    }

    /**
     * 将对象转换为完整 Cron 表达式字符串。
     *
     * @return string 完整 Cron 表达式
     */
    public function __toString()
    {
        return $this->getExpression();
    }

    /**
     * 判断指定时刻是否命中当前 Cron 表达式。
     *
     * @param string|\DateTime $currentTime 待判断的时间
     * @param null|string      $timeZone    时区，未传时使用系统默认时区
     *
     * @return bool 命中时返回 true，否则返回 false
     */
    public function isDue($currentTime = 'now', $timeZone = null)
    {
        if (is_null($timeZone)) {
            $timeZone = date_default_timezone_get();
        }

        if ('now' === $currentTime) {
            $currentDate = date('Y-m-d H:i:s');
            $currentTime = strtotime($currentDate);
        } elseif ($currentTime instanceof DateTime) {
            $currentDate = clone $currentTime;
            // 统一按调用方指定的时区比较时间。
            $currentDate->setTimezone(new DateTimeZone($timeZone));
            $currentDate = $currentDate->format('Y-m-d H:i:s');
            $currentTime = strtotime($currentDate);
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentDate = DateTime::createFromFormat('U', $currentTime->format('U'));
            $currentDate->setTimezone(new DateTimeZone($timeZone));
            $currentDate = $currentDate->format('Y-m-d H:i:s');
            $currentTime = strtotime($currentDate);
        } else {
            $currentTime = new DateTime($currentTime);
            $currentTime->setTime($currentTime->format('H'), $currentTime->format('i'), $currentTime->format('s'));
            $currentDate = $currentTime->format('Y-m-d H:i:s');
            $currentTime = $currentTime->getTimeStamp();
        }

        try {
            return $this->getNextRunDate($currentDate, 0, true)->getTimestamp() == $currentTime;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 在指定基准时间前后搜索满足表达式的执行时间。
     *
     * @param string|\DateTime $currentTime      计算基准时间
     * @param int              $nth              跳过的匹配次数
     * @param bool             $invert           为 true 时向过去搜索
     * @param bool             $allowCurrentDate 当前时间命中时是否直接返回
     * @param string|null      $timeZone         时区，未传时使用系统默认时区
     *
     * @return \DateTime
     * @throws \RuntimeException 在最大迭代次数内无法找到匹配时间时抛出
     */
    protected function getRunDate($currentTime = null, $nth = 0, $invert = false, $allowCurrentDate = false, $timeZone = null)
    {
        if (is_null($timeZone)) {
            $timeZone = date_default_timezone_get();
        }

        if ($currentTime instanceof DateTime) {
            $currentDate = clone $currentTime;
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentDate = DateTime::createFromFormat('U', $currentTime->format('U'));
            $currentDate->setTimezone($currentTime->getTimezone());
        } else {
            $currentDate = new DateTime($currentTime ?: 'now');
            $currentDate->setTimezone(new DateTimeZone($timeZone));
        }

        $currentDate->setTime($currentDate->format('H'), $currentDate->format('i'), $currentDate->format('s'));
        $nextRun = clone $currentDate;
        $nth = (int) $nth;

        // 通配符和未定义字段无需参与匹配。
        $parts = array();
        $fields = array();
        $dayPart = $this->getExpression(self::DAY);
        $weekdayPart = $this->getExpression(self::WEEKDAY);
        $useUnixDayOrWeekday = $this->isUnixExpression && $dayPart !== '*' && $weekdayPart !== '*';
        foreach (self::$order as $position) {
            $part = $this->getExpression($position);
            if (null === $part || '*' === $part ||
                ($useUnixDayOrWeekday && ($position === self::DAY || $position === self::WEEKDAY))) {
                continue;
            }
            $parts[$position] = $part;
            $fields[$position] = $this->fieldFactory->getField($position);
        }

        // 达到上限后终止搜索，避免不存在的日期导致无限循环。
        for ($i = 0; $i < $this->maxIterationCount; $i++) {

            // Unix Cron 在日期和星期均受限时，任一字段匹配即可执行。
            if ($useUnixDayOrWeekday &&
                !$this->isPartSatisfied($nextRun, self::DAY, $dayPart) &&
                !$this->isPartSatisfied($nextRun, self::WEEKDAY, $weekdayPart)) {
                $this->fieldFactory->getField(self::DAY)->increment($nextRun, $invert);
                continue;
            }

            foreach ($parts as $position => $part) {
                // 当前字段不匹配时，按字段粒度推进时间后重新匹配。
                if (!$this->isPartSatisfied($nextRun, $position, $part)) {
                    $fields[$position]->increment($nextRun, $invert, $part);
                    continue 2;
                }
            }

            // 根据调用方要求跳过当前匹配结果。
            if ((!$allowCurrentDate && $nextRun == $currentDate) || --$nth > -1) {
                $this->fieldFactory->getField(0)->increment($nextRun, $invert, isset($parts[0]) ? $parts[0] : null);
                continue;
            }

            return $nextRun;
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException('Impossible CRON expression');
        // @codeCoverageIgnoreEnd
    }

    /**
     * 判断指定字段是否匹配当前时间，兼容列表表达式。
     *
     * @param DateTime $date     待判断时间
     * @param int      $position 字段位置
     * @param string   $part     字段表达式
     *
     * @return bool
     */
    protected function isPartSatisfied(DateTime $date, $position, $part)
    {
        $field = $this->fieldFactory->getField($position);
        foreach (array_map('trim', explode(',', $part)) as $listPart) {
            if ($field->isSatisfiedBy($date, $listPart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 根据语义化调度参数生成 Cron 表达式。
     *
     * $unit 支持 second、minute、hour、day、week、month，其中 second 至 day
     * 使用 $interval 指定 Cron 字段的步长；week 与 month 的 $interval 必须为 1，
     * 因为标准 Cron 无法精确表达每 N 周或每 N 月。
     *
     * $options 支持以下条件：second（0-59）、minute（0-59）、hour（0-23）、
     * weekday（0-7，仅 week）、day（1-31，仅 month）、format（5、6、7）和 year。
     * 未指定 format 时默认生成六段表达式；指定 year 时默认生成七段表达式。五段
     * 表达式不包含秒字段，因此不支持 second 单位。
     *
     * @param string $unit     调度单位
     * @param int    $interval 执行间隔
     * @param array  $options  时间或日期条件
     *
     * @return string 生成的 Cron 表达式
     * @throws InvalidArgumentException 当调度参数不合法时抛出
     */
    public static function generate($unit, $interval = 1, array $options = array())
    {
        return self::buildScheduleExpression($unit, $interval, $options);
    }

    /**
     * 将语义化调度参数转换为五段、六段或七段 Cron 表达式。
     *
     * @param string $unit     调度单位
     * @param int    $interval 执行间隔
     * @param array  $options  时间或日期条件
     *
     * @return string
     * @throws InvalidArgumentException 当调度参数不合法时抛出
     */
    private static function buildScheduleExpression($unit, $interval, array $options)
    {
        $unit = self::normalizeScheduleUnit($unit);
        $format = self::getScheduleFormat($options);
        $second = self::getScheduleOption($options, 'second', 0, 0, 59);
        $minute = self::getScheduleOption($options, 'minute', 0, 0, 59);
        $hour = self::getScheduleOption($options, 'hour', 0, 0, 23);
        $day = '*';
        $month = '*';
        $weekday = '?';

        switch ($unit) {
            case 'second':
                $second = '*/' . self::validateScheduleInterval($interval, 59);
                $minute = '*';
                $hour = '*';
                break;

            case 'minute':
                $minute = '*/' . self::validateScheduleInterval($interval, 59);
                $hour = '*';
                break;

            case 'hour':
                $hour = '*/' . self::validateScheduleInterval($interval, 23);
                break;

            case 'day':
                $day = '*/' . self::validateScheduleInterval($interval, 31);
                break;

            case 'week':
                self::validateScheduleInterval($interval, 1);
                $day = '?';
                $weekday = self::getScheduleOption($options, 'weekday', 0, 0, 7);
                break;

            case 'month':
                self::validateScheduleInterval($interval, 1);
                $day = self::getScheduleOption($options, 'day', 1, 1, 31);
                break;
        }

        return self::formatScheduleExpression($format, $second, $minute, $hour, $day, $month, $weekday, $options);
    }

    /**
     * 根据调度选项确定目标 Cron 格式。
     *
     * @param array $options 调度选项
     *
     * @return int
     * @throws InvalidArgumentException 当格式与 year 参数冲突时抛出
     */
    private static function getScheduleFormat(array $options)
    {
        $format = array_key_exists('format', $options)
            ? self::validateScheduleValue($options['format'], 'format', 5, 7)
            : (array_key_exists('year', $options) ? 7 : 6);

        if ($format != 7 && array_key_exists('year', $options)) {
            throw new InvalidArgumentException('year is only supported by seven-part Cron expressions');
        }

        return $format;
    }

    /**
     * 按目标格式组装 Cron 字段。
     *
     * @param int    $format  Cron 字段数量
     * @param string $second  秒字段
     * @param string $minute  分字段
     * @param string $hour    时字段
     * @param string $day     日字段
     * @param string $month   月字段
     * @param string $weekday 星期字段
     * @param array  $options 调度选项
     *
     * @return string
     * @throws InvalidArgumentException 当五段格式包含秒级调度时抛出
     */
    private static function formatScheduleExpression($format, $second, $minute, $hour, $day, $month, $weekday, array $options)
    {
        if ($format == 5) {
            if ($second !== 0) {
                throw new InvalidArgumentException('Five-part Cron expressions do not support second-level schedules');
            }

            return $minute . ' ' . $hour . ' ' . str_replace('?', '*', $day) . ' ' . $month . ' ' . str_replace('?', '*', $weekday);
        }

        $expression = $second . ' ' . $minute . ' ' . $hour . ' ' . $day . ' ' . $month . ' ' . $weekday;
        if ($format == 7) {
            $expression .= ' ' . (array_key_exists('year', $options) ? $options['year'] : '*');
        }

        return $expression;
    }

    /**
     * 规范化外部传入的调度单位。
     *
     * @param string $unit 调度单位
     *
     * @return string
     * @throws InvalidArgumentException 当调度单位不受支持时抛出
     */
    private static function normalizeScheduleUnit($unit)
    {
        $unit = strtolower(trim((string) $unit));
        $units = array(
            'second' => 'second',
            'seconds' => 'second',
            'minute' => 'minute',
            'minutes' => 'minute',
            'hour' => 'hour',
            'hours' => 'hour',
            'day' => 'day',
            'days' => 'day',
            'daily' => 'day',
            'week' => 'week',
            'weekly' => 'week',
            'month' => 'month',
            'monthly' => 'month'
        );

        if (!isset($units[$unit])) {
            throw new InvalidArgumentException('Unsupported schedule unit: ' . $unit);
        }

        return $units[$unit];
    }

    /**
     * 读取调度选项中的数值字段，并在未指定时使用默认值。
     *
     * @param array  $options 调度选项
     * @param string $key     选项名称
     * @param int    $default 默认值
     * @param int    $minimum 最小值
     * @param int    $maximum 最大值
     *
     * @return int
     * @throws InvalidArgumentException 当选项值不合法时抛出
     */
    private static function getScheduleOption(array $options, $key, $default, $minimum, $maximum)
    {
        $value = array_key_exists($key, $options) ? $options[$key] : $default;

        return self::validateScheduleValue($value, $key, $minimum, $maximum);
    }

    /**
     * 校验间隔值是否适用于当前 Cron 字段。
     *
     * @param int $interval 间隔值
     * @param int $maximum  最大间隔值
     *
     * @return int
     * @throws InvalidArgumentException 当间隔值不合法时抛出
     */
    private static function validateScheduleInterval($interval, $maximum)
    {
        return self::validateScheduleValue($interval, 'interval', 1, $maximum);
    }

    /**
     * 校验调度参数是否为指定范围内的整数。
     *
     * @param mixed  $value   参数值
     * @param string $name    参数名称
     * @param int    $minimum 最小值
     * @param int    $maximum 最大值
     *
     * @return int
     * @throws InvalidArgumentException 当参数值不合法时抛出
     */
    private static function validateScheduleValue($value, $name, $minimum, $maximum)
    {
        if (!preg_match('/^\d+$/', (string) $value) || $value < $minimum || $value > $maximum) {
            throw new InvalidArgumentException(
                $name . ' must be an integer between ' . $minimum . ' and ' . $maximum
            );
        }

        return (int) $value;
    }
}
