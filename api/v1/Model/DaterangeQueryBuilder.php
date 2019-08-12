<?php

namespace Daterange\v1\Model;

/**
 * Use to create queries to the data base.
 *
 * @package Daterange\Model
 */
class DaterangeQueryBuilder {

  /**
   * Table use in the query.
   *
   * @var string.
   */
  protected $table;

  /**
   * Main query statement.
   *
   * @var string
   */
  protected $queryStatement;

  /**
   * Main query parameters.
   *
   * @var array
   */
  protected $paramStatement = [];

  /**
   * Fields to filter a query by.
   *
   * @var string
   */
  protected $filters = NULL;

  /**
   * Pager offset.
   *
   * @var int
   */
  protected $offset = NULL;

  /**
   * Pager limit.
   *
   * @var int
   */
  protected $limit = NULL;

  /**
   * Fields to return in results.
   *
   * @var array
   */
  protected $fields = NULL;

  /**
   * Field use in sort.
   *
   * @var string
   */
  protected $sort = NULL;


  /**
   * Gets a DaterangeQueryBuilder instance.
   *
   * @param string $table
   * @param array  $extraParameters
   */
  public function __construct(string $table, array $extraParameters) {

    // New Daterange use to obtain properties.
    $daterange = new Daterange();

    // Set Daterange table.
    $this->table = $table;

    // Sets Filters.
    $this->set_filters($extraParameters, $daterange->getRequiredProperties());

    // Sets Offset.
    $this->set_offset($extraParameters);

    // Sets Limit.
    $this->set_limit($extraParameters);

    // Sets Fields.
    $this->set_fields($extraParameters, $daterange->getRequiredProperties());

    // Sets Sort.
    $this->set_sort($extraParameters, $daterange->getRequiredProperties());

    // Generates daterange query.
    $this->generate_query();

    unset($daterange);

  }

  /**
   * Sets the variables and values to use in the WHERE clause.
   *
   * @param array $extraParameters
   * @param array $filtering_properties
   */
  protected function set_filters(array $extraParameters, array $filtering_properties): void {
    $filters = $extraParameters[FILTER] ?? NULL;
    if (!($filters === NULL) || !empty($filters)) {
      $filter_comma = explode(',', trim($filters));
      foreach ($filter_comma as $key_comma) {
        $filter = explode(':', $key_comma);
        $filter[0] = strtolower(trim(str_replace('"', '', $filter[0])));
        $filter[1] = str_replace('*', '%', $filter[1]);
        if (array_key_exists($filter[0], $filtering_properties)) {
          $this->transform_filter_value($filter[0], $filter[1]);
          $this->paramStatement[':' . $filter[0] . '_filter'] = $filter[1];
        }
      }
    }
  }

  /**
   * Helper function of set_filters().
   *
   * @param string $field
   * @param string $value
   */
  protected function transform_filter_value(string $field, string $value): void {
    $operator = strpos($value, '%') ? ' LIKE ' : '=';
    $this->filters .= $this->filters === NULL ? ' WHERE ' : ' AND ';
    $this->filters .= "`{$field}`{$operator}:{$field}_filter";
  }

  /**
   * Sets the variables and values of the OFFSET.
   *
   * @param array $extraParameters
   */
  protected function set_offset(array $extraParameters): void {
    $offset = $extraParameters[OFFSET] ?? NULL;
    $offset = trim($offset);
    if ($offset !== NULL && is_numeric($offset)) {
      $this->paramStatement[':offset'] = (int) $offset;
    }
    else {
      $this->paramStatement[':offset'] = 0;
    }
  }

  /**
   * Sets the variables and values of the LIMIT.
   *
   * @param array $extraParameters
   */
  protected function set_limit(array $extraParameters): void {
    $limit = $extraParameters[LIMIT] ?? NULL;
    $limit = trim($limit);
    $this->limit = ' LIMIT :offset, :limit ';
    if ($limit !== NULL && is_numeric($limit)) {
      $this->paramStatement[':limit'] = (int) $limit;
    }
    else {
      $this->paramStatement[':limit'] = 1844674407370955161;
    }
  }

  /**
   * Sets the field values to SELECT.
   *
   * @param array $extraParameters
   * @param array $person_properties
   */
  protected function set_fields(array $extraParameters, array $person_properties): void {
    $fields = $extraParameters[FIELDS] ?? NULL;
    $fields = trim($fields);
    if ($fields !== NULL || !empty($fields)) {
      $fields = explode(',', $fields);
      foreach ($fields as $field) {
        $field = strtolower(trim(str_replace('"', '', $field)));
        if (array_key_exists($field, $person_properties)) {
          $this->fields[] = $field;
        }
      }
    }
    $this->fields = ($this->fields === NULL) ? '*' : implode(', ', $this->fields);
  }

  /**
   * Sets the variables and values to SORT BY.
   *
   * @param array $extraParameters
   * @param array $person_properties
   */
  protected function set_sort(array $extraParameters, array $person_properties): void {
    $sort = $extraParameters[SORT] ?? NULL;
    $sort = trim($sort);
    if ($sort !== NULL || !empty($sort)) {
      $sort_comma = explode(',', str_replace('"', '', $sort));
      foreach ($sort_comma as $sort_string) {
        $sort_space = explode(' ', trim($sort_string));
        $field = strtolower($sort_space[0]);
        $order = strtoupper($sort_space[1]);
        if (array_key_exists($field, $person_properties) && ($order === ASC || $order === DESC)) {
          $this->sort .= ($this->sort === NULL) ? ' ORDER BY ' : ', ';
          $this->sort .= "`{$field}` {$order}";
          // $this->paramStatement[':' . $field . '_sort'] = $field;
        }
      }
    }
  }

  /**
   * Creates the query string.
   */
  protected function generate_query(): void {
    $this->queryStatement = "SELECT {$this->fields} FROM `{$this->table}` {$this->filters} {$this->sort} {$this->limit}";
  }

  /**
   * @return string
   */
  public function getQueryStatement(): string {
    return $this->queryStatement;
  }

  /**
   * @return array
   */
  public function getParamStatement(): array {
    return $this->paramStatement;
  }

}
