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
  public function getUpsertSegmentsValues(): array {
    $values = [];
    $this->upsert_segments = array_filter($this->upsert_segments);
    if ($this->upsert_segments) {
      foreach ($this->upsert_segments as $record) {
        $values[] = $record->getDateStart()->format(DATE_FORMAT);
        $values[] = $record->getDateEnd()->format(DATE_FORMAT);
        $values[] = $record->getPrice();
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
    $this->neighbors = array_unique($this->neighbors, SORT_REGULAR);

    // Sorts the array.
    usort($this->neighbors, [$this, 'compare']);

    $start = $end = NULL;

    foreach ($this->neighbors as $i => $neighbor) {

      $start = clone $this->daterange->getDateStart();
      $end = clone $this->daterange->getDateEnd();
//      if($i == 1) {
//        var_dump($this->neighbors);
//      }
      // Compares equal floats (abs(($a-$b)/$b) < 0.00001)
      if (abs(($this->daterange->getPrice() - $neighbor->getPrice()) / $neighbor->getPrice()) < 0.00001) {

        // Merges.
        switch (TRUE) {

          // daterange:             -----+-------+-----
          // neighbor meets:             |       |[---]
          // neighbor overlaps:          |    [--+--]
          // neighbor finished-by:       |  [----]
          case $this->meets($neighbor):
          case $this->overlaps($neighbor):
          case $this->finishedBy($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            // Adds.
            $this->neighbors[$i]->setDateStart($start);
            $this->upsert_segments[] = clone $this->neighbors[$i];
          if($i == 1) {
            var_dump("1");
            var_dump($this->upsert_segments);
          }
            break;

          // daterange:             -----+-------+-----
          // neighbor contains:          | [---] |
          case $this->contains($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            // Adds.
            $this->neighbors[$i] = clone $this->daterange;
            $this->upsert_segments[] = clone $this->daterange;
            if($i == 1) {
              var_dump("2");
              var_dump($this->upsert_segments);
            }
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
          if($i == 1) {
            var_dump("3");
            var_dump($this->upsert_segments);
          }
            break;

          // daterange:             -----+-------+-----
          // neighbor startedBy:         [-----] |
          // neighbor overlappedBy:   [--+---]   |
          // neighbor metBy:        [---]|       |
          case $this->startedBy($neighbor):
          case $this->overlappedBy($neighbor):
          case $this->metBy($neighbor):
            // Adds.
            $this->neighbors[$i]->setDateEnd($end);
            $this->upsert_segments[] = clone $this->neighbors[$i];
          if($i == 1) {
            var_dump("4");
            var_dump($this->upsert_segments);
          }
            break;

        }

      }
      else {

        // Splits.
        switch (TRUE) {

          // daterange:             -----+-------+-----
          // neighbor overlaps:          |    [--+--]
          // neighbor starts:            [-------+--]
          case $this->overlaps($neighbor):
          case $this->starts($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            // Adds.
            $this->neighbors[] = clone $this->daterange;
          try {
            $this->neighbors[$i]->setDateStart($end->modify('+1 day'));
          }
          catch (Exception $e) {
          }
          $this->addDaterangeToUpsert();
            $this->upsert_segments[] = clone $this->neighbors[$i];
          if($i == 1) {
            var_dump("5");
            var_dump($this->upsert_segments);
          }
            break;

          // daterange:             -----+-------+-----
          // neighbor finishedBy:        |  [----]
          // neighbor contains:          | [----]|
          // neighbor startedBy:         [-----] |
          case $this->finishedBy($neighbor):
          case $this->contains($neighbor):
          case $this->startedBy($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            // Adds.
            $this->addDaterangeToUpsert();
            $this->neighbors[$i] = clone $this->daterange;
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
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            // Adds.
            $segment_left = clone $neighbor;
            try {
              $segment_left->setDateEnd($start->modify('-1 day'));
            }
            catch (Exception $e) {
            }
            $this->neighbors[] = $segment_left;
            $this->neighbors[] = clone $this->daterange;
            try {
              $this->neighbors[$i]->setDateStart($end->modify('+1 day'));
            }
            catch (Exception $e) {
            }
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
            // deletes.
            $this->old_segments[] = clone $neighbor;
            // Adds.
          try {
            $this->neighbors[$i]->setDateEnd($start->modify('-1 day'));
          }
          catch (Exception $e) {
          }
          $this->neighbors[] = clone $this->daterange;
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
      unset($segment, $start, $end);

    }
    unset($start, $end);
//    die();
    //$this->checkDatesRepeatConsecutive();
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
    if (!in_array($this->daterange, $this->upsert_segments, TRUE)) {
      $this->upsert_segments[] = clone $this->daterange;
    }
  }

  /**
   * Recursive function to merge dates.
   *
   * @throws \Exception
   */
  protected function checkDatesRepeatConsecutive() {

    if (count($this->upsert_segments) > 2) {

      // Sorts the array.
      usort($this->upsert_segments, [$this, 'compare']);

      $start = $end = NULL;

      foreach ($this->upsert_segments as $i => $record) {

        if ($i === 0) {
          $start = clone $record->getDateStart();
          $end = clone $record->getDateEnd();
          continue;
        }

        if (($record->getDateStart() >= $start) && ($record->getDateEnd() <= $end)) {
          $this->neighbors = [];
          $this->neighbors = $this->upsert_segments;
          $this->upsert_segments = [];
          return $this->updateNeighbors();
        }

        $start = clone $record->getDateStart();
        $end = clone $record->getDateEnd();
      }
    }

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

}
