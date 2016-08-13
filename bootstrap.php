<?php

/**
 * Bootstrap script for test exam.
 * Define constants below to set number of users and payment dates interval.
 * Create database `mediatech` for user `mediatech`@`localhost` with password 'mediatech'
 * Then, run this script as CLI like this: php bootstrap.php 1000 2014-01-01
 */

// Assume that command line args are correct
$users_count = isset($argv[1]) ? $argv[1] : 1000;
$date_start = isset($argv[2]) ? $argv[2] : '2014-01-01';

$dbconfig = parse_ini_file('mysql.ini');
$db = new PDO(
    'mysql:host=' . $dbconfig['host'] .
    ';port=' . $dbconfig['port'] .
    ';dbname=' . $dbconfig['dbname'],
    $dbconfig['user'],
    $dbconfig['password']
);

echo "Bootstrap 1 start...\n";

// Create tables

$db->exec("
    DROP TABLE IF EXISTS `user`;
    CREATE TABLE `user` (
        `user_id` INT NOT NULL AUTO_INCREMENT,
        `hold_rule` SMALLINT NOT NULL,
        PRIMARY KEY (`user_id`)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

$db->exec("
    DROP TABLE IF EXISTS `earned`;
    CREATE TABLE `earned` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `date` DATE NOT NULL,
        `earned` DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (`id`)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

$db->exec("
    DROP TABLE IF EXISTS `paid`;
    CREATE TABLE `paid` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `paid_amount` DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (`id`)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

// Date functions for SQL queries

$db->exec("
    DROP FUNCTION IF EXISTS `PERIOD_FIRST_DATE`;
    CREATE FUNCTION `PERIOD_FIRST_DATE` (dt date) RETURNS date
    DETERMINISTIC
    BEGIN
        IF DAY(dt) >= 16 THEN
            RETURN DATE_FORMAT(dt, '%Y-%m-16');
        ELSEIF DAY(dt) >= 1 THEN
            RETURN DATE_FORMAT(dt, '%Y-%m-01');
        END IF;
    END;

    DROP FUNCTION IF EXISTS `PERIOD_LAST_DATE`;
    CREATE FUNCTION `PERIOD_LAST_DATE` (dt date) RETURNS date
    DETERMINISTIC
    BEGIN
        IF DAY(dt) >= 16 THEN
            RETURN DATE_FORMAT(dt + INTERVAL 1 MONTH, '%Y-%m-01') - INTERVAL 1 DAY;
        ELSEIF DAY(dt) >= 1 THEN
            RETURN DATE_FORMAT(dt, '%Y-%m-15');
        END IF;
    END;

    DROP FUNCTION IF EXISTS `PERIOD_N_FIRST_DATE`;
    CREATE FUNCTION `PERIOD_N_FIRST_DATE` (period_num int, dt date) RETURNS date
    DETERMINISTIC
    BEGIN
        SET dt = PERIOD_FIRST_DATE(dt);
        WHILE period_num > 0 DO
            SET dt = PERIOD_FIRST_DATE(dt - INTERVAL 1 DAY);
            SET period_num = period_num - 1;
        END WHILE;
        RETURN dt;
    END;

    DROP FUNCTION IF EXISTS `PERIOD_N_LAST_DATE`;
    CREATE FUNCTION `PERIOD_N_LAST_DATE` (period_num int, dt date) RETURNS date
    DETERMINISTIC
    BEGIN
        RETURN PERIOD_LAST_DATE(PERIOD_N_FIRST_DATE(period_num, dt));
    END;
");

// Create users

echo "Create users...\n";
$hold_rules = [];
for ($i = 0; $i < $users_count; $i++)
    $hold_rules[] = rand(1, 2);
mass_insert("INSERT INTO `user` (`hold_rule`) VALUES ", 1, $hold_rules);
$users = $db
    ->query("SELECT `user_id`, PERIOD_N_FIRST_DATE(`hold_rule`, CURDATE()) - INTERVAL 1 DAY AS `last_payable_date` FROM `user`")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

// Create earnings and payments

echo "Create earnings and payments...\n";
date_default_timezone_set('UTC');
$date = new DateTime($date_start);
$date_end = (new DateTime('now'))->setTime(0, 0, 0);
$sql_values_earnings = [];
$sql_values_payments = [];
while (true) {
    foreach ($users as $user_id => $last_payment_date) {
        // Skip earning creating this day for current user
        if (rand(0, 5) != 0) continue;
        $earned = rand(1, 10000) / 100.0;
        $sql_values_earnings[] = $user_id;
        $sql_values_earnings[] = $date->format('Y-m-d');
        $sql_values_earnings[] = $earned;
        if (count($sql_values_earnings) > 5000 * 3) {
            echo "\r" . $date->format('Y-m-d');
            mass_insert("INSERT INTO `earned` (`user_id`, `date`, `earned`) VALUES ", 3, $sql_values_earnings);
        }

        if ($date->getTimestamp() >= (new DateTime($last_payment_date))->getTimestamp()) continue;
        $paid = $earned;
        $sql_values_payments[] = $user_id;
        $sql_values_payments[] = $paid;
        if (count($sql_values_payments) > 5000 * 3) {
            mass_insert("INSERT INTO `paid` (`user_id`, `paid_amount`) VALUES ", 2, $sql_values_payments);
        }
    }
    $date = $date->add(new DateInterval("P1D"));
    if ($date->getTimestamp() > $date_end->getTimestamp()) break;
}
if ($sql_values_earnings) mass_insert("INSERT INTO `earned` (`user_id`, `date`, `earned`) VALUES ", 3, $sql_values_earnings);
if ($sql_values_payments) mass_insert("INSERT INTO `paid` (`user_id`, `paid_amount`) VALUES ", 2, $sql_values_payments);

echo "\rBootstrap 1 done.\n";

function mass_insert($sql_template, $num_columns, &$sql_values) {
    global $db;
    $placeholder_group = '(' . implode(',', array_fill(0, $num_columns, '?')) . ')';
    $full_sql = $sql_template . implode(',', array_fill(0, count($sql_values) / $num_columns, $placeholder_group));
    $db->prepare($full_sql)->execute($sql_values);
    $sql_values = [];
}