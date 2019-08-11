<?

namespace Daterange\v1\Model;

use Daterange\Exception\RestException as RestException;
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

    // Map the request payload with the Daterange fields.
    if (!empty($payload)) {
      foreach ($payload as $field => $val) {
        if (property_exists(__CLASS__, $field)) {
          $this->$field = $val;
        }
      }
    }

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

  /**
   * Returns all Daterange properties as an array.
   *
   * @return array
   */
  public function getProperties(): array {
    return get_object_vars($this);
  }

  /**
   * Verifies all required fields are not null or empty.
   */
  public function verifyRequiredFields(): void {
    try {
      foreach ($this->getRequiredProperties() as $field) {
        if (empty($this->$field)) {
          $error = [
            'msg' => 'The field "' . $field . '" is required.',
            'class' => __CLASS__,
            'func' => __METHOD__,
          ];
          throw new RestException($error);
        }
      }
    }
    catch (RestException $exception) {
      throw $exception;
    }
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
   * Returns a manually generated array of the sorting properties for Daterange.
   * The structure it returns is similar to 'get_object_vars'.
   *
   * @return array
   */
  public function getSortingProperties(): array {
    return $this->getFilteringProperties();
  }

  /**
   * Returns a manually generated array of the filtering properties for
   * Daterange. The structure it returns is similar to 'get_object_vars'.
   *
   * @return array
   */
  public function getFilteringProperties(): array {
    return [
      'date_start' => $this->date_start,
      'date_end' => $this->date_end,
      'price' => $this->price,
    ];
  }

  /**
   * Updates current Daterange instance based on another Daterange instance.
   *
   * @param $new_person Daterange
   */
  public function update(Daterange $new_person): void {
    foreach ($this->getUpdateProperties() as $property) {
      if ($new_person->$property) {
        $this->$property = $new_person->$property;
      }
    }
  }

  /**
   * Returns a manually generated array of the updating properties for
   * Daterange.
   *
   * @return array
   */
  public function getUpdateProperties(): array {
    return $this->getRequiredProperties();
  }

  /**
   * Function called when encoded with json_encode.
   *
   * @return array|mixed
   */
  public function jsonSerialize() {
    return array_filter(get_object_vars($this));
  }

}
