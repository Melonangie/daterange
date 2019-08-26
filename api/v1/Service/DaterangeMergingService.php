<?php

namespace Daterange\v1\Service;

use DateInterval;
use Daterange\v1\Exception\RestException as RestException;
use Daterange\v1\Model\Daterange as Daterange;
use DateTime;
use DateTimeImmutable;
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
  protected $upsert_segments;

  /**
   * @var array
   */
  protected $old_segments;

  /**
   * DaterangeMergingService constructor.
   *
   * @param array $payload
   * @param \Daterange\v1\Service\DaterangeQueryService $service
   */
  public function __construct(array $payload, DaterangeQueryService $service) {

    @set_exception_handler([$this, 'exception_handler']);

    // Gets a new instance.
    $this->daterange = $this->getNewDaterange($payload);

    // Gets the neighbors.
    $this->neighbors = $service->getNextPrevDates($this->daterange->getDateStart()->format(DATE_FORMAT), $this->daterange->getDateEnd()->format(DATE_FORMAT));

    // Initialize arrays.
    $this->upsert_segments = $this->old_segments = [];

    // Merges, splits neighbors data.
    $this->updateNeighbors();

  }

  /**
   * @return \Daterange\v1\Model\Daterange
   */
  public function getDaterange(): Daterange {
    return $this->daterange;
  }

  /**
   * @return array
   */
  public function getNeighbors(): array {
    return $this->neighbors;
  }

  /**
   * @return array
   */
  public function getUpsertSegments(): array {
    return $this->upsert_segments;
  }

  /**
   * @return array
   */
  public function getOldSegments(): array {
    return $this->old_segments;
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
   * Returns a flatten array of the upsert_segments array.
   *
   * @return array
   */
  public function getNeighborsValues(): array {
    $values = [];
    $this->neighbors = array_filter($this->neighbors);
    if ($this->neighbors) {
      foreach ($this->neighbors as $neighbor) {
        $values[] = $neighbor->getDateStart()->format(DATE_FORMAT);
        $values[] = $neighbor->getDateEnd()->format(DATE_FORMAT);
        $values[] = $neighbor->getPrice();
      }
    }
    return $values;
  }

  /**
   * Returns a flatten array of the old_segments array.
   *
   * @return array
   */
  public function getOldSegmentsValues(): array {
    $values = [];
    $this->old_segments = array_filter($this->old_segments);
    if ($this->old_segments) {
      foreach ($this->old_segments as $record) {
        $values[] = $record->getDateStart()->format(DATE_FORMAT);
        $values[] = $record->getDateEnd()->format(DATE_FORMAT);
        $values[] = $record->getPrice();
      }
    }
    return $values;
  }

  /**
   * @param array $payload
   *
   * @return \Daterange\v1\Model\Daterange
   */
  protected function getNewDaterange(array $payload): Daterange {
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
   *
   */
  protected function updateNeighbors() {

    // Unique values.
    //$this->neighbors = array_unique($this->neighbors, SORT_REGULAR);

    // Sorts the array.
    usort($this->neighbors, [$this, 'compare']);

    foreach ($this->neighbors as $i => $neighbor) {
//      if($i == 1) {
//        var_dump($this->neighbors);
//      }
      // Compares equal floats (abs(($a-$b)/$b) < 0.00001)
      if (abs(($this->daterange->getPrice() - $neighbor->getPrice()) / $neighbor->getPrice()) < 0.00001) {

        // Merges.
        switch (TRUE) {

          case $this->before($neighbor):
          case $this->after($neighbor):
            // daterange:             -----+-------+-----
            // neighbor before:            |       |  [---]
            // neighbor after:     [---]   |       |
            // Neighbors.
            $this->neighbors[] = clone $this->daterange;
            // Deletes, adds.
            $this->upsert_segments[] = clone $this->neighbors[$i];
            break;

          // daterange:             -----+-------+-----
          // neighbor meets:             |       |[---]
          // neighbor overlaps:          |    [--+--]
          // neighbor finished-by:       |  [----]
          case $this->meets($neighbor):
          case $this->overlaps($neighbor):
          case $this->finishedBy($neighbor):
            // Neighbors.
            $this->neighbors[$i]->setDateStart(clone $this->daterange->getDateStart());
            // Deletes, adds.
            $this->old_segments[] = clone $neighbor;
            $this->upsert_segments[] = clone $this->neighbors[$i];
//          if($i == 0) {
//            var_dump("1");
//            var_dump($this->upsert_segments);die();
//          }
            break;

          // daterange:             -----+-------+-----
          // neighbor contains:          | [---] |
          case $this->contains($neighbor):
            // Neighbors.
            $this->neighbors[$i] = clone $this->daterange;
            // Deletes, adds.
            $this->old_segments[] = clone $neighbor;
            $this->upsert_segments[] = clone $this->neighbors[$i];
//            if($i == 0) {
//              var_dump("2");
//              var_dump($this->upsert_segments);die();
//            }
            break;

          // daterange:             -----+-------+-----
          // neighbor starts:            [-------+--]
          // neighbor equals:            [-------]
          // neighbor during:         [--+-------+--]
          // neighbor finishes:       [--+-------]
          case $this->starts($neighbor):
          case $this->equals($neighbor):
          case $this->during($neighbor):
          case $this->finishes($neighbor):
//          if($i == 0) {
//            var_dump("3");
//            var_dump($this->upsert_segments); die();
//          }
            break;

          // daterange:             -----+-------+-----
          // neighbor startedBy:         [-----] |
          // neighbor overlappedBy:   [--+---]   |
          // neighbor metBy:        [---]|       |
          case $this->startedBy($neighbor):
          case $this->overlappedBy($neighbor):
          case $this->metBy($neighbor):
            // Neighbors.
            $this->neighbors[$i]->setDateEnd(clone $this->daterange->getDateEnd());
            // Deletes, adds.
            $this->upsert_segments[] = clone $this->neighbors[$i];
//          if($i == 0) {
//            var_dump("4");
//            var_dump($this->upsert_segments);
//          }
            break;

        }

      }
      else {

        // Splits.
        switch (TRUE) {

          case $this->before($neighbor):
          case $this->after($neighbor):
            // daterange:             -----+-------+-----
            // neighbor before:            |       |  [---]
            // neighbor after:     [---]   |       |
            // Neighbors.
            $this->neighbors[] = clone $this->daterange;
            // Deletes, adds.
            $this->upsert_segments[] = clone $this->neighbors[$i];
            break;

          // daterange:             -----+-------+-----
          // neighbor overlaps:          |    [--+--]
          // neighbor starts:            [-------+--]
          case $this->overlaps($neighbor):
          case $this->starts($neighbor):
          if($i == 1) {
            var_dump("5");
            var_dump($this->neighbors);
          }
            // Neighbors.
            //$this->neighbors[] = clone $this->daterange;
            $end = clone $this->daterange->getDateEnd();
            $this->neighbors[$i]->setDateStart($end->modify('+1 day'));
            // Deletes, adds.
            $this->old_segments[] = clone $neighbor;
            $this->addDaterangeToUpsert();
            $this->upsert_segments[] = clone $this->neighbors[$i];
            if($i == 1) {
            var_dump("5");
            var_dump($this->neighbors);
          }
            break;

          // daterange:             -----+-------+-----
          // neighbor finishedBy:        |  [----]
          // neighbor contains:          | [----]|
          // neighbor startedBy:         [-----] |
          case $this->finishedBy($neighbor):
          case $this->contains($neighbor):
          case $this->startedBy($neighbor):
            // Neighbors.
            $this->neighbors[$i] = clone $this->daterange;
            // Deletes, adds.
            $this->old_segments[] = clone $neighbor;
            $this->addDaterangeToUpsert();
          if($i == 1) {
            var_dump("6");
            var_dump($this->upsert_segments);
          }
            break;

          // daterange:             -----+-------+-----
          // neighbor equals:            [-------]
          case $this->equals($neighbor):
            if($i == 1) {
              var_dump("7");
              var_dump($this->upsert_segments);
            }
            break;

          // daterange:             -----+-------+-----
          // neighbor during:         [--+-------+--]
          case $this->during($neighbor):
            // Neighbors.
            $segment_left = clone $neighbor;
            $start = clone $this->daterange->getDateStart();
            $segment_left->setDateEnd($start->modify('-1 day'));
            $this->neighbors[] = $segment_left;
            $this->neighbors[] = clone $this->daterange;
            $end = clone $this->daterange->getDateEnd();
            $this->neighbors[$i]->setDateStart($end->modify('+1 day'));
            // Deletes, adds.
            $this->old_segments[] = clone $neighbor;
            $this->upsert_segments[] = $segment_left;
            $this->addDaterangeToUpsert();
            $this->upsert_segments[] = clone $this->neighbors[$i];
            if($i == 1) {
              var_dump("8");
              var_dump($this->neighbors);
            }
            break;

          // daterange:             -----+-------+-----
          // neighbor finishes:        [-+-------]
          // neighbor overlappedBy:  [---+----]  |
          case $this->finishes($neighbor):
          case $this->overlappedBy($neighbor):
            // Neighbors.
            $start = clone $this->daterange->getDateStart();
            $this->neighbors[$i]->setDateEnd($start->modify('-1 day'));
            $this->neighbors[] = clone $this->daterange;
            // Deletes, adds.
            $this->old_segments[] = clone $neighbor;
            $this->upsert_segments[] = clone $this->neighbors[$i];
            $this->addDaterangeToUpsert();
          if($i == 1) {
            var_dump("9");
            var_dump($this->upsert_segments);
          }
            break;

        }

      }

      if($i == 0) {
        //var_dump($this->upsert_segments);die();
      }

    }
    //$this->checkDatesRepeatConsecutive();
   // var_dump($this->neighbors);die();
  }

  /**
   * Comparator, to sort an array.
   *
   * @param $a
   * @param $b
   *
   * @return int
   */
  protected function compare($a, $b) {
    return $a->getDateStart() <=> $b->getDateStart();
  }

  /**
   * If not already added, adds the daterange to the upsert_segments array.
   */
  protected function addDaterangeToUpsert(): void {

    $included = FALSE;

    foreach ($this->upsert_segments as $segment) {
      if ($segment->getDateStart() == $this->daterange->getDateStart() &&
        $segment->getDateEnd() == $this->daterange->getDateEnd()) {
        $included = TRUE;
        break;
      }
    }

    if (!$included) {
      $this->upsert_segments[] = clone $this->daterange;
    }

  }


  /**
   * Recursive function to merge dates.
   */
  protected function checkDatesRepeatConsecutive() {

    if (count($this->upsert_segments) > 2) {

      $start = $end = NULL;

      foreach ($this->upsert_segments as $i => $segment) {

        if ($i === 0) {
          $start = clone $segment->getDateStart();
          $end = clone $segment->getDateEnd();
          continue;
        }

        if (($segment->getDateStart() >= $start) && ($segment->getDateEnd() <= $end)) {
          return $this->updateNeighbors();
        }

        $start = clone $segment->getDateStart();
        $end = clone $segment->getDateEnd();
      }
    }

  }

  /**
   * Daterange *before* the neighbor.
   *             ds     de
   * daterange:  [------]--+------+
   * neighbor:   +------+--[------]
   *                       ns     ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function before(Daterange $neighbor) {
    return
      // de < ns
      $this->daterange->getDateEnd() < $neighbor->getDateStart() &&
      // Diff is more than one day.
      ($this->daterange->getDateEnd()->diff($neighbor->getDateStart())->days !== 1);
  }

  /**
   * Daterange *meets* the neighbor.
   *             ds      de
   * daterange:  [-------]+-------+
   * neighbor:   +-------+[-------]
   *                      ns      ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function meets(Daterange $neighbor): bool {

    return
      // Diff is one day.
      ($this->daterange->getDateEnd()->diff($neighbor->getDateStart())->days === 1);
  }

  /**
   * Daterange *overlaps* the neighbor.
   *             ds          de
   * daterange:  [-----+-----]-----+
   * neighbor:   +-----[-----+-----]
   *                   ns          ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function overlaps(Daterange $neighbor): bool {

    return
      // Starts:  ds < ns
      $this->daterange->getDateStart() < $neighbor->getDateStart() &&
      // Overlap: ns < de
      $neighbor->getDateStart() < $this->daterange->getDateEnd() &&
      // Ends:    de < ne
      $this->daterange->getDateEnd() < $neighbor->getDateEnd();
  }

  /**
   * Daterange *finished-by* the neighbor.
   *             ds            de
   * daterange:  [------+------]
   * neighbor:   +------[------]
   *                    ns     ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function finishedBy(Daterange $neighbor): bool {

    return
      // Starts:  ds < ns
      $this->daterange->getDateStart() < $neighbor->getDateStart() &&
      // Overlap: ns < de
      $neighbor->getDateStart() < $this->daterange->getDateEnd() &&
      // Ends:    de = ne
      $this->daterange->getDateEnd() == $neighbor->getDateEnd();
  }

  /**
   * Daterange *contains* the neighbor.
   *             ds                de
   * daterange:  [-----+-----+-----]
   * neighbor:         [-----]
   *                   ns    ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function contains(Daterange $neighbor): bool {
    return
      // Starts:  ds < ns
      $this->daterange->getDateStart() < $neighbor->getDateStart() &&
      // Ends:    ne < de
      $neighbor->getDateEnd() < $this->daterange->getDateEnd();
  }

  /**
   * Daterange *starts* the neighbor.
   *             ds     de
   * daterange:  [------]
   * neighbor:   [------+------]
   *             ns            ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function starts(Daterange $neighbor): bool {

    return
      // Starts:  ds = ns
      $this->daterange->getDateStart() == $neighbor->getDateStart() &&
      // Ends:    de < ne
      $this->daterange->getDateEnd() < $neighbor->getDateEnd();
  }

  /**
   * Daterange *equals* the neighbor.
   *             ds       de
   * daterange:  [--------]
   * neighbor:   [--------]
   *             ns       ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function equals(Daterange $neighbor): bool {

    return
      // Starts:  ds = ns
      $this->daterange->getDateStart() == $neighbor->getDateStart() &&
      // Ends:    de = ne
      $this->daterange->getDateEnd() == $neighbor->getDateEnd();
  }

  /**
   * Daterange *during* the neighbor.
   *                   ds    de
   * daterange:  +-----[-----]-----+
   * neighbor:   [-----+-----+-----]
   *             ns                ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function during(Daterange $neighbor): bool {

    return
      // Starts:  ns < ds
      $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      // Ends:    de < ne
      $this->daterange->getDateEnd() < $neighbor->getDateEnd();
  }

  /**
   * Daterange *finishes* the neighbor.
   *                    ds      de
   * daterange:  +------[-------]
   * neighbor:   [------+-------]
   *             ns             ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function finishes(Daterange $neighbor): bool {

    return
      // Starts:  ns < ds
      $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      // Ends:    de = ne
      $this->daterange->getDateEnd() == $neighbor->getDateEnd();
  }

  /**
   * Daterange *started-by* the neighbor.
   *             ds            de
   * daterange:  [------+------]
   * neighbor:   [------]------+
   *             ns     ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function startedBy(Daterange $neighbor): bool {

    return
      // Starts:  ds = ns
      $this->daterange->getDateStart() == $neighbor->getDateStart() &&
      // Overlap: ds < ne
      $this->daterange->getDateStart() < $neighbor->getDateEnd() &&
      // Ends:    ne < de
      $neighbor->getDateEnd() < $this->daterange->getDateEnd();
  }

  /**
   * Daterange *overlapped-by* the neighbor.
   *                   ds          de
   * daterange:  +-----[-----+-----]
   * neighbor:   [-----+-----]-----+
   *             ns          ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function overlappedBy(Daterange $neighbor): bool {

    return
      // Starts:  ns < ds
      $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      // Overlap: ds < ne
      $this->daterange->getDateStart() < $neighbor->getDateEnd() &&
      // Ends:    ne < de
      $neighbor->getDateEnd() < $this->daterange->getDateEnd();
  }

  /**
   * Daterange *met-by* the neighbor.
   *                      ds      de
   * daterange:  +-------+[-------]
   * neighbor:   [-------]+-------+
   *             ns      ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function metBy(Daterange $neighbor): bool {

    return
      // Diff is one day.
      $neighbor->getDateEnd()->diff($this->daterange->getDateStart())->days === 1;
  }

  /**
   * Daterange *after* the neighbor.
   *                       ds     de
   * daterange:  +------+--[------]
   * neighbor:   [------]--+------+
   *             ns     ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function after(Daterange $neighbor) {
    return
      // ne < ds
      $neighbor->getDateEnd() < $this->daterange->getDateStart() &&
      // Diff is more than one day.
      ($neighbor->getDateEnd()->diff($this->daterange->getDateStart())->days !== 1);
  }

}
