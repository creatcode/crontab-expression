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
    '0 15 10 ? * MON-FRI',
    '0 0 0 L * *',
    '0 0 9 15W * *',
    '0 0 10 ? * 5L',
    '0 0 10 ? * 5#3',
    '0 0 0 1 JAN *',
    '0 0 0 1 1 * 2027',
    '0 0 0 1 1 * 9999',
    '@daily'
);
$invalidExpressions = array(
    '0 60 * * * *',
    '0 0 24 * * *',
    '0 0 0 32 * *',
    '0 0 0 * 13 *',
    '0 0 0 * * 8',
    '0 0 0 * * * 10000',
    '*/0 * * * * *',
    '0 0 ? * MON',
    '0 0 0 99W * *',
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

if (!CronExpression::factory('*/10 * * * * *')->isDue('2026-08-21 08:07:10', 'Asia/Shanghai')) {
    $failures[] = '秒级 isDue() 判断错误';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Cron 表达式回归测试通过。" . PHP_EOL;
