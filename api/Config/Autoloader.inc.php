<?php

require_once 'PHP.inc.php';
require_once 'Constants.inc.php';

/**
 * Simple autoloader, so we don't need Composer just for this.
 */
spl_autoload_register(function ($class) {

  // Project-specific namespace.
  $prefix = 'Daterange\\';

  // Base directory for the namespace.
  $base_dir = __DIR__ . '/../';

  // Check the class uses the namespace.
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) {
    return;
  }

  // Get the class name.
  $relative_class = substr($class, $len);

  // Replace the namespace with the base directory.
  // Replace namespace separators with directory separators in the class name.
  // Append .php to classes names.
  $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

  // If the file exists require it.
  if (file_exists($file)) {
    require $file;
  }

  //    var_dump($class);
  //    var_dump($file);
});
