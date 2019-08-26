<?

namespace Daterange\v1\Model;

use Daterange\v1\Exception\RestException as RestException;
use DateTime;
use JsonSerializable;

/**
 * Class Daterange
 *
 * @package Daterange\model
 */
class Daterange implements JsonSerializable {

  /**
   * Record id.
   *
   * @var $id int
   */
  protected $id;

  /**
   * Last time record was modified.
   *
   * @var $modified string
   */
  protected $modified;

  /**
   * Record start date.
   *
   * @var $date_start DateTime
   */
  protected $date_start;

  /**
   * Record end date.
   *
   * @var $date_end DateTime
   */
  protected $date_end;

  /**
   * Record price.
   *
   * @var $price float
   */
  protected $price;

  /**
   * Daterange constructor.
   *
   * @param $payload
   */
  public function __construct($payload = NULL) {

    if (!empty($payload)) {

      // Sanitize payload.
      $payload = $this->sanitizePayload($payload);

      // Map the request payload with the Daterange fields.
      foreach ($payload as $field => $val) {
        if (property_exists(__CLASS__, $field)) {
          $this->$field = $val;
        }
      }

      // Verifies all required fields are not null or empty.
      $this->verifyRequiredFields();

      // Verify dates.
      $this->verifyDates();

    }

    // Sets the values from fetch object.
    is_string($this->date_start) ? $this->setDateStartFromString($this->date_start) : NULL;
    is_string($this->date_end) ? $this->setDateEndFromString($this->date_end) : NULL;
    is_string($this->price) ? $this->setPriceFromString($this->price) : NULL;

  }

  /**
   * Daterange clone.
   */
  public function __clone() {
    // Unset id.
    $this->id = null;
  }

  /**
   * Sanitizes the payload.
   *
   * @param $payload
   *
   * @return array
   */
  protected function sanitizePayload($payload): array {

    $filtered = [];

    // Sanitize the array.
    $args = [
      'id' => [
        'filter' => FILTER_SANITIZE_NUMBER_INT,
        'validate' => FILTER_VALIDATE_INT
      ],
      'modified' => [
        'filter' => FILTER_SANITIZE_STRING,
        'validate' => FILTER_FLAG_STRIP_HIGH
      ],
      'date_start' => [
        'filter' => FILTER_SANITIZE_STRING,
        'format' => 'Y-m-d'
      ],
      'date_end' => [
        'filter' => FILTER_SANITIZE_STRING,
        'format' => 'Y-m-d'
      ],
      'price' => [
        'filter' => FILTER_SANITIZE_NUMBER_FLOAT,
        'validate' => FILTER_VALIDATE_FLOAT
      ]
    ];

    try {

      // walk the array.
      foreach ($payload as $field => $field_value) {
        // Sanitize
        $field = filter_var(strtolower(trim($field)), FILTER_SANITIZE_STRING);
        $field_value = filter_var(trim($field_value), $args[$field]['filter']);
        if (property_exists(__CLASS__, $field)) {
          // Validate
          if ($field === DATE_STARTS || $field === DATE_ENDS) {
            $filtered[$field] = $this->getValidDate($field_value, $args[$field]['format']);
          }
          else {
            $filtered[$field] = filter_var($field_value, $args[$field]['validate']);
          }
        }
      }

    }
    catch (\Exception $exception) {
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }

    unset($filters);

    return $filtered;
  }

  /**
   * Validates a date.
   *
   * @param string $date
   * @param string $format
   *
   * @return DateTime
   */
  protected function getValidDate(string $date, string $format = 'Y-m-d'): DateTime {
    try {
      $d = DateTime::createFromFormat($format, $date);
      if (!($d && $d->format($format) == $date)) {
        $error = ['msg' => 'The date "' . $date . '" is invalid.', 'class' => __CLASS__, 'func' => __METHOD__,];
        throw new RestException($error);
      }
    }
    catch (RestException $exception) {
      throw $exception;
    }
    return $d;
  }

  /**
   * Verifies all required fields are not null or empty.
   */
  protected function verifyRequiredFields(): void {
    try {
      foreach ($this->getRequiredProperties() as $field => $field_value) {
        if (empty($field_value)) {
          $error = ['msg' => 'The field "' . $field . '" is required.', 'class' => __CLASS__, 'func' => __METHOD__,];
          throw new RestException($error);
        }
      }
    }
    catch (RestException $exception) {
      throw $exception;
    }
  }

  /**
   * Verifies the start and end dates.
   */
  protected function verifyDates(): void {
    try {
      if ($this->date_start > $this->date_end) {
        $error = ['msg' => 'The end date must be grater or equal to the start date. Start date: ' . $this->date_start->format(DATE_FORMAT) . '. End date: ' . $this->date_end->format(DATE_FORMAT) , 'class' => __CLASS__, 'func' => __METHOD__,];
        throw new RestException($error);
      }
    }
    catch (RestException | \Exception $exception) {
      throw $exception;
    }
  }

  /**
   * @param string $date_start
   */
  protected function setDateStartFromString(string $date_start): void {
    try {
      $this->date_start = new DateTime($date_start);
      $this->date_start->settime(0,0);
    }
    catch (\Exception $exception) {
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }
  }


  /**
   * @param string $date_end
   */
  protected function setDateEndFromString(string $date_end): void {
    try {
      $this->date_end = new DateTime($date_end);
      $this->date_end->settime(0,0);
    }
    catch (\Exception $exception) {
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Daterange service error.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }
  }

  /**
   * @param string $price
   */
  protected function setPriceFromString(string $price): void {
    $this->price = (float) $price;
  }

  /**
   * Returns all Daterange properties as an array.
   *
   * @return array
   */
  public function getProperties(): array {
    return get_object_vars($this);
  }

  /**
   * Required fields to create a Daterange.
   *
   * @return array
   */
  public function getRequiredProperties(): array {
    return ['date_start', 'date_end', 'price'];
  }

  /**
   * Function called when encoded with json_encode.
   *
   * @return array|mixed
   */
  public function jsonSerialize() {
    $this->date_start = $this->date_start->format(DATE_FORMAT);
    $this->date_end = $this->date_end->format(DATE_FORMAT);
    return array_filter(get_object_vars($this));
  }

  public function __toString() {
    return $this->date_start->format(DATE_FORMAT);
  }

  /**
   * @return int
   */
  public function getId(): int {
    return $this->id;
  }

  /**
   * @param int $id
   */
  public function setId(int $id): void {
    $this->id = $id;
  }

  /**
   * @return string
   */
  public function getModified(): string {
    return $this->modified;
  }

  /**
   * @param string $modified
   */
  public function setModified(string $modified): void {
    $this->modified = $modified;
  }

  /**
   * @return DateTime
   */
  public function getDateStart(): DateTime {
    return $this->date_start->settime(0,0);
  }

  /**
   * @param DateTime $date_start
   */
  public function setDateStart(DateTime $date_start): void {
    $date_start->settime(0,0);
    $this->date_start = $date_start;
  }

  /**
   * @return DateTime
   */
  public function getDateEnd(): DateTime {
    return $this->date_end->settime(0,0);
  }

  /**
   * @param DateTime $date_end
   */
  public function setDateEnd(DateTime $date_end): void {
    $date_end->settime(0,0);
    $this->date_end = $date_end;
  }

  /**
   * @return float
   */
  public function getPrice(): float {
    return $this->price;
  }

  /**
   * @param float $price
   */
  public function setPrice(float $price): void {
      $this->price = $price;
  }

}
