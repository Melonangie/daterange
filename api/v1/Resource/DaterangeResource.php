<?php

namespace Daterange\v1\Resource;

use Daterange\v1\Database\DBConnection as DBConnection;
use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
use Daterange\v1\Request\Request as Request;
use Daterange\v1\Service\DaterangeMergingService as DaterangeMergingService;
use Daterange\v1\Service\DaterangeQueryService as DaterangeQueryService;

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
   * @var DaterangeQueryService
   */
  protected $service;

  /**
   * Daterange object..
   *
   * @var Daterange
   */
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
    $this->service = new DaterangeQueryService($this->db);

  }

  /**
   * Select the Database driver.
   *
   * @return DBConnection
   */
  private function setDriver(): DBConnection {
    // Here goes the logic to select a store engine.
    return new DBConnection(MYSQL);
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
   * @return DaterangeQueryService
   */
  public function getService(): DaterangeQueryService {
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
   * Maps the GET request method to a function in DaterangeQueryService.
   *
   * @return array|string
   */
  public function get() {

    if ($this->request->getParameter() === NULL) {

      // Gets all records.
      if ($this->request->getExtraParameters() === NULL) {
        return $this->service->readDaterangeAll();
      }

      // Gets a query builder for all records.
      return $this->service->readDaterangeQuery($this->request->getExtraParameters());

    }

    // Gets a single record by start date.
    return $this->service->readDaterange($this->request->getParameter());

  }

  /**
   * Maps the POST request with the function createDaterange from
   * DaterangeQueryService.
   *
   * @return array
   * @throws \Exception
   */
  public function post(): array {

    // Gets a new instance.
    $merge = new DaterangeMergingService($this->request->getPayload(), $this->service);

    // Gets the neighbors data.
    if (!$merge->getNeighbors()) {
      return $this->service->createDaterange($merge->getDaterange());
    }
    var_dump($merge->getNeighbors());
    // Recreate the records.
    return $this->service->updateDateranges($merge->getOldSegmentsValues(), count($merge->getOldSegments()), $merge->getNeighborsValues(), count($merge->getNeighbors()));
  }

  /**
   * Maps the PUT method with the function updateDaterange from
   * DaterangeQueryService.
   *
   * @return array
   * @throws \Exception
   */
  public function put(): array {

    // Gets a new instance.
    $merge = new DaterangeMergingService($this->request->getPayload(), $this->service);

    // Gets the neighbors data.
    if (!$merge->getNeighbors()) {
      return $this->service->updateDaterange($merge->getDaterange());
    }

    // Recreate the records.
    return $this->service->updateDateranges($merge->getOldSegmentsValues(), count($merge->getOldSegments()), $merge->getUpsertSegmentsValues(), count($merge->getUpsertSegments()));
  }

  /**
   * Maps the DELETE method with the function deleteDaterange from
   * DaterangeQueryService.
   *
   * @return array
   */
  public function delete(): array {

    // Checks the parameter.
    if ($this->request->getParameter() === NULL) {
      $error = ['msg' => 'The unique identifier must be a valid date.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error, 400);
    }

    // Delete all records.
    if (strcasecmp($this->request->getParameter(), ALL) === 0) {
      return $this->service->deleteDaterangeAll();
    }

    // Deletes a single record.
    return $this->service->deleteDaterange($this->request->getParameter());

  }

}
