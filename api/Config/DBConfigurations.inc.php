<?php

// Gets environment configuration variables.
$env[MYSQL] = [
  'DB_MYSQL_HOST' => filter_var($_ENV['DB_MYSQL_HOST'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
  'DB_MYSQL_NAME' => filter_var($_ENV['DB_MYSQL_NAME'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
  'DB_MYSQL_USER' => filter_var($_ENV['DB_MYSQL_USER'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
  'DB_MYSQL_PASSWORD_FILE' => filter_var($_ENV['DB_MYSQL_PASSWORD_FILE'], FILTER_SANITIZE_URL),
  'DB_MYSQL_CHARSET' => filter_var($_ENV['DB_MYSQL_CHARSET'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH),
];
// Additional driver array go here ...
// $env[POSTGRESQL] = [ ... ];

// Sets Database configuration variables.
global $db_configs;
$db_configs = [
  MYSQL => [
    DB_USERNAME => $env[MYSQL]['DB_MYSQL_USER'],
    DB_PASSWORD => trim(file_get_contents($env[MYSQL]['DB_MYSQL_PASSWORD_FILE'])),
    DSN => sprintf('mysql:host=%s;dbname=%s;charset=%s',
                   $env[MYSQL]['DB_MYSQL_HOST'],
                   $env[MYSQL]['DB_MYSQL_NAME'],
                   $env[MYSQL]['DB_MYSQL_CHARSET']
    ),
    SCHEMA => [
      'TABLE' => DATES_TABLE,
      'VIEW' => DATES_VIEW,
      'VIEW_ALL' => DATES_VIEW_ALL,
      DATE_STARTS => PDO::PARAM_STR,
      DATE_ENDS => PDO::PARAM_STR,
      PRICE => PDO::PARAM_STR
    ]
  ],
  // Additional driver array go here ...
  // POSTGRESQL => [ ... ],
  PDO => [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_PERSISTENT => FALSE,
    PDO::ATTR_AUTOCOMMIT => FALSE,
    PDO::ATTR_EMULATE_PREPARES => FALSE,
  ],
];

unset($env);
