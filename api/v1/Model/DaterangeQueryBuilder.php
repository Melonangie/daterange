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
     * @var $table string.
     */
    protected $table;

    /**
     * Main query statement.
     *
     * @var $statement string.
     */
    protected $queryStatement;

    /**
     * Main query parameters.
     *
     * @var $statement array.
     */
    protected $paramStatement = [];

    /**
     * Fields to filter a query by.
     *
     * @var $filters string.
     */
    protected $filters = NULL;

    /**
     * Pager offset.
     *
     * @var $offset int.
     */
    protected $offset = NULL;

    /**
     * Pager limit.
     *
     * @var $limit int.
     */
    protected $limit = NULL;

    /**
     * Fields to return in results.
     *
     * @var $fields array.
     */
    protected $fields = NULL;

    /**
     * Field use in sort.
     *
     * @var $sort string..
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
      $this->set_filters($extraParameters[FILTER], $daterange->getFilteringProperties());

      // Sets Offset.
      $this->set_offset($extraParameters[OFFSET]);

      // Sets Limit.
      $this->set_limit($extraParameters[LIMIT]);

      // Sets Fields.
      $this->set_fields($extraParameters[FIELDS], $daterange->getProperties());

      // Sets Sort.
      $this->set_sort($extraParameters[SORT], $daterange->getSortingProperties());

      // Generates daterange query.
      $this->generate_query();

    }

    /**
     * Sets the variables and values to use in the WHERE clause.
     *
     * @param $filters
     * @param $person_filtering_properties
     */
    protected function set_filters($filters, $person_filtering_properties): void {
      if (!($filters === NULL) || !empty($filters)) {
        $filter_comma = explode(',', trim($filters));
        foreach ($filter_comma as $key_comma) {
          $filter = explode(':', $key_comma);
          $filter[0] = mb_strtolower(trim(str_replace('"', '', $filter[0])), CHARSET);
          $filter[1] = str_replace('*', '%', $filter[1]);
          if (array_key_exists($filter[0], $person_filtering_properties)) {
            $this->transform_filter_value($filter[0], $filter[1]);
            $this->paramStatement[':' . $filter[0] . '_filter'] = $filter[1];
          }
        }
      }
    }

    /**
     * Helper function of set_filters().
     *
     * @param $field
     * @param $value
     */
    protected function transform_filter_value($field, $value): void {
      $operator = strpos($value, '%') ? ' LIKE ' : '=';
      $this->filters .= $this->filters === NULL ? ' WHERE ' : ' AND ';
      $this->filters .= "`{$field}`{$operator}:{$field}_filter";
    }

    /**
     * Sets the variables and values of the OFFSET.
     *
     * @param $offset
     */
    protected function set_offset($offset): void {
      $offset = trim($offset);
      if ($offset !== NULL && is_numeric($offset)) {
        //$this->offset = ' OFFSET :offset ';
        $this->paramStatement[':offset'] = (int) $offset;
      }
      else {
        $this->paramStatement[':offset'] = (int) 0;
      }
    }

    /**
     * Sets the variables and values of the LIMIT.
     *
     * @param $limit
     */
    protected function set_limit($limit) {
      $limit = trim($limit);
      $this->limit = ' LIMIT :offset, :limit ';
      if (!is_null($limit) && is_numeric($limit)) {
        $this->paramStatement[':limit'] = (int) $limit;
      }
      else {
        $this->paramStatement[':limit'] = (int) 1844674407370955161;
      }
    }

    /**
     * Sets the field values to SELECT.
     *
     * @param $fields
     * @param $person_properties
     */
    protected function set_fields($fields, $person_properties) {
      $fields = trim($fields);
      if (!is_null($fields) || !empty($fields)) {
        $fields = explode(',', $fields);
        foreach ($fields as $field) {
          $field = trim(mb_strtolower(str_replace('"', '', $field), CHARSET));
          if (array_key_exists($field, $person_properties)) {
            if ($field == 'phones') {
              $this->filter_phones = TRUE;
              $field = 'id';
            }
            if ($field == 'emails') {
              $this->filter_emails = TRUE;
              $field = 'id';
            }
            $this->fields[] = $field;
          }
        }
      }
      $this->fields = ($this->fields === NULL) ? '*' : implode(', ', $this->fields);
    }

    /**
     * Sets the variables and values to SORT BY.
     *
     * @param $sort
     * @param $person_sorting
     */
    protected function set_sort($sort, $person_sorting) {
      $sort = trim($sort);
      if (!is_null($sort) || !empty($sort)) {
        $sort_comma = explode(',', str_replace('"', '', $sort));
        foreach ($sort_comma as $sort_string) {
          $sort_space = explode(' ', trim($sort_string));
          $field = mb_strtolower($sort_space[0], CHARSET);
          $order = mb_strtoupper($sort_space[1], CHARSET);
          if (array_key_exists($field, $person_sorting) && ($order == ASC || $order == DESC)) {
            $this->sort .= (is_null($this->sort)) ? ' ORDER BY ' : ', ';
            $this->sort .= "`{$field}` {$order}";
            // $this->paramStatement[':' . $field . '_sort'] = $field;
          }
        }
      }
    }

    /**
     * Creates the query string.
     */
    protected function generate_query() {
      $this->queryStatement = "SELECT {$this->fields} FROM `{$this->person_table}` {$this->filter} {$this->sort} {$this->limit}";
    }

  }
