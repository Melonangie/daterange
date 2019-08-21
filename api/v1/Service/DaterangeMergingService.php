<?php


namespace Daterange\v1\Service;


use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
use DateTime;
use Exception;

/**
 * Class DaterangeMergingService
 *
 * @package Daterange\v1\Service
 */
class DaterangeMergingService {

  /**
   * @var \Daterange\v1\Model\Daterange
   */
  protected $daterange;

  /**
   * @var array
   */
  protected $neighbors;

  /**
   * @var array
   */
  protected $new_segments;

  /**
   * DaterangeMergingService constructor.
   *
   * @param array                                       $payload
   * @param \Daterange\v1\Service\DaterangeQueryService $service
   */
  public function __construct(array $payload, DaterangeQueryService $service) {

    // Gets a new instance.
    $this->daterange = $this->getNewDaterange($payload);

    // Gets the neighbors.
    $this->neighbors = $service->getNextPrevDates($this->daterange->getDateStart()->format(DATE_FORMAT), $this->daterange->getDateEnd()->format(DATE_FORMAT));

  }

  /**
   * @param array $payload
   *
   * @return \Daterange\v1\Model\Daterange
   */
  public function getNewDaterange(array $payload): Daterange {
    try {
      $daterange = new Daterange($payload);
      if ($daterange === NULL) {
        $error = ['msg' => 'There was an error loading the new object.', 'class' => __CLASS__, 'func' => __METHOD__,];
        throw new RestException($error, 400);
      }
    }
    catch (Exception $exception) {
      $error = ['err_msg' => $exception->getMessage(), 'err_code' => $exception->getCode(), 'msg' => 'Couldn\'t process payload.', 'class' => __CLASS__, 'func' => __METHOD__,];
      throw new RestException($error);
    }
    return $daterange;
  }

  /**
   * Updates current Daterange instance based on another Daterange instance.
   * https://www.ics.uci.edu/~alspaugh/cls/shr/allen.html
   */
  public function updateNeighbors(): void {

    // Sorts the array.
    usort($this->neighbors, [$this, 'compare']);

    $include_daterange = TRUE;

    foreach ($this->neighbors as $key => $neighbor) {

      // Merges.
      // Compares equal floats (abs(($a-$b)/$b) < 0.00001)
      if (abs(($this->daterange->getPrice() - $neighbor->getPrice()) / $neighbor->getPrice()) < 0.00001) {

        // Neighbor *contains* the new daterange.
        //     $daterange
        // -----+-----+-----
        //   a-----------b      $neighbor  contains
        if ($this->contains($neighbor)) {
          $include_daterange = FALSE;
          continue;
        }

        // Neighbor *before or meets or overlaps or finished-by* the new daterange.
        //     $daterange
        // -----+-----+-----
        //  a--b|     |         $neighbor before
        //  a---b     |         $neighbor meets
        //  a-----b   |         $neighbor overlaps
        //  a---------b         $neighbor finished-by
        elseif ($this->before($neighbor) || $this->meetsOverlapsFinishes($neighbor)) {
          $this->neighbors[$key]->setDateEnd($this->daterange->getDateEnd());
          $this->new_segments[] = $this->neighbors[$key];
          $include_daterange = FALSE;
        }

        // Neighbor *during or finishes or starts or equals* the new daterange.
        //     $daterange
        // -----+-----+-----
        //      | a-b |        $neighbor  during
        //      | a---b        $neighbor  finishes
        //      a---b |        $neighbor  starts
        //      a-----b        $neighbor  equals
        elseif ($this->duringFinishesStartsEquals($neighbor)) {
          $this->neighbors[$key]->setDateStart($this->daterange->getDateStart());
          $this->neighbors[$key]->setDateEnd($this->daterange->getDateEnd());
          $this->new_segments[] = $this->neighbors[$key];
          $include_daterange = FALSE;
        }

        // Neighbor *started-by or overlapped-by or met-by or after* the new daterange.
        //     $daterange
        // -----+-----+-----
        //      a---------b   $neighbor started-by
        //      | a-------b   $neighbor overlapped-by
        //      |     a---b   $neighbor met-by
        //      |     |a--b   $neighbor after
        elseif ($this->startedOverlappedMet($neighbor) || $this->after($neighbor)) {
          $this->neighbors[$key]->setDateStart($this->daterange->getDateStart());
          $this->new_segments[] = $this->neighbors[$key];
          $include_daterange = FALSE;
        }
      }

      // Splits
      else {

        $start = clone $this->daterange->getDateStart();
        $end = clone $this->daterange->getDateEnd();

        // Neighbor *contains* the new daterange.
        //     $daterange
        // -----+-----+-----
        //   a-----------b      $neighbor  contains
        if ($this->contains($neighbor)) {
          $new_neighbor = clone $neighbor;
          $new_neighbor->setDateEnd($start->modify('-1 day'));
          //$this->neighbors[] = $new_neighbor->setDateEnd($start->modify('-1 day'));
          $this->neighbors[$key]->setDateStart($end->modify('+1 day'));
          if ($new_neighbor->getDateStart() <= $new_neighbor->getDateEnd()) {
            $this->new_segments[] = $new_neighbor;
          }
          if ($this->neighbors[$key]->getDateStart() <= $this->neighbors[$key]->getDateEnd()) {
            $this->new_segments[] = $this->neighbors[$key];
          }
        }

        // Neighbor *meets or overlaps or finished-by* the new daterange.
        //     $daterange
        // -----+-----+-----
        //  a--b|     |         $neighbor before
        //  a---b     |         $neighbor meets
        //  a-----b   |         $neighbor overlaps
        //  a---------b         $neighbor finished-by
        elseif ($this->meetsOverlapsFinishes($neighbor)) {
          $this->neighbors[$key]->setDateEnd($start->modify('-1 day'));
          if ($this->neighbors[$key]->getDateStart() <= $this->neighbors[$key]->getDateEnd()) {
            $this->new_segments[] = $this->neighbors[$key];
          }
        }

        // Neighbor *during or finishes or starts or equals* the new daterange.
        //     $daterange
        // -----+-----+-----
        //      | a-b |        $neighbor  during
        //      | a---b        $neighbor  finishes
        //      a---b |        $neighbor  starts
        //      a-----b        $neighbor  equals
        elseif ($this->duringFinishesStartsEquals($neighbor)) {
          $this->neighbors[$key]->setDateStart($start);
          $this->neighbors[$key]->setDateEnd($end);
          $this->neighbors[$key]->setPrice($this->daterange->getPrice());
          $this->new_segments[] = $this->neighbors[$key];
          $include_daterange = FALSE;
        }

        // Neighbor *started-by or overlapped-by or met-by* the new daterange.
        //     $daterange
        // -----+-----+-----
        //      a---------b   $neighbor started-by
        //      | a-------b   $neighbor overlapped-by
        //      |     a---b   $neighbor met-by
        elseif ($this->startedOverlappedMet($neighbor)) {
          $this->neighbors[$key]->setDateStart($end->modify('+1 day'));
          if ($this->neighbors[$key]->getDateStart() <= $this->neighbors[$key]->getDateEnd()) {
            $this->new_segments[] = $this->neighbors[$key];
          }
        }

      }
    }

    if ($include_daterange) {
      $this->new_segments[] = $this->daterange;
    }
    var_dump($include_daterange);
    var_dump($this->new_segments);
//die();

  }

  public function compare($a, $b) {
    return $a->getDateStart() <=> $b->getDateStart();
  }

  /**
   * Neighbor *contains* the new daterange.
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function contains(Daterange $neighbor): bool {
    //     $daterange
    // -----+-----+-----
    //   a-----------b     $neighbor  contains
    return $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      $neighbor->getDateEnd() > $this->daterange->getDateEnd();
  }

  /**
   * Neighbor *before* the new daterange.
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function before(Daterange $neighbor): bool {
    //     $daterange
    // -----+-----+-----
    //  a--b|     |         $neighbor before
    return $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      $neighbor->getDateEnd() < $this->daterange->getDateStart();
  }

  /**
   * Neighbor *meets or overlaps or finished-by* the new daterange.
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function meetsOverlapsFinishes(Daterange $neighbor): bool {
    //     $daterange
    // -----+-----+-----
    //  a---b     |         $neighbor meets
    //  a-----b   |         $neighbor overlaps
    //  a---------b         $neighbor finished-by
    return $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      ($neighbor->getDateEnd() >= $this->daterange->getDateStart() ||
        $neighbor->getDateEnd() <= $this->daterange->getDateEnd());
  }

  /**
   * Neighbor *during or finishes or starts or equals* the new daterange.
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function duringFinishesStartsEquals(Daterange $neighbor): bool {
    //     $daterange
    // -----+-----+-----
    //      | a-b |        $neighbor  during
    //      | a---b        $neighbor  finishes
    //      a---b |        $neighbor  starts
    //      a-----b        $neighbor  equals
    return ($neighbor->getDateStart() > $this->daterange->getDateStart() &&
        $neighbor->getDateEnd() <= $this->daterange->getDateEnd()) ||
      ($neighbor->getDateStart() === $this->daterange->getDateStart() &&
      $neighbor->getDateEnd() <= $this->daterange->getDateEnd());
  }

  /**
   * Neighbor *started-by or overlapped-by or met-by* the new daterange.
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function startedOverlappedMet(Daterange $neighbor): bool {
    //     $daterange
    // -----+-----+-----
    //      a---------b   $neighbor started-by
    //      | a-------b   $neighbor overlapped-by
    //      |     a---b   $neighbor met-by
    return $neighbor->getDateEnd() > $this->daterange->getDateEnd() &&
      ($neighbor->getDateStart() >= $this->daterange->getDateStart() ||
        $neighbor->getDateStart() <= $this->daterange->getDateEnd());
  }

  /**
   * Neighbor *after* the new daterange.
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function after(Daterange $neighbor): bool {
    //     $daterange
    // -----+-----+-----
    //      |     |a--b   $neighbor after
    return $neighbor->getDateEnd() > $this->daterange->getDateEnd() &&
      ($neighbor->getDateStart() > $this->daterange->getDateEnd());
  }

  /**
   * @return \Daterange\v1\Model\Daterange
   */
  public function getDaterange(): \Daterange\v1\Model\Daterange {
    return $this->daterange;
  }

  /**
   * @param \Daterange\v1\Model\Daterange $daterange
   */
  public function setDaterange(\Daterange\v1\Model\Daterange $daterange): void {
    $this->daterange = $daterange;
  }

  /**
   * @return array
   */
  public function getNeighbors(): array {
    return $this->neighbors;
  }

  /**
   * @param array $neighbors
   */
  public function setNeighbors(array $neighbors): void {
    $this->neighbors = $neighbors;
  }

  /**
   * @return mixed
   */
  public function getNewSegments() {
    return $this->new_segments;
  }

  /**
   * @param mixed $new_segments
   */
  public function setNewSegments($new_segments): void {
    $this->new_segments = $new_segments;
  }

}
