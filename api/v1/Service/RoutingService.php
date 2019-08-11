<?php

  namespace Daterange\v1\Service;

  use Daterange\v1\Exception\RestException as RestException;

  /**
   * Class RoutingService.
   * Use to map the request data with the app classes.
   *
   * @package Daterange\v1\Service
   */
  class RoutingService {

    /**
     * The DB driver.
     *
     * @var
     */
    protected $driver;

    /**
     * The request.
     *
     * @var
     */
    protected $request;

    /**
     * A response instance.
     *
     * @var
     */
    protected $response;

    /**
     * The resource name.
     *
     * @var string
     */
    protected $resourceName;

    /**
     * The resource name including the namespace.
     *
     * @var string
     */
    protected $fullResourceName;

    /**
     * The resource instance.
     *
     * @var
     */
    protected $resource;

    /**
     * The resource method invoked in the request.
     *
     * @var string
     */
    protected $resourceMethod;

    /**
     * The resource response.
     *
     * @var
     */
    protected $resourceResponse;

    /**
     * Gets the driver.
     *
     * @return mixed
     */
    public function getDriver() {
      return $this->driver;
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
     * Gets the response.
     *
     * @return mixed
     */
    public function getResponse() {
      return $this->response;
    }

    /**
     * Gets the resource name.
     *
     * @return string
     */
    public function getResourceName(): string {
      return $this->resourceName;
    }

    /**
     * Gets the resource name namespaced.
     *
     * @return string
     */
    public function getFullResourceName(): string {
      return $this->fullResourceName;
    }

    /**
     * Gets an instance of the resource.
     *
     * @return mixed
     */
    public function getResource() {
      return $this->resource;
    }

    /**
     * Gets the request resource method.
     *
     * @return string
     */
    public function getResourceMethod(): string {
      return $this->resourceMethod;
    }

    /**
     * Gets the resource response.
     *
     * @return mixed
     */
    public function getResourceResponse() {
      return $this->resourceResponse;
    }

    /**
     * Routing Service constructor.
     *
     * @param $request
     * @param $driver
     */
    function __construct($request, $driver) {

      @set_exception_handler([$this, 'exception_handler']);

      // Set request.
      $this->request = $request;

      // Sets the DB driver;
      $this->driver = $driver;

      // Sets resource name.
      $this->resourceName = ucfirst($this->request->getRoute()) . 'Resource';

      // Sets the full resource name.
      $this->fullResourceName = 'Daterange\v1\Resource\\' . $this->resourceName;

      // Check resource exist.
      $this->verifyResourceExist();

      // Sets the resource.
      $this->resource = new $this->fullResourceName($this->request, $this->driver);

      // Set resource method.
      $this->resourceMethod = mb_strtolower($this->request->getMethod());

      // Check function exist.
      $this->verifyMethodExist();

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
     * Checks the dynamically created resource name exists.
     */
    protected function verifyResourceExist() {
      if (!class_exists($this->fullResourceName)) {
        $error = [
          'msg' => "Bad Resource name. Resource: " . $this->fullResourceName,
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error, 404);
      }
    }

    /**
     * Checks the dynamically created function exists.
     */
    protected function verifyMethodExist() {
      if (!in_array($this->resourceMethod, get_class_methods($this->fullResourceName))) {
        $error = [
          'msg' => "Method not allowed for requested route. Method: " . $this->resourceMethod,
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error, 405);
      }
    }

    /**
     * Process the request.
     */
    public function process_request() {
      $this->resourceResponse = $this->resource->{$this->resourceMethod}();
    }

    /**
     * Prints the request response.
     */
    public function encode_response() {

      // Set response headder.
      header("Content-Type: " . CONTENT_TYPE_CHARSET_JSON);

      // Set response code.
      http_response_code(200);

      // Encodes the response.
      echo json_encode($this->resourceResponse, TRUE);
    }

  }
