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
    if (count($this->old_segments)) {
      foreach ($this->old_segments as $record) {
        $values[] = $record->getDateStart()->format(DATE_FORMAT);
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

    // Sorts the array.
    usort($this->neighbors, [$this, 'compare']);

    foreach ($this->neighbors as $i => $neighbor) {

      // Compares equal floats (abs(($a-$b)/$b) < 0.00001)
      if (abs($neighbor->getPrice() - $this->daterange->getPrice()) < PHP_FLOAT_EPSILON ) {

        // Merges.
        switch (TRUE) {

          // daterange:             -----+-------+-----
          // neighbor before:            |       |   [---]
          // neighbor after:     [---]   |       |
          case $this->before($neighbor):
          case $this->after($neighbor):
            // Neighbors.
            $this->addDaterangeToNeighbors();
            // Adds.
            $this->addDaterangeToUpsert();
            break;

          // daterange:             -----+-------+-----
          // neighbor meets:             |       |[---]
          // neighbor overlaps:          |    [--+--]
          // neighbor finished-by:       |  [----]
          case $this->meets($neighbor):
          case $this->overlaps($neighbor):
          case $this->finishedBy($neighbor):
            // Deletes..
            $this->old_segments[] = clone $neighbor;
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $this->neighbors[$i]->setDateStart(clone $this->daterange->getDateStart());
            $this->neighbors[] = clone $this->neighbors[$i];
            // Adds.
            $this->upsert_segments[] = clone $this->neighbors[$i];
            break;

          // daterange:             -----+-------+-----
          // neighbor contains:          | [---] |
          case $this->contains($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $this->neighbors[$i] = clone $this->daterange;
            $this->neighbors[] = clone $this->daterange;
            // Adds.
            $this->addDaterangeToUpsert();
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
            break;

          // daterange:             -----+-------+-----
          // neighbor startedBy:         [-----] |
          // neighbor overlappedBy:   [--+---]   |
          // neighbor metBy:        [---]|       |
          case $this->startedBy($neighbor):
          case $this->overlappedBy($neighbor):
          case $this->metBy($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $this->neighbors[$i]->setDateEnd(clone $this->daterange->getDateEnd());
            $this->neighbors[] = clone $this->neighbors[$i];
            // Adds.
            $this->upsert_segments[] = clone $this->neighbors[$i];
            break;

        }

      }
      else {

        // Splits.
        switch (TRUE) {

          // daterange:             -----+-------+-----
          // neighbor before:            |       |   [---]
          // neighbor after:     [---]   |       |
          case $this->before($neighbor):
          case $this->after($neighbor):
            // Neighbors.
            $this->addDaterangeToNeighbors();
            // Adds.
            $this->addDaterangeToUpsert();
            break;

          // daterange:             -----+-------+-----
          // neighbor overlaps:          |    [--+--]
          // neighbor starts:            [-------+--]
          case $this->overlaps($neighbor):
          case $this->starts($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $this->addDaterangeToNeighbors();
            $end = clone $this->daterange->getDateEnd();
            $this->neighbors[$i]->setDateStart($end->modify('+1 day'));
            $this->neighbors[] = clone $this->neighbors[$i];
            // Adds.
            $this->addDaterangeToUpsert();
            $this->upsert_segments[] = clone $this->neighbors[$i];
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
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $this->neighbors[$i] = clone $this->daterange;
            $this->neighbors[] = clone $this->daterange;
            // Adds.
            $this->addDaterangeToUpsert();
            break;

          // daterange:             -----+-------+-----
          // neighbor equals:            [-------]
          case $this->equals($neighbor):
            break;

          // daterange:             -----+-------+-----
          // neighbor during:         [--+-------+--]
          case $this->during($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $segment_left = clone $neighbor;
            $start = clone $this->daterange->getDateStart();
            $segment_left->setDateEnd($start->modify('-1 day'));
            $this->neighbors[] = $segment_left;
            $this->addDaterangeToNeighbors();
            $end = clone $this->daterange->getDateEnd();
            $this->neighbors[$i]->setDateStart($end->modify('+1 day'));
            $this->neighbors[] = clone $this->neighbors[$i];
            // Adds.
            $this->upsert_segments[] = clone $segment_left;
            $this->addDaterangeToUpsert();
            $this->upsert_segments[] = clone $this->neighbors[$i];
            break;

          // daterange:             -----+-------+-----
          // neighbor finishes:        [-+-------]
          // neighbor overlappedBy:  [---+----]  |
          case $this->finishes($neighbor):
          case $this->overlappedBy($neighbor):
            // Deletes.
            $this->old_segments[] = clone $neighbor;
            $this->removeUpsert($neighbor->getDateStart());
            // Neighbors.
            $start = clone $this->daterange->getDateStart();
            $this->neighbors[$i]->setDateEnd($start->modify('-1 day'));
            $this->neighbors[] = clone $this->neighbors[$i];
            $this->addDaterangeToNeighbors();
            // Adds.
            $this->upsert_segments[] = clone $this->neighbors[$i];
            $this->addDaterangeToUpsert();
            break;

        }

      }

    }

    $this->checkDatesRepeatConsecutive();

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
      if ($segment->getDateStart() == $this->daterange->getDateStart()) {
        $included = TRUE;
        break;
      }
    }

    if (!$included) {
      $this->upsert_segments[] = clone $this->daterange;
    }

  }

  /**
   * If not already added, adds the daterange to the upsert_segments array.
   */
  protected function addDaterangeToNeighbors(): void {

    $included = FALSE;

    foreach ($this->neighbors as $segment) {
      if ($segment->getDateStart() == $this->daterange->getDateStart()) {
        $included = TRUE;
        break;
      }
    }

    if (!$included) {
      $this->neighbors[] = clone $this->daterange;
    }

  }

  /**
   * If not already added, adds the daterange to the upsert_segments array.
   */
  protected function removeUpsert(DateTime $start): void {

    foreach ($this->upsert_segments as $i => $segment) {
      if ($segment->getDateStart() == $start) {
        unset($this->upsert_segments[$i]);
        break;
      }
    }

  }

  /**
   * Recursive function to merge dates.
   */
  protected function checkDatesRepeatConsecutive() {

    if (count($this->upsert_segments) > 1) {

      $this->upsert_segments = array_values($this->upsert_segments );

      foreach ($this->upsert_segments as $i => &$segment) {

        if ($i === 0) {
          continue;
        }

        $this->daterange = $segment;

        $j = $i - 1;

        if ( ! isset($this->upsert_segments[$j])) {
          break;
        }

        $neighbor = $this->upsert_segments[$j];

        // Compares equal floats (abs(($a-$b)/$b) < 0.00001)
        if (abs($segment->getPrice() - $neighbor->getPrice()) < PHP_FLOAT_EPSILON ) {

          // Merges.
          switch (TRUE) {

            // daterange:             -----+-------+-----
            // neighbor meets:             |       |[---]
            // neighbor overlaps:          |    [--+--]
            // neighbor finished-by:       |  [----]
            case $this->meets($neighbor):
            case $this->overlaps($neighbor):
            case $this->finishedBy($neighbor):
              $this->upsert_segments[$i]->setDateEnd(clone $this->upsert_segments[$j]->setDateEnd());
              $this->old_segments[] = clone $this->upsert_segments[$j];
              unset($this->upsert_segments[$j]);
              break;

            // daterange:             -----+-------+-----
            // neighbor contains:          | [---] |
            case $this->contains($neighbor):
              $this->old_segments[] = clone $this->upsert_segments[$j];
              unset($this->upsert_segments[$j]);
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
              $this->old_segments[] = clone $this->upsert_segments[$i];
              unset($this->upsert_segments[$i]);
              break;

            // daterange:             -----+-------+-----
            // neighbor startedBy:         [-----] |
            // neighbor overlappedBy:   [--+---]   |
            // neighbor metBy:        [---]|       |
            case $this->startedBy($neighbor):
            case $this->overlappedBy($neighbor):
            case $this->metBy($neighbor):
              $this->upsert_segments[$i]->setDateStart(clone $this->upsert_segments[$j]->getDateStart());
              $this->old_segments[] = clone $this->upsert_segments[$j];
              unset($this->upsert_segments[$j]);
              break;

          }
        }
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
   *             ds          de         ds   de        dsde
   * daterange:  [-----+-----]-----+    [----]----+    -I----+
   * neighbor:   +-----[-----+-----]    -----[----]    -[----]
   *                   ns          ne        ns   ne    ns   ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function overlaps(Daterange $neighbor): bool {

    return
      // Starts:  ds <= ns
      $this->daterange->getDateStart() <= $neighbor->getDateStart() &&
      // Overlap: ns <= de
      $neighbor->getDateStart() <= $this->daterange->getDateEnd() &&
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
   *                   ds          de        ds   de       dsde
   * daterange:  +-----[-----+-----]    +----[----]    -+---I-
   * neighbor:   [-----+-----]-----+    [----]----+    -[---]-
   *             ns          ne         ns   ne         ns  ne
   *
   * @param \Daterange\v1\Model\Daterange $neighbor
   *
   * @return bool
   */
  protected function overlappedBy(Daterange $neighbor): bool {

    return
      // Starts:  ns < ds
      $neighbor->getDateStart() < $this->daterange->getDateStart() &&
      // Overlap: ds <= ne
      $this->daterange->getDateStart() <= $neighbor->getDateEnd() &&
      // Ends:    ne < de
      $neighbor->getDateEnd() <= $this->daterange->getDateEnd();
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
