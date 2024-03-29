<?php

namespace Daterange\v1\Database;

use Daterange\v1\Exception\RestException;
use Exception;
use PDO;
use PDOException;

// Get DB Configurations.
require_once __DIR__ . '../../../Config/DBConfigurations.inc.php';

/**
 * Class that holds the data base connection.
 *
 * @package Daterange\Database
 */
class DBConnection {

  /**
   * PDO object.
   *
   * @var PDO
   */
  private $pdo;

  /**
   * Databases schema.
   *
   * @var array
   */
  private $schema;

  /**
   * Databases name.
   *
   * @var string
   */
  private $driver;

  /**
   * PDO constructor.
   *
   * @param string $driver
   */
  public function __construct(string $driver) {

    @set_exception_handler([$this, 'exception_handler']);

    // Gets the database configuration variable.
    global $db_configs;

    // Verifies the driver exists in the configuration.
    $this->checkConnection($driver, $db_configs);

    // Creates the pdo object.
    $this->createPDO($db_configs);

    // Gets the database schema from the configuration variable.
    $this->setTableSchema($db_configs);

    unset($db_configs);

  }

  /**
   * Set the DB driver.
   *
   * @param string $driver
   * @param array  $db_configs
   */
  private function checkConnection(string $driver, array $db_configs): void {
    if (array_key_exists($driver, $db_configs)) {
      $this->driver = $driver;
    }
    else {
      $error = [
        'msg' => "Bad DB driver: " . $driver,
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error);
    }
  }

  /**
   * Creates the PDO object.
   *
   * @param array $db_configs
   */
  private function createPDO(array $db_configs): void {
    try {
      $this->pdo = new PDO(
        $db_configs[$this->driver][DSN],
        $db_configs[$this->driver][DB_USERNAME],
        $db_configs[$this->driver][DB_PASSWORD],
        $db_configs[PDO]
      );

    }
      // Log exception and throw error.
    catch (PDOException | Exception $exception) {
      $error = [
        'err_msg' => $exception->getMessage(),
        'err_code' => $exception->getCode(),
        'msg' => 'PDO Connection error.',
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error);
    }

  }

  /**
   * Gets the Database PDO.
   *
   * @return \PDO
   */
  public function pdo(): PDO {
    return $this->pdo;
  }

  /**
   * Gets the DB schema.
   *
   * @param string $element
   *
   * @return string
   */
  public function get(string $element): string {
    return $this->schema[$element];
  }

  /**
   * Gets the Database PDO.
   *
   * @return \PDO
   */
  public function getPdo(): PDO {
    return $this->pdo;
  }

  /**
   * Gets the DB schema.
   *
   * @return array
   */
  public function getSchema(): array {
    return $this->schema;
  }

  /**
   * Gets the database schema.
   *
   * @param array $db_configs
   */
  private function setTableSchema(array $db_configs): void {
    $this->schema = $db_configs[$this->driver][SCHEMA];
  }

  /**
   * Gets the DB driver.
   *
   * @return string
   */
  public function getDriver(): string {
    return $this->driver;
  }

  /**
   * REST Exception
   *
   * @param $exception
   */
  public function exception_handler($exception): void {
    // Set headers.
    header('Content-Type: ' . CONTENT_TYPE_CHARSET_JSON);
    http_response_code($exception->getCode());

    // Set message.
    echo $exception->getMessage();
  }

}
