<?php
// --- Telegram token: берём из переменной окружения Railway, fallback на хардкод ---
$tg_token = getenv('BOT_TOKEN') ?: "PUT_NEW_TOKEN_HERE";

// --- Подключение к MySQL через MYSQL_URL, которую даёт Railway ---
// Формат: mysql://root:PASSWORD@mysql.railway.internal:3306/railway
$mysql_url = getenv('MYSQL_URL');

if ($mysql_url) {
    $u = parse_url($mysql_url);
    $dbhost     = $u['host'];
    $dbport     = $u['port'] ?? 3306;
    $dbuser     = rawurldecode($u['user']);
    $dbpassword = rawurldecode($u['pass']);
    $dbname     = ltrim($u['path'] ?? '/railway', '/');
} else {
    // запасной вариант через отдельные переменные Railway
    $dbhost     = getenv('MYSQLHOST')     ?: 'localhost';
    $dbport     = getenv('MYSQLPORT')     ?: 3306;
    $dbuser     = getenv('MYSQLUSER')     ?: 'root';
    $dbpassword = getenv('MYSQLPASSWORD') ?: '';
    $dbname     = getenv('MYSQLDATABASE') ?: 'railway';
}

$link = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbname, (int)$dbport);
if (!$link) {
    // не молчим, а пишем причину в лог — иначе бот «умирает» без следов
    error_log("DB connect failed: " . mysqli_connect_error());
    exit;
}
mysqli_query($link, "SET NAMES 'utf8mb4'");

$tableCheckQuery = "SHOW TABLES LIKE 'users'";
$tableExists = mysqli_query($link, $tableCheckQuery);

if (mysqli_num_rows($tableExists) == 0) {
    $createQuery = "
        CREATE TABLE `users` (
            `id` int(11) NOT NULL,
            `date` timestamp NOT NULL DEFAULT current_timestamp(),
            `telegram_id` bigint(20) NOT NULL,
            `user_name` varchar(100) NOT NULL DEFAULT '',
            `balance` bigint(20) NOT NULL DEFAULT 0,
            `income` bigint(20) NOT NULL DEFAULT 0,
            `tickets` int(11) NOT NULL DEFAULT 3,
            `referal` int(11) NOT NULL DEFAULT 0,
            `ref_str` varchar(16) NOT NULL,
            `daily_income` bigint(20) NOT NULL DEFAULT 0,
            `tmp_sum` bigint(20) NOT NULL DEFAULT 0,
            `avatar` varchar(512) NOT NULL DEFAULT 'https://upload.wikimedia.org/wikipedia/commons/7/7c/Profile_avatar_placeholder_large.png?20150327203541',
            `last_ticket` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
            `last_game` timestamp NULL DEFAULT '0000-00-00 00:00:00',
            `boosters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '[]' CHECK (json_valid(`boosters`))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        ALTER TABLE `users`
          ADD PRIMARY KEY (`id`),
          ADD KEY `telegram_id` (`telegram_id`,`referal`,`ref_str`);

        ALTER TABLE `users`
          MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
    ";

    if (mysqli_multi_query($link, $createQuery)) {
        do {
            if ($result = mysqli_store_result($link)) {
                mysqli_free_result($result);
            }
        } while (mysqli_next_result($link));
    } else {
        $link->close();
        exit;
    }
}
?>
