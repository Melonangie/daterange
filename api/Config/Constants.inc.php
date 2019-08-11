<?php

// Set charset constant.
// define('CHARSET', mb_strtoupper(filter_input(INPUT_ENV, 'CHARSET', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)));
define('CHARSET', mb_strtoupper(filter_var($_ENV['CHARSET'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)));
define('PROTOCOL', filter_var($_SERVER['REQUEST_SCHEME'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH));
define('HOST', filter_var($_SERVER['HTTP_HOST'], FILTER_SANITIZE_URL));

// Define DB constants.
define('MYSQL', 'mysql');
define('DB_CHARSET', 'charset');
define('DB_USERNAME', 'username');
define('DB_PASSWORD', 'password');
define('DSN', 'dsn');
define('PDO', 'pdo');
define('PDO_PARAMS', 'pdo_params');
define('SCHEMA', 'schema');
define('TABLE', 'table');
define('VIEW', 'view');
define('DATES_TABLE', 'dates');
define('DATES_VIEW', 'date_ranges');

// Query constants.
define('FILTER', 'filter');
define('OFFSET', 'offset');
define('LIMIT', 'limit');
define('FIELDS', 'fields');
define('SORT', 'sort');
define('DESC', 'DESC');
define('ASC', 'ASC');

// Define HTTP constants.
define('GET', 'GET');
define('POST', 'POST');
define('PUT', 'PUT');
define('DELETE', 'DELETE');
define('FILE', 'FILE');
define('ALLOWED_METHODS', [GET, POST, PUT, DELETE]);
define('CONTENT_TYPE_JSON', 'application/json');
define('CONTENT_TYPE_CHARSET_JSON', CONTENT_TYPE_JSON . '; charset=' . CHARSET);
define('BASE_PATH', PROTOCOL . '://' . HOST);
define('ROUTE', 'route');
define('PARAMETER', 'param');

// Define App constants.
define('ID', 'id');
define('MODIFIED', 'modified');
define('DATE_STARTS', 'date_start');
define('DATE_END', 'date_end');
define('PRICE', 'price');
define('V1', 'v1');
define('RESOURCE_NAMESPACE', 'Daterange\v1\Resource');

// Define REST messages constants.
define('MSG_FATAL_EXCEPTION', 'Application service not available');
