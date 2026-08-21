<?php


namespace Creatcode\Cronexp;

use InvalidArgumentException;

/**
 * Cron 字段享元工厂。
 * @link http://en.wikipedia.org/wiki/Cron
 * 支持秒字段的创建。
 */
class FieldFactory
{
    /**
     * @var array 已创建字段对象的缓存
     */
    private $fields = array();

    /**
     * 根据 Cron 字段位置获取对应的字段对象。
     *
     * @param int $position Cron 字段位置
     *
     * @return FieldInterface
     * @throws InvalidArgumentException 字段位置无效时抛出
     */
    public function getField($position)
    {
        if (!isset($this->fields[$position])) {
            switch ($position) {
                case 0:
                    $this->fields[$position] = new SecondsField();
                    break;
                case 1:
                    $this->fields[$position] = new MinutesField();
                    break;
                case 2:
                    $this->fields[$position] = new HoursField();
                    break;
                case 3:
                    $this->fields[$position] = new DayOfMonthField();
                    break;
                case 4:
                    $this->fields[$position] = new MonthField();
                    break;
                case 5:
                    $this->fields[$position] = new DayOfWeekField();
                    break;
                case 6:
                    $this->fields[$position] = new YearField();
                    break;
                default:
                    throw new InvalidArgumentException(
                        $position . ' is not a valid position'
                    );
            }
        }

        return $this->fields[$position];
    }
}
