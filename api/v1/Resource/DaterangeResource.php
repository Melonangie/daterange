<?php

namespace Daterange\v1\Resource;

use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
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
   * @var
   */
  protected $request;

  /**
   * The class that Daterange Gateway.
   *
   * @var DaterangeService
   */
  protected $service;

  /**
   * PersonResource constructor.
   *
   * @param        $request
   * @param string $driver
   */
  public function __construct($request, $driver) {

    @set_exception_handler([$this, 'exception_handler']);

    // Sets the request.
    $this->request = $request;

    // Sets the service.
    $this->service = new DaterangeService($driver);

  }

  /**
   * Gets the request.
   *
   * @return mixed
   */
  public function getRequest() {
    return $this->request;
  }

  /**
   * Gets the service for the request.
   *
   * @return \Daterange\v1\Service\DaterangeService
   */
  public function getService(): DaterangeService {
    return $this->service;
  }

  /**
   * Exception and error handling.
   *
   * @param $exception
   */
  public function exception_handler($exception) {
    // Set headers.
    header('Content-Type: ' . CONTENT_TYPE_CHARSET_JSON);
    http_response_code($exception->getCode());

    // Set message.
    echo $exception->getMessage();
  }

  /**
   * Maps the GET request method with the function readPersons from
   * DaterangeService.
   *
   * @return string
   */
  public function get() {

    if ($this->request->getParameter() === NULL) {

      // Gets all persons.
      if ($this->request->getExtraParameters() === NULL) {
        return $this->service->readDateranges();
      }

      // Gets the query builder.
      else {
        return $this->service->readDaterangeQuery($this->request->getExtraParameters());
      }

    }

    // Verifies record exist.
    if (!$this->service->verifyExist('id', $this->request->getParameter(), PDO::PARAM_INT, 'PERSONS_TABLE')) {
      $error = [
        'msg' => "A record with the request id doesn't exist. id: " . $this->request->getParameter(),
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 404);
    }

    // Gets a single person by id.
    return $this->service->readDaterange((int) $this->request->getParameter());
  }

  /**
   * Maps the POST request method with the function createPerson from
   * DaterangeService.
   *
   * @return string
   */
  public function post() {

    // Creates a new Daterange instance.
    try {
      $person = new Daterange($this->request->getPayload());
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

    // Checks the required fields are not null nor empty.
    $person->verifyRequiredFields();

    return $this->service->createDaterange($person);
  }

  /**
   * Maps the PUT request method with the function updatePerson from
   * DaterangeService.
   *
   * @return string
   */
  public function put() {

    // Gets the Daterange id from the payload.
    $id = $this->request->get_payload_id();
    if ($id === NULL) {
      $error = [
        'msg' => "PUT method requires an id in the payload. ",
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 400);
    }

    // Verifies the person exist.
    if (!$this->service->verifyExist('id', (int) $id, PDO::PARAM_INT, 'PERSONS_TABLE')) {
      $error = [
        'msg' => "A record with the request id doesn't exist. id: " . $this->request->getParameter(),
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 404);
    }

    // Gets the new Daterange data.
    $daterange = new Daterange($this->request->getPayload());

    if ($daterange === NULL) {
      $error = [
        'msg' => "There was an error loading your object. ",
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 400);
    }

    // Checks the required fields are not null nor empty.
    $daterange->verifyRequiredFields();

    // Gets the old data.
    $old_person = $this->service->readDaterange($id);

    // Updates the old Daterange, in case we're missing a property will not get deleted.
    $old_person->update($daterange);

    return $this->service->updateDaterange($old_person);
  }

  /**
   * Maps the DELETE request method with the function deletePerson from
   * DaterangeService.
   *
   * @return string
   */
  public function delete() {

    // Verifies record exist.
    if (!$this->service->verifyExist('id', (int) $this->request->getParameter(), PDO::PARAM_INT, 'PERSONS_TABLE')) {
      $error = [
        'msg' => "A record with the request id doesn't exist. id: " . $this->request->getParameter(),
        'class' => __CLASS__,
        'func' => __METHOD__,
      ];
      throw new RestException($error, 404);
    }

    return $this->service->deleteDaterange((int) $this->request->getParameter());
  }

}
