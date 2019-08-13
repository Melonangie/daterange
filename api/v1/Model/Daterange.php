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
   * @var $date_start string
   */
  protected $date_start;

  /**
   * Record end date.
   *
   * @var $date_end string
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
      $this->sanitizePayload($payload);

      // Verifies all required fields are not null or empty.
      $this->verifyRequiredFields($payload);

      // Verify dates.
      $this->verifyDates($payload);

      // Map the request payload with the Daterange fields.
      foreach ($payload as $field => $val) {
        if (property_exists(__CLASS__, $field)) {
          $this->$field = $val;
        }
      }

    }

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
        'format' => 'Y-m-d H:i:s'
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
          if ($field == MODIFIED || $field == DATE_STARTS || $field == DATE_ENDS) {
            $filtered[$field] = $this->validateDate($field_value, $args[$field]['format']);
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
   * @return string
   */
  protected function validateDate($date, $format = 'Y-m-d H:i:s'): string {
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
    return $date;
  }

  /**
   * Verifies all required fields are not null or empty.
   *
   * @param array $payload
   */
  protected function verifyRequiredFields(array $payload): void {
    try {
      foreach ($payload as $field => $field_value) {
        if (empty($field_value) && array_key_exists($field, $this->getRequiredProperties())) {
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
   *
   * @param array $payload
   */
  protected function verifyDates(array $payload): void {
    try {
      $start = new DateTime($payload['date_start']);
      $end = new DateTime($payload['date_end']);
      if ($start > $end) {
        $error = ['msg' => 'The end date must be grater or equal to the start date. Start date: ' . $payload['date_start'] . '. End date: ' . $payload['date_end'], 'class' => __CLASS__, 'func' => __METHOD__,];
        throw new RestException($error);
      }
    }
    catch (RestException | \Exception $exception) {
      throw $exception;
    }
  }

  /**
   * Updates current Daterange instance based on another Daterange instance.
   *
   * @param array $neighbors
   *
   * @return array
   * @throws \Exception
   */
  public function update(array $neighbors): array {

    $current_start = new DateTime($this->date_start);
    $current_end = new DateTime($this->date_end);

    $add = [];
    $delete = [];

    foreach ($neighbors as $i => $date) {
      $start = new DateTime($date->date_start);
      $end = new DateTime($date->date_end);

      // Merges
      if (abs(($this->price - $date->price) / $date->price) < 0.00001) {

        // start is before - end is after
        if ($start < $current_start && $end > $current_end) {
          $this->date_start = $date->date_start;
          $this->date_end = $date->date_end;
          $delete[] = $i;
        }

        // start is before - end is at or before
        elseif ($start < $current_start && $end <= $current_end) {
          $this->date_start = $date->date_start;
          $delete[] = $i;
        }

        // start is at or after - ends at or before
        elseif ($start >= $current_start && $end <= $current_end) {
          $delete[] = $i;
        }

        // start is at or after - ends is after
        elseif ($start >= $current_start && $end > $current_end) {
          $this->date_end = $date->date_end;
          $delete[] = $i;
        }
      }

      // Splits
      else {
        // start is before - end is after
        if ($start < $current_start && $end > $current_end) {
          $new = new Daterange();
          $new->date_start = $end;
          $new->date_start = $new->date_start->modify('+1 day')->format(DATE_FORMAT);
          $new->date_end = $date->date_end;
          $new->price = $date->price;
          $add[] = $new;
          $date->date_end = $current_start;
          $date->date_end->modify('-1 day')->format(DATE_FORMAT);
        }

        // start is before - end is at or before
        elseif ($start < $current_start && $end <= $current_end) {
          $date->date_end = $current_start;
          $date->date_end->modify('-1 day')->format(DATE_FORMAT);
        }

        // start is at or after - ends at or before
        elseif ($start >= $current_start && $end <= $current_end) {
          $delete[] = $i;
        }

        // start is at or after - ends is after
        elseif ($start >= $current_start && $end > $current_end) {
          $date->date_start = $current_end;
          $date->date_start->modify('+1 day')->format(DATE_FORMAT);
        }
      }
    }

    foreach ($delete as $key) {
      unset($neighbors[$key]);
    }

    $neighbors = array_merge($neighbors, $add);

    return $neighbors;
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
    return array_filter(get_object_vars($this));
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
   * @return string
   */
  public function getDateStart(): string {
    return $this->date_start;
  }

  /**
   * @param string $date_start
   */
  public function setDateStart(string $date_start): void {
    $this->date_start = $date_start;
  }

  /**
   * @return string
   */
  public function getDateEnd(): string {
    return $this->date_end;
  }

  /**
   * @param string $date_end
   */
  public function setDateEnd(string $date_end): void {
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
