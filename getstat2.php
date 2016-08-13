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
        `user2`.`user_id`,
        `hold_rule`,
        `total_earned`,
        `total_paid`,
        IFNULL(`ep_0`.`sum_earned`, 0) AS `earned_0`,
        IFNULL(`ep_1`.`sum_earned`, 0) AS `earned_1`,
        IFNULL(`ep_2`.`sum_earned`, 0) AS `earned_2`,
        IFNULL(`ep_3`.`sum_earned`, 0) AS `earned_3`,
        IFNULL(`ep_4`.`sum_earned`, 0) AS `earned_4`,
        IFNULL(`ep_5`.`sum_earned`, 0) AS `earned_5`,
        IFNULL(`ep_6`.`sum_earned`, 0) AS `earned_6`,
        IFNULL(`ep_7`.`sum_earned`, 0) AS `earned_7`,
        `total_earned` - `total_paid` - IF(
                `hold_rule` = 1,
                IFNULL(`ep_0`.`sum_earned`, 0) + IFNULL(`ep_1`.`sum_earned`, 0),
                IFNULL(`ep_0`.`sum_earned`, 0) + IFNULL(`ep_1`.`sum_earned`, 0) + IFNULL(`ep_2`.`sum_earned`, 0)
            ) AS `can_be_paid`,
        `total_earned` - `total_paid` AS `balance`
    FROM `user2`
    LEFT JOIN `earned_by_period` AS `ep_0` ON (`ep_0`.`user_id` = `user2`.`user_id` AND `ep_0`.`date_start` = @date0)
    LEFT JOIN `earned_by_period` AS `ep_1` ON (`ep_1`.`user_id` = `user2`.`user_id` AND `ep_1`.`date_start` = @date1)
    LEFT JOIN `earned_by_period` AS `ep_2` ON (`ep_2`.`user_id` = `user2`.`user_id` AND `ep_2`.`date_start` = @date2)
    LEFT JOIN `earned_by_period` AS `ep_3` ON (`ep_3`.`user_id` = `user2`.`user_id` AND `ep_3`.`date_start` = @date3)
    LEFT JOIN `earned_by_period` AS `ep_4` ON (`ep_4`.`user_id` = `user2`.`user_id` AND `ep_4`.`date_start` = @date4)
    LEFT JOIN `earned_by_period` AS `ep_5` ON (`ep_5`.`user_id` = `user2`.`user_id` AND `ep_5`.`date_start` = @date5)
    LEFT JOIN `earned_by_period` AS `ep_6` ON (`ep_6`.`user_id` = `user2`.`user_id` AND `ep_6`.`date_start` = @date6)
    LEFT JOIN `earned_by_period` AS `ep_7` ON (`ep_7`.`user_id` = `user2`.`user_id` AND `ep_7`.`date_start` = @date7)
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