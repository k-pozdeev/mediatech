<?php

$quiet = (isset($argv[1]) && $argv[1] == '--quiet') ? true : false;

date_default_timezone_set('UTC');
$dbconfig = parse_ini_file('mysql.ini');
$db = new PDO(
    'mysql:host=' . $dbconfig['host'] .
    ';port=' . $dbconfig['port'] .
    ';dbname=' . $dbconfig['dbname'],
    $dbconfig['user'],
    $dbconfig['password']
);

$db->exec("
    SET @today = CURDATE();

    SET @date0 = PERIOD_N_FIRST_DATE(0, @today);
    SET @date1 = PERIOD_N_FIRST_DATE(1, @today);
    SET @date2 = PERIOD_N_FIRST_DATE(2, @today);
    SET @date3 = PERIOD_N_FIRST_DATE(3, @today);
    SET @date4 = PERIOD_N_FIRST_DATE(4, @today);
    SET @date5 = PERIOD_N_FIRST_DATE(5, @today);
    SET @date6 = PERIOD_N_FIRST_DATE(6, @today);
    SET @date7 = PERIOD_N_FIRST_DATE(7, @today);
");

$stmt = $db->query("
    SELECT
        `user_id`,
        `hold_rule`,
        SUM(`earned`) AS `total_earned`,
        `total_paid`,
        SUM(IF(`date` >= @date0, `earned`, 0)) as `period_0`,
        SUM(IF(`date` >= @date1 AND `date` < @date0, `earned`, 0)) as `period_1`,
        SUM(IF(`date` >= @date2 AND `date` < @date1, `earned`, 0)) as `period_2`,
        SUM(IF(`date` >= @date3 AND `date` < @date2, `earned`, 0)) as `period_3`,
        SUM(IF(`date` >= @date4 AND `date` < @date3, `earned`, 0)) as `period_4`,
        SUM(IF(`date` >= @date5 AND `date` < @date4, `earned`, 0)) as `period_5`,
        SUM(IF(`date` >= @date6 AND `date` < @date5, `earned`, 0)) as `period_6`,
        SUM(IF(`date` >= @date7 AND `date` < @date6, `earned`, 0)) as `period_7`,
        SUM(`earned`) - `total_paid` - SUM(IF(`date` > `last_payable_date`, `earned`, 0)) AS `can_be_paid`,
        SUM(`earned`) - `total_paid` AS `balance`
    FROM `earned`
    JOIN (
        SELECT `user_id`, `hold_rule`, PERIOD_N_FIRST_DATE(`hold_rule`, @today) - INTERVAL 1 DAY AS `last_payable_date`
        FROM `user`
    ) AS `user_extended` USING (`user_id`)
    JOIN (
        SELECT `user_id`, SUM(`paid_amount`) AS `total_paid`
        FROM `paid`
        GROUP BY `user_id`
    ) AS `total_paid` USING (`user_id`)
    GROUP BY `user_id`
");
$data = $stmt->fetchAll(PDO::FETCH_NUM);
$stmt->closeCursor();

$headers_stmt = $db->query("
    SELECT
        'USER',
        'HOLD RULE',
        'TOTAL EARNED',
        'TOTAL PAID',
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(0, @today), PERIOD_N_LAST_DATE(0, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(1, @today), PERIOD_N_LAST_DATE(1, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(2, @today), PERIOD_N_LAST_DATE(2, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(3, @today), PERIOD_N_LAST_DATE(3, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(4, @today), PERIOD_N_LAST_DATE(4, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(5, @today), PERIOD_N_LAST_DATE(5, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(6, @today), PERIOD_N_LAST_DATE(6, @today)),
        CONCAT_WS(' - ', PERIOD_N_FIRST_DATE(7, @today), PERIOD_N_LAST_DATE(7, @today)),
        'CAN BE PAID',
        'BALANCE'
");

$headers = $headers_stmt->fetch(PDO::FETCH_NUM);

if (!$quiet)
    foreach ($data as $row) {
        print_r(array_combine($headers, $row));
    }