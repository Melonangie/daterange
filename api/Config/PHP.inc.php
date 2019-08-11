<?php

// Set PHP settings
$functions = 'readfile,passthru,exec,shell_exec,popen,telnet,friends,phpinfo,exec,system,proc_open,curl_exec,curl_multi_exec,parse_ini_file,show_source,allow_url_fopen,allow_url_include';
ini_set('disable_functions', $functions);
ini_set('variables_order', 'GPCSE');

// Turn off all error reporting
error_reporting(E_ALL);
/*  error_reporting(0);
  ini_set('display_errors', 0);
  ini_set('display_startup_errors', 0);
  ini_set('log_errors', 1);*/

// Set encoding.
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_regex_encoding("UTF-8");
mb_http_output('UTF-8');
ini_set('filter.default', 'full_special_chars');
ini_set('filter.default_flags', 0);

// Set locale to US Eng.
setlocale(LC_ALL, 'en_US.UTF-8');

// Set time zone.
date_default_timezone_set('America/Tijuana');
