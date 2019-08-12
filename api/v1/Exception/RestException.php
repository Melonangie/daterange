<?php

  namespace Daterange\v1\Exception;

  use RuntimeException;

  /**
   * Class RestException
   *
   * @package Daterange\v1\Exception
   */
  class RestException extends RuntimeException {

    /**
     * The exception message.
     *
     * @var false|string
     */
    protected $message;

    /**
     * The exception code.
     *
     * @var int
     */
    protected $code;

    /**
     * The file where the exception occurred.
     *
     * @var String
     */
    protected $file;

    /**
     * The line number where the exception occurred.
     *
     * @var int
     */
    protected $line;

    /**
     * The information going to the logs.
     *
     * @var array
     */
    protected $logDetails;

    /**
     * The exception message for the end user.
     *
     * @var string
     */
    protected $userMessage;

    /**
     * The description of the error.
     *
     * @var mixed
     */
    protected $error;

    /**
     * Sets the exception message.
     *
     * @param false|string $message
     */
    public function setMessage($message): void {
      $this->message = $message;
    }

    /**
     * Sets the exception code.
     *
     * @param int $code
     */
    public function setCode(int $code): void {
      $this->code = $code;
    }

    /**
     * Sets the file where the exception occurred.
     *
     * @param mixed $file
     */
    public function setFile($file): void {
      $this->file = $file;
    }

    /**
     * Sets the line number where the exception occurred.
     *
     * @param mixed $line
     */
    public function setLine($line): void {
      $this->line = $line;
    }

    /**
     * Gets the exception log details.
     *
     * @return array
     */
    public function getLogDetails(): array {
      return $this->logDetails;
    }

    /**
     * Sets the log details.
     *
     * @param array $logDetails
     */
    public function setLogDetails(array $logDetails): void {
      $this->logDetails = $logDetails;
    }

    /**
     * Gets the exception message for the end user.
     *
     * @return string
     */
    public function getUserMessage(): string {
      return $this->userMessage;
    }

    /**
     * Sets the exception message for the end user.
     *
     * @param string $userMessage
     */
    public function setUserMessage(string $userMessage): void {
      $this->userMessage = $userMessage;
    }

    /**
     * Gets the exception error message.
     *
     * @return mixed
     */
    public function getError() {
      return $this->error;
    }

    /**
     * Sets the exception error message.
     *
     * @param mixed $error
     */
    public function setError($error): void {
      $this->error = $error;
    }

    /**
     * RestException constructor.
     *
     * @param array $error
     * @param int $code
     * @param string $userMessage
     * @param null $previous
     */
    public function __construct($error = [], $code = 500, $userMessage = MSG_FATAL_EXCEPTION, $previous = NULL) {

      // Log exception/error message.
      $this->logDetails = [
        'Class' => $error['class'],
        'Function' => $error['func'],
        'File' => $this->getFile(),
        'Line' => $this->getLine(),
        'Code' => $this->getCode() ?? @$error['err_code'],
        'Message' => $this->getMessage() ?? @$error['err_msg'],
        'Trace' => $this->getTraceAsString(),
      ];
      error_log(print_r($this->logDetails, TRUE), 0);

      // Sets the exception code.
      $this->code = $code;

      // Sets the message for the end user.
      $this->userMessage = $userMessage;

      // Sets the error array.
      $this->error = $error['msg'];

      // Prepares the exception message.
      $this->message = json_encode([
        'Code' => $this->code,
        'Message' => $this->userMessage,
        'Error' => $this->error,
      ]);

      parent::__construct($this->message, $this->code, $previous);

    }

  }
