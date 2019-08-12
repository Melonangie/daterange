<?php

namespace Daterange\v1\Resource;

use Daterange\v1\Database\DBConnection as DBConnection;
use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
use Daterange\v1\Request\Request as Request;
use Daterange\v1\Service\DaterangeService as DaterangeService;
use Exception;
use PDO;

/**
 * Class DaterangeResource
 *
 * @package Daterange\v1\Resource
 */
Class DaterangeResource {

  /**
   * The request.
   *
   * @var Request
   */
  protected $request;

  /**
   * Database.
   *
   * @var string
   */
  protected $db;

  /**
   * Daterange Gateway.
   *
   * @var DaterangeService
   */
  protected $service;


  protected $daterange;

  /**
   * PersonResource constructor.
   *
   * @param Request $request
   */
  public function __construct(Request $request) {

    @set_exception_handler([$this, 'exception_handler']);

    // Sets the request.
    $this->request = $request;

    // Sets the database driver.
    $this->db = $this->setDriver();

    // Sets the service.
    $this->service = new DaterangeService($this->db);

  }

  /**
   * Gets the request.
   *
   * @return Request
   */
  public function getRequest(): Request {
    return $this->request;
  }

  /**
   * Select the Database driver.
   *
   * @return DBConnection
   */
  private function setDriver(): DBConnection {
    // Here you put the logic to selecting the store engine.
    return new DBConnection(MYSQL);
  }

  /**
   * Gets the database driver.
   *
   * @return string
   */
  public function getDb(): string {
    return $this->db;
  }

  /**
   * Gets the service for the request.
   *
   * @return DaterangeService
   */
  public function getService(): DaterangeService {
    return $this->service;
  }

  /**
   * Exception and error handling.
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
   * Maps the GET request method to a function in DaterangeService.
   *
   * @return array|string
   */
  public function get() {

    // Gets all records.
    if ($this->request->getParameter() === NULL) {

      // Gets all records.
      if ($this->request->getExtraParameters() === NULL) {
        return $this->service->readDaterangeAll();
      }

      // Gets a query builder for all records.
      return $this->service->readDaterangeQuery($this->request->getExtraParameters());

    }

    // Gets a single record by start date.
    // Verifies record exist.
    // There are no business rules, this is the place to get them ans use them.
    if (!$this->service->verifyExist(DATE_STARTS, $this->request->getParameter())) {
      $error = [
        'msg' => "A record with the request start date doesn't exist. Start date: " . $this->request->getParameter(),
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 404);
    }
    return $this->service->readDaterange($this->request->getParameter());

  }

  /**
   * Maps the POST request with the function createDaterange from DaterangeService.
   *
   * @return array
   */
  public function post(): array {

    // Gets a new instance.
    try {
      $daterange = new Daterange($this->request->getPayload());
      if ($daterange === NULL) {
        $error = [
          'msg' => 'There was an error loading the new object.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error, 400);
      }
    }
    catch (Exception $exception) {
      $error = [
        'err_msg' => $exception->getMessage(),
        'err_code' => $exception->getCode(),
        'msg' => 'Couldn\'t process payload.',
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error);
    }

    return $this->service->createDaterange($daterange);
  }

  /**
   * Maps the PUT method with the function updateDaterange from DaterangeService.
   *
   * @return array
   */
  public function put(): array {

    // Gets a new instance.
    try {
      $daterange = new Daterange($this->request->getPayload());
      if ($daterange === NULL) {
        $error = [
          'msg' => 'There was an error loading the new object.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error, 400);
      }
    }
    catch (Exception $exception) {
      $error = [
        'err_msg' => $exception->getMessage(),
        'err_code' => $exception->getCode(),
        'msg' => 'Couldn\'t process payload.',
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error);
    }

    // Verifies the instance exist.
    if (!$this->service->verifyExist('date_start', $daterange->getDateStart())) {
      $error = [
        'msg' => "A record with the request id doesn't exist. Start date: " . $daterange->getDateStart(),
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 404);
    }

    // Gets the old data.
//    $previous = $this->service->readDaterange($id);

    // Updates the old Daterange, in case we're missing a property will not get deleted.
//    $next = $old_person->update($daterange);

    return $this->service->updateDaterange($daterange);
  }

  /**
   * Maps the DELETE method with the function deleteDaterange from DaterangeService.
   *
   * @return array
   */
  public function delete(): array {

    // Delete all records.
    if ($this->request->getParameter() !== NULL) {
      return $this->service->deleteDaterangeAll();
    }

    // Deletes all records
    // Verifies record exist.
    if (!$this->service->verifyExist('date_start', $this->request->getParameter())) {
      $error = [
        'msg' => "A record with the request start date doesn't exist. Start date: " . $this->request->getParameter(),
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 404);
    }

    return $this->service->deleteDaterange($this->request->getParameter());
  }

}
