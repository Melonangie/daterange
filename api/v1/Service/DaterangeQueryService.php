<?php

namespace Daterange\v1\Service;

use Daterange\v1\Database\DBConnection as DBConnection;
use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
use Daterange\v1\Model\DaterangeQueryBuilder as DaterangeQueryBuilder;
use Exception;
use PDO;
use PDOException;

/**
 * Class DaterangeService
 *
 * @package Daterange\v1\Service
 */
class DaterangeQueryService {

  /**
   * The Database schema.
   *
   * @var DBConnection
   */
  private $db;

  /**
   * DaterangeService constructor.
   *
   * @param \Daterange\v1\Database\DBConnection $db
   */
  public function __construct(DBConnection $db) {

    @set_exception_handler([$this, 'exception_handler']);

    // Set PDO.
    $this->db = $db;

  }

  /**
   * Exception.
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

  /**
   * Saves a record to the Database.
   *
   * @param Daterange $daterange
   *
   * @return array
   */
  public function createDaterange(Daterange $daterange): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Insert the record into the main DB table.
      $query = $this->db->pdo()->prepare(sprintf('INSERT INTO `%s` (%s, %s, %s) VALUES (:date_starts, :date_ends, :price)', $this->db->get('TABLE'), DATE_STARTS, DATE_ENDS, PRICE));
      $query->bindValue(':date_starts', $daterange->getDateStart()->format(DATE_FORMAT), PDO::PARAM_STR);
      $query->bindValue(':date_ends', $daterange->getDateEnd()->format(DATE_FORMAT),PDO::PARAM_STR);
      $query->bindValue(':price', $daterange->getPrice(), PDO::PARAM_STR);
      $query->execute();

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    return ['Code:' => '200', 'Message:' => 'Successfully created record.', 'Error:' => ''];
  }

  /**
   * Gets a specific record.
   *
   * @param string $date_start
   *
   * @return Daterange
   */
  public function readDaterange(string $date_start): Daterange {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets a specific record from the DB.
      $query = $this->db->pdo()->prepare(sprintf('SELECT * FROM %s WHERE `%s`=:date_start', $this->db->get('VIEW_ALL'), DATE_STARTS));
      $query->bindValue(':date_start', $date_start, PDO::PARAM_STR);
      $query->execute();
      if ($query->rowCount() <= 0) {
        $error = ['msg' => "A record with the request start date doesn't exist. Start date: " . $date_start, 'class' => __CLASS__, 'func' => __METHOD__,];
        throw new RestException($error, 404);
      }
      $date = $query->fetchObject(DATERANGE_CLASS);

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return object.
    return $date;
  }

  /**
   * Gets all records.
   *
   * @return array
   */
  public function readDaterangeAll(): array {

    $dates = [];

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets all records from the DB.
      $query = $this->db->pdo()->query(sprintf('SELECT * FROM %s', $this->db->get('VIEW_ALL')));
      while ($date = $query->fetchObject(DATERANGE_CLASS)) {
        $dates[] = $date;
      }
      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return array of objects.
    return $dates;
  }

  /**
   * Gets a record based on a query string.
   *
   * @param array $extraParameters
   *
   * @return array
   */
  public function readDaterangeQuery(array $extraParameters): array {

    $dates = [];

    // Get the dynamic query.
    $dynamic_query = new DaterangeQueryBuilder($this->db->get('VIEW_ALL'), $extraParameters);

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Gets all records from the DB.
      $query = $this->db->pdo()->prepare($dynamic_query->getQueryStatement());
      $query->execute($dynamic_query->getParamStatement());
      while ($date = $query->fetchObject(DATERANGE_CLASS)) {
        $dates[] = $date;
      }

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'PDO Connection error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }
    // Return response.
    return $dates;
  }

  /**
   * Recreate Date ranges records.
   *
   * @param array $values
   *
   * @param int   $count
   *
   * @return array
   */
  public function recreateDateranges(array $values, int $count): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Recreates the records.
      $place_holders = str_repeat('(?,?,?),', $count - 1) . '(?,?,?)';
      $sql ='INSERT INTO `%s` (`%s`, `%s`, `%s`) VALUES %s ON DUPLICATE KEY UPDATE `%s`=VALUES(`%s`), `%s`=VALUES(`%s`), `%s`=VALUES(`%s`)';
      $query = $this->db->pdo()->prepare(sprintf($sql, $this->db->get('TABLE'), DATE_STARTS, DATE_ENDS, PRICE, $place_holders, DATE_STARTS, DATE_STARTS, DATE_ENDS, DATE_ENDS, PRICE, PRICE));
      $query->execute($values);

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response.
    return ['Code:' => '200', 'Message:' => 'Successfully created new record.', 'Error:' => ''];
  }

  /**
   * Updates a record.
   *
   * @param Daterange $daterange
   *
   * @return array
   */
  public function updateDaterange(Daterange $daterange): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Update the record in the main DB table.
      $query = $this->db->pdo()->prepare(sprintf('UPDATE `%s` SET `%s`=:modified, `%s`=:date_end, `%s`=:price WHERE `%s`=:date_start', $this->db->get('TABLE'), MODIFIED, DATE_ENDS, PRICE, DATE_STARTS));
      $query->bindValue(':modified', date('Y-m-d H:i:s'), PDO::PARAM_STR);
      $query->bindValue(':date_end', $daterange->getDateEnd()->format(DATE_FORMAT), PDO::PARAM_STR);
      $query->bindValue(':price', $daterange->getPrice(), PDO::PARAM_STR);
      $query->bindValue(':date_start', $daterange->getDateStart()->format(DATE_FORMAT), PDO::PARAM_STR);
      $query->execute();
      if ($query->rowCount() <= 0) {
        $error = ['msg' => "A record with the request start date doesn't exist. Start date: " . $daterange->getDateStart()->format(DATE_FORMAT), 'class' => __CLASS__, 'func' => __METHOD__,];
        throw new RestException($error, 404);
      }

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'PDO Connection error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response.
    return ['Code:' => '200', 'Message:' => 'Successfully updated record. Start date: ' . $daterange->getDateStart()->format(DATE_FORMAT), 'Error:' => ''];
  }

  /**
   * Deletes a Daterange from the Database, based on a id.
   *
   * @param string $date_start
   *
   * @return array
   */
  public function deleteDaterange(string $date_start): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Deletes record.
      $query = $this->db->pdo()->prepare(sprintf('DELETE FROM `%s` WHERE `%s`=:date_start', $this->db->get('TABLE'), DATE_STARTS));
      $query->bindValue(':date_start', $date_start, PDO::PARAM_STR);
      $query->execute();

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response.
    return ['Code:' => '200', 'Message:' => 'Successfully deleted record. Date Start: ' . $date_start, 'Error:' => ''];
  }

  /**
   * Deletes a Daterange Emails from the Database, based on a id.
   *
   * @return array
   */
  public function deleteDaterangeAll(): array {
    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Truncates a table..
      $this->db->pdo()->exec(sprintf('TRUNCATE TABLE `%s`', $this->db->get('TABLE')));

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException | Exception $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    // Return response
    return ['Code:' => '200', 'Message:' => 'Successfully truncated database table.', 'Error:' => ''];
  }

  /**
   * Gets the information for neighbors.
   *
   * @param string $date_start
   * @param string $date_end
   *
   * @return array
   */
  public function getNextPrevDates(string $date_start, string $date_end): array {

    $dates = [];

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      $query = $this->db->pdo()->prepare(sprintf('SELECT s.* FROM (select @pstart:=? p1, @pend:=? p2) parm, %s s', $this->db->get('VIEW')));
      $query->bindParam(1, $date_start, PDO::PARAM_STR);
      $query->bindParam(2, $date_end, PDO::PARAM_STR);
      $query->execute();
      while ($date = $query->fetchObject(DATERANGE_CLASS)) {
        $dates[] = $date;
      }

      // Commits transaction.
      $this->db->pdo()->commit();
    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    return $dates;
  }

  /**
   * Checks if a record with a value exist.
   *
   * @param string $date_start
   *
   * @return bool
   */
  public function verifyExist(string $date_start): bool {

    $exist = FALSE;

    try {
      // Begin transaction.
      $this->db->pdo()->beginTransaction();

      // Checks record exist.
      //       $query = $this->db->pdo()->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->db->get('VIEW')));
      //      $query->bindParam(1, $date_start, PDO::PARAM_STR);
      //      $query->execute();
      //$query = $this->db->pdo()->prepare(sprintf('SELECT 1 FROM (select @pid:=? p) parm, %s s', $this->db->get('VIEW')));
      $query = $this->db->pdo()->query(sprintf('SELECT 1 FROM %s WHERE ', $this->db->get('VIEW_ALL')));
      $query->bindParam(1, $date_start, PDO::PARAM_STR);
      $query->execute();
      if ($query->fetchColumn()) {
        $exist = TRUE;
      }

      // Commits transaction.
      $this->db->pdo()->commit();

    }
    catch (PDOException $exception) {
      $this->db->pdo()->rollBack();
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Person service error.', 'class' => __CLASS__, 'func' => __METHOD__];
      throw new RestException($error);
    }

    return $exist;
  }

  /**
   * Database connection object.
   *
   * @return \Daterange\v1\Database\DBConnection
   */
  public function getDb(): DBConnection {
    return $this->db;
  }

}
