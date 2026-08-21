<?php

spl_autoload_register(function ($class) {
    $prefix = 'Creatcode\\Cronexp\\';
    if (strpos($class, $prefix) === 0) {
        require __DIR__ . '/../src/' . substr($class, strlen($prefix)) . '.php';
    }
});

use Creatcode\Cronexp\CronExpression;

$failures = array();
$validExpressions = array(
    '*/5 * * * *',
    '*/10 * * * * *',
    '0 0 0 1 JAN *',
    '0 15 10 * * MON-FRI',
    '0 0 0 * * 0,7',
    '@daily'
);
$invalidExpressions = array(
    '0 60 * * * *',
    '0 0 24 * * *',
    '0 0 0 32 * *',
    '0 0 0 * 13 *',
    '0 0 0 * * 8',
    '*/0 * * * * *',
    '0 0 0 ? * MON',
    '0 0 0 L * *',
    '0 0 0 99W * *',
    '0 0 0 * * 5#3',
    '0 0 0 1 1 * 2027',
    '0 0 0 * * * * *'
);

foreach ($validExpressions as $expression) {
    if (!CronExpression::isValidExpression($expression)) {
        $failures[] = '应接受表达式：' . $expression;
    }
}

foreach ($invalidExpressions as $expression) {
    if (CronExpression::isValidExpression($expression)) {
        $failures[] = '应拒绝表达式：' . $expression;
    }
}

$nextRun = CronExpression::factory('*/5 * * * *')
    ->getNextRunDate('2026-08-21 08:07:05', 0, false, 'Asia/Shanghai')
    ->format('Y-m-d H:i:s');
if ($nextRun !== '2026-08-21 08:10:00') {
    $failures[] = '五段表达式计算错误：' . $nextRun;
}

$unixNextRun = CronExpression::factory('0 0 1 * MON')
    ->getNextRunDate('2026-08-21 08:07:05', 0, false, 'Asia/Shanghai')
    ->format('Y-m-d H:i:s');
if ($unixNextRun !== '2026-08-24 00:00:00') {
    $failures[] = 'Unix 日期和星期 OR 语义错误：' . $unixNextRun;
}

$secondLevelNextRun = CronExpression::factory('0 0 0 1 * MON')
    ->getNextRunDate('2026-08-21 08:07:05', 0, false, 'Asia/Shanghai')
    ->format('Y-m-d H:i:s');
if ($secondLevelNextRun !== '2026-08-24 00:00:00') {
    $failures[] = '秒级日期和星期 OR 语义错误：' . $secondLevelNextRun;
}

if (!CronExpression::factory('*/10 * * * * *')->isDue('2026-08-21 08:07:10', 'Asia/Shanghai')) {
    $failures[] = '秒级 isDue() 判断错误';
}

$stepNextRun = CronExpression::factory('5/20 * * * * *')
    ->getNextRunDate('2026-08-21 08:07:05', 0, false, 'Asia/Shanghai')
    ->format('Y-m-d H:i:s');
if ($stepNextRun !== '2026-08-21 08:07:25') {
    $failures[] = '秒字段起始步进计算错误：' . $stepNextRun;
}

$schedules = array(
    array('second', 10, array(), '*/10 * * * * *'),
    array('minute', 5, array('second' => 10), '10 */5 * * * *'),
    array('hour', 3, array('minute' => 15), '0 15 */3 * * *'),
    array('day', 2, array('hour' => 8), '0 0 8 */2 * *'),
    array('week', 1, array('weekday' => 1, 'hour' => 9), '0 0 9 * * 1'),
    array('month', 1, array('day' => 15, 'hour' => 9), '0 0 9 15 * *')
);

foreach ($schedules as $schedule) {
    $expression = CronExpression::generate($schedule[0], $schedule[1], $schedule[2]);
    if ($expression !== $schedule[3]) {
        $failures[] = '语义化调度生成错误：' . $expression;
    }
    if (!CronExpression::isValidExpression($expression)) {
        $failures[] = '语义化调度生成了无效表达式：' . $expression;
    }
}

$fivePartExpression = CronExpression::generate('minute', 5, array('format' => 5));
if ($fivePartExpression !== '*/5 * * * *') {
    $failures[] = '五段调度生成错误：' . $fivePartExpression;
}
if (!CronExpression::isValidExpression($fivePartExpression)) {
    $failures[] = '五段调度生成了无效表达式：' . $fivePartExpression;
}

try {
    CronExpression::generate('minute', 0);
    $failures[] = '应拒绝无效的调度间隔';
} catch (InvalidArgumentException $e) {
}

try {
    CronExpression::generate('minute', 5, array('format' => 7));
    $failures[] = '应拒绝七段 Cron 格式';
} catch (InvalidArgumentException $e) {
}

try {
    CronExpression::generate('minute', 5, array('year' => 2027));
    $failures[] = '应拒绝 year 调度选项';
} catch (InvalidArgumentException $e) {
}

try {
    CronExpression::generate('week', 2);
    $failures[] = '应拒绝无法精确表达的周间隔';
} catch (InvalidArgumentException $e) {
}

try {
    CronExpression::generate('second', 10, array('format' => 5));
    $failures[] = '应拒绝使用五段格式的秒级调度';
} catch (InvalidArgumentException $e) {
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Cron 表达式回归测试通过。" . PHP_EOL;
