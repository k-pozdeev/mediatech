<?php

/**
 * Bootstrap script for test exam. Version 2 with a goal to improve performance.
 * Run this script as CLI after bootstrap.php has already been run.
 */

$dbconfig = parse_ini_file('mysql.ini');
$db = new PDO(
    'mysql:host=' . $dbconfig['host'] .
    ';port=' . $dbconfig['port'] .
    ';dbname=' . $dbconfig['dbname'],
    $dbconfig['user'],
    $dbconfig['password']
);

echo "Bootstrap 2 start...\n";

// Create tables

$db->exec("
    DROP TABLE IF EXISTS `user2`;
    CREATE TABLE `user2` (
        `user_id` INT NOT NULL AUTO_INCREMENT,
        `hold_rule` SMALLINT NOT NULL,
        `total_earned` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `total_paid` DECIMAL(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`user_id`)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

$db->exec("
    DROP TABLE IF EXISTS `earned2`;
    CREATE TABLE `earned2` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `date` DATE NOT NULL,
        `earned` DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (`id`)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

$db->exec("
    DROP TABLE IF EXISTS `paid2`;
    CREATE TABLE `paid2` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `paid_amount` DECIMAL(10,2) NOT NULL,
        PRIMARY KEY (`id`)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

$db->exec("
    DROP TABLE IF EXISTS `earned_by_period`;
    CREATE TABLE `earned_by_period` (
        `date_start` DATE NOT NULL,
        `date_end` DATE NOT NULL,
        `user_id` INT NOT NULL,
        `sum_earned` DECIMAL(15,2) NOT NULL DEFAULT 0,
        PRIMARY KEY (`date_start`, `date_end`, `user_id`),
        INDEX `date_user` (`date_start` ASC, `user_id` ASC)
    ) CHARACTER SET 'UTF8' ENGINE='InnoDB'
");

// Create triggers

$db->exec("
    DROP TRIGGER IF EXISTS `ON_EARNING_INSERT`;

    CREATE TRIGGER `ON_EARNING_INSERT`
    AFTER INSERT
    ON `earned2` FOR EACH ROW
    BEGIN
        UPDATE `user2`
        SET `total_earned` = `total_earned` + NEW.`earned`
        WHERE `user_id` = NEW.`user_id`;

        INSERT INTO `earned_by_period` (`date_start`, `date_end`, `user_id`, `sum_earned`)
        VALUES (PERIOD_FIRST_DATE(NEW.`date`), PERIOD_LAST_DATE(NEW.`date`), NEW.`user_id`, NEW.`earned`)
        ON DUPLICATE KEY UPDATE
        `sum_earned` = `sum_earned` + NEW.`earned`;
    END;
");

$db->exec("
    DROP TRIGGER IF EXISTS `ON_PAYMENT_INSERT`;
    CREATE TRIGGER `ON_PAYMENT_INSERT`
    AFTER INSERT
    ON `paid2` FOR EACH ROW
    BEGIN
        UPDATE `user2`
        SET `total_paid` = `total_paid` + NEW.`paid_amount`
        WHERE `user_id` = NEW.`user_id`;
    END;
");

// Create users

echo "Copy users...\n";
$db->exec("
    INSERT INTO `user2` (`user_id`, `hold_rule`)
    SELECT `user_id`, `hold_rule`
    FROM `user`
");

echo "Copy earnings...\n";
$db->exec("
    INSERT INTO `earned2`
    SELECT * FROM `earned`
");

echo "Copy payments...\n";
$db->exec("
    INSERT INTO `paid2`
    SELECT * FROM `paid`
");

echo "Bootstrap 2 end...\n";