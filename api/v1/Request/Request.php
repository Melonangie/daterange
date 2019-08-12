<?php

namespace Daterange\v1\Request;

use Daterange\v1\Exception\RestException as RestException;
use DateTime;

/**
 * Class Request
 *
 * @package Daterange\v1\Request
 */
class Request {

  /**
   * The request method.
   *
   * @var string
   */
  protected $method;

  /**
   * The request route.
   *
   * @var string
   */
  protected $route;

  /**
   * The request parameter for GET /{id}.
   *
   * @var string|null
   */
  protected $parameter;

  /**
   * The request query string as an array.
   *
   * @var string|null
   */
  protected $extraParameters;

  /**
   * The request payload.
   *
   * @var array|null
   */
  protected $payload;

  /**
   * The request content type.
   *
   * @var string
   */
  protected $contentType;

  /**
   * Request constructor.
   */
  public function __construct() {

    @set_exception_handler([$this, 'exception_handler']);

    // Set the request method.
    $this->method = $this->get_request_method();

    // The content type of the request must be application/json.
    $this->contentType = $this->get_content_type();

    // Set the request route.
    $this->route = $this->get_request_route();

    // Set the request params.
    $this->parameter = $this->get_request_parameter();

    // Set the request extra params.
    $this->extraParameters = $this->get_request_extraParameters();

    // Set the un-sanitized request payload.
    $this->payload = $this->get_request_payload();

  }

  /**
   * Get the request method.
   *
   * @return string
   */
  protected function get_request_method(): string {

    // Gets the request method.
    $method = strtoupper(filter_var($_SERVER['REQUEST_METHOD'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH));

    // Checks is one of the expected value.
    if (!in_array($method, ALLOWED_METHODS, TRUE)) {
      $error = [
        'msg' => 'Request method not allowed/found: ' . $method,
        'class' => __CLASS__,
        'func' => __FUNCTION__,
      ];
      throw new RestException($error, 405);
    }

    return $method;
  }

  /**
   * Verify if the request content type headers.
   *
   * @return string
   */
  protected function get_content_type(): string {

    // Gets content type.
    $content_type = strtolower(trim(filter_var($_SERVER['CONTENT_TYPE'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)));

    // Checks is expected value.
    if (strcmp($content_type, CONTENT_TYPE_JSON) !== 0) {
      $error = [
        'msg' => 'Request content-type must be: ' . CONTENT_TYPE_JSON . '. Found: ' . $content_type,
        'class' => __CLASS__,
        'func' => __FUNCTION__,
      ];
      throw new RestException($error, 400);
    }

    return $content_type;
  }

  /**
   * Gets the request route.
   *
   * @return string
   */
  protected function get_request_route(): string {

    // Route is mandatory.
    if (!filter_has_var(INPUT_GET, ROUTE)) {
      $error = [
        'msg' => 'Request route not found.',
        'class' => __CLASS__,
        'func' => __FUNCTION__,
      ];
      throw new RestException($error, 404);
    }

    return strtolower(trim(filter_input(INPUT_GET, ROUTE, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH)));
  }

  /**
   * Gets the request parameter for GET /{date_start}.
   *
   * @return string|null
   */
  protected function get_request_parameter(): ?string {

    // Only get the parameter for GET and DELETE.
    if (strcasecmp($this->method, PUT) === 0) {
      return NULL;
    }

    // Checks if the parameter Param is set.
    // Using $_GET because filter_input is inconsistent in getting bool values.
    if (!$_GET[PARAMETER]) {
      return NULL;
    }

    // Gets the request parameter.
    $param = trim(filter_input(INPUT_GET, PARAMETER, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH));

    // Checks the parameter GET /{date_start} is a valid date.
    if (strcasecmp($this->method, GET) === 0) {
      $d = DateTime::createFromFormat('Y-m-d', $param);
      if (!($d && $d->format('Y-m-d') == $param)) {
        $error = [
          'msg' => 'The unique identifier must be a valid date. Found: ' . $param,
          'class' => __CLASS__,
          'func' => __FUNCTION__,
        ];
        throw new RestException($error, 400);
      }
    }

    // Checks the parameter POST is /all.
    if ((strcasecmp($this->method, POST) === 0) && strcasecmp($param, 'all') !== 0) {
      $error = [
        'msg' => 'The parameter: ' . $param . ' is not recognize.',
        'class' => __CLASS__,
        'func' => __FUNCTION__,
      ];
      throw new RestException($error, 400);
    }

    return $param;
  }

  /**
   * Gets the request query string.
   *
   * @return array|null
   */
  protected function get_request_extraParameters(): ?array {

    // Only available for a GET request.
    if (strcasecmp($this->method, GET) !== 0) {
      return NULL;
    }

    // Gets the extra parameters.
    // $request_params = filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
    $request_params = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);

    // Gets the query string.
    $parse_url = parse_url($request_params, PHP_URL_QUERY);
    if (!$parse_url) {
      return NULL;
    }

    // Parses the query string.
    parse_str($parse_url, $extraParameters);

    return $extraParameters;
  }

  /**
   * Gets the request payload as an associative array.
   *
   * @return array|null
   */
  protected function get_request_payload(): ?array {

    // Not available for GET nor DELETE.
    if ((strcasecmp($this->method, GET) === 0) || (strcasecmp($this->method, DELETE) === 0)) {
      return NULL;
    }

    $payload = [];

    // Gets the payload and verifies it from JSON request.
    if (strcasecmp($this->contentType, CONTENT_TYPE_JSON) === 0) {
      $payload = json_decode(trim(file_get_contents("php://input", TRUE)), TRUE);
      if (empty($payload)) {
        $error = [
          'msg' => 'Request contains invalid JSON.',
          'class' => __CLASS__,
          'func' => __FUNCTION__,
        ];
        throw new RestException($error, 400);
      }
    }

    // Returns un-sanitized payload.
    return $payload;
  }

  /**
   * Gets the request method.
   *
   * @return string
   */
  public function getMethod(): string {
    return $this->method;
  }

  /**
   * Gets the request route.
   *
   * @return string
   */
  public function getRoute(): string {
    return $this->route;
  }

  /**
   * Gets the request parameter for GET /{string}.
   *
   * @return string|null
   */
  public function getParameter(): ?string {
    return $this->parameter;
  }

  /**
   * Gets the request query string as an array.
   *
   * @return array|null
   */
  public function getExtraParameters(): ?array {
    return $this->extraParameters;
  }

  /**
   * Gets the request payload.
   *
   * @return array|null
   */
  public function getPayload(): ?array {
    return $this->payload;
  }

  /**
   * Gets the request content type.
   *
   * @return string
   */
  public function getContentType(): string {
    return $this->contentType;
  }

  /**
   * The exception handler.
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
