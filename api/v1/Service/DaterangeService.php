<?php

  namespace Daterange\v1\Service;

  use Exception;
  use PDO;
  use PDOException;
  use Daterange\v1\Model\DaterangeQueryBuilder as DaterangeQueryBuilder;
  use Daterange\v1\Exception\RestException as RestException;

  /**
   * Class DaterangeService
   *
   * @package Daterange\v1\Service
   */
  class DaterangeService {

    /**
     * The DataBase driver.
     *
     * @var string
     */
    private $driver;

    /**
     * The Database schema.
     *
     * @var mixed
     */
    private $schema;

    /**
     * A PDO instance.
     *
     * @var \PDO
     */
    private $pdo;

    /**
     * The request.
     *
     * @var array
     */
    private $request;

    /**
     * An array of Daterange instances.
     *
     * @var array
     */
    private $daterange;

    /**
     * Returns current DB driver.
     *
     * @return string
     */
    public function getDriver() {
      return $this->driver;
    }

    /**
     * Returns current request.
     *
     * @return mixed
     */
    public function getRequest() {
      return $this->request;
    }

    /**
     * Returns current Daterange array.
     *
     * @return mixed
     */
    public function getDaterange() {
      return $this->daterange;
    }

    /**
     * DaterangeService constructor.
     *
     * @param string $driver
     */
    public function __construct(string $driver) {

      @set_exception_handler([$this, 'exception_handler']);

      // Get Configurations.
      $config = new DBconfig();

      // Set the DB schema.
      $this->schema = $config->getDbElement(SCHEMA);

      // Set the DB driver.
      if (array_key_exists($driver, $config->getDb())) {
        $this->driver = $driver;
      }
      else {
        $error = [
          'msg' => "Bad DB driver: " . $driver,
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      // Create a PDO object.
      try {
        $this->pdo = new PDO(
          $config->getDb_element($this->driver)[DSN],
          $config->getDb_element($this->driver)[DB_USERNAME],
          $config->getDb_element($this->driver)[DB_PASSWORD],
          $config->getDb_element(PDO)
        );

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
        $this->pdo->setAttribute(PDO::ATTR_PERSISTENT, FALSE);
        $this->pdo->setAttribute(PDO::ATTR_AUTOCOMMIT, FALSE);
        $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);

      }
        // Log exception and throw error.
      catch (PDOException | Exception $exception) {
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'PDO Connection error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      } // Unset Configuration.
      finally {
        unset($config);
      }

    }

    /**
     * @param $exception
     */
    public function exception_handler($exception) {
      // Set headers.
      header('Content-Type: ' . CONTENT_TYPE_CHARSET_JSON);
      http_response_code($exception->getCode());

      // Set message.
      echo $exception->getMessage();
    }

    /**
     * Saves a Daterange instance to the Database.
     *
     * @param $person The instance of Daterange.
     *
     * @return string
     */
    public function createDaterange($person) {

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Insert the person to the main DB table.
        $person_table = $this->pdo->prepare(sprintf('INSERT INTO %s (MODIFIED, FIRSTNAME, SURENAME, PHOTO) VALUES (:modified, :firstname, :surename, :photo)', $this->schema['PERSONS_TABLE']));
        $person_table->bindValue(':modified', date("Y-m-d H:i:s"), PDO::PARAM_STR);
        $person_table->bindValue(':firstname', $person->getFirstname(), PDO::PARAM_STR);
        $person_table->bindValue(':surename', $person->getSurename(), PDO::PARAM_STR);
        $person_table->bindValue(':photo', $person->getPhoto(), PDO::PARAM_STR);
        $person_table->execute();

        // Gets the id.
        $id = $this->pdo->lastInsertId();

        // Insert the person emails to the DB.
        $person_table = $this->pdo->prepare(sprintf('INSERT INTO `%s` (`person_id`, `email`, `type`) VALUES (:email_id, :email, :email_type)', $this->schema['EMAILS_TABLE']));
        foreach ($person->getEmails() as $key => $value) {
          $person_table->bindParam(':email_id', $id, PDO::PARAM_INT);
          $person_table->bindParam(':email', $value['email'], PDO::PARAM_STR);
          $person_table->bindParam(':email_type', $value['type'], PDO::PARAM_STR);
          $person_table->execute();
        }

        // Insert the person phones to the DB.
        $person_table = $this->pdo->prepare(sprintf('INSERT INTO `%s` (`person_id`, `phone`, `type`) VALUES (:phone_id, :phone, :phone_type)', $this->schema['PHONES_TABLE']));
        foreach ($person->getPhones() as $key => $value) {
          $person_table->bindParam(':phone_id', $id, PDO::PARAM_INT);
          $person_table->bindParam(':phone', $value['phone'], PDO::PARAM_STR);
          $person_table->bindParam(':phone_type', $value['type'], PDO::PARAM_STR);
          $person_table->execute();
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        if (file_exists($person->getPhoto())) {
          unlink($person->getPhoto());
        }
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'Daterange service error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      return [
        "Code:" => "200",
        "Message:" => "Succesfully created person. Id: " . $id,
        "Error:" => "",
      ];
    }

    /**
     * Gets a specific Daterange instance from the DataBase, based on the id.
     *
     * @param $id
     *
     * @return string
     */
    public function readDaterange($id) {

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Gets a specific person from the DB.
        $person_table = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE id=:id', $this->schema['PERSONS_TABLE']));
        $person_table->bindParam(':id', $id, PDO::PARAM_INT);
        $person_table->execute();
        $person = $person_table->fetchObject('Persons\v1\Model\Person');

        // Get selected person emails.
        $emails = $this->pdo->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['EMAILS_VIEW']));
        $emails->bindParam(1, $person->getId(), PDO::PARAM_INT);
        $emails->execute();
        while ($email = $emails->fetch(PDO::FETCH_OBJ)) {
          $person->addEmail($email);
        }

        // Get selected person phones.
        $phones = $this->pdo->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['PHONES_VIEW']));
        $phones->bindParam(1, $person->getId(), PDO::PARAM_INT);
        $phones->execute();
        while ($phone = $phones->fetch(PDO::FETCH_OBJ)) {
          $person->addPhone($phone);
        }

        // Adds the basepath to get the images.
        if ($person->getPhoto()) {
          $person->setPhoto(BASE_PATH . $person->getPhoto());
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'Daterange service error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      // Return array of Daterange objects.
      return $person;
    }

    /**
     * Gets all Daterange instances from the DataBase.
     *
     * @return array
     */
    public function readDateranges() {

      $persons = [];

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Gets all persons from the DB.
        $person_table = $this->pdo->query(sprintf('SELECT * FROM %s', $this->schema['PERSONS_TABLE']));

        // Get selected person emails and phones.
        while ($person = $person_table->fetchObject('Persons\v1\Model\Person')) {

          $emails = $this->pdo->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['EMAILS_VIEW']));
          $emails->bindParam(1, $person->getId(), PDO::PARAM_INT);
          $emails->execute();
          while ($email = $emails->fetch(PDO::FETCH_OBJ)) {
            $person->addEmail($email);
          }

          $phones = $this->pdo->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['PHONES_VIEW']));
          $phones->bindParam(1, $person->getId(), PDO::PARAM_INT);
          $phones->execute();
          while ($phone = $phones->fetch(PDO::FETCH_OBJ)) {
            $person->addPhone($phone);
          }

          // Adds the basepath to get the images.
          if ($person->getPhoto()) {
            $person->setPhoto(BASE_PATH . $person->getPhoto());
          }

          $persons[] = $person;
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'Daterange service error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      // Return array of Daterange objects.
      return $persons;

    }

    /**
     * Gets specific Daterange from the DataBase, based on a payload query or
     * query string.
     *
     * @param $extraParameters
     *
     * @return array
     */
    public function readDaterangeQuery($extraParameters) {

      $persons = [];

      // Get the  dynamic query.
      $dynamic_query = new DaterangeQueryBuilder($this->schema['PERSONS_TABLE'], $extraParameters);

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Gets all persons from the DB.
        $query = $this->pdo->prepare($dynamic_query->getQueryStatement());
        $query->execute($dynamic_query->getParamStatement());

        // Get selected person emails and phones.
        while ($person = $query->fetchObject('Persons\v1\Model\Person')) {

          if ($dynamic_query->getFields() == '*' || $dynamic_query->getFilterEmails()) {
            $emails = $this->pdo->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['EMAILS_VIEW']));
            $emails->bindParam(1, $person->getId(), PDO::PARAM_INT);
            $emails->execute();
            while ($email = $emails->fetch(PDO::FETCH_OBJ)) {
              $person->addEmail($email);
            }
          }

          if ($dynamic_query->getFields() == '*' || $dynamic_query->getFilterPhones()) {
            $phones = $this->pdo->prepare(sprintf('SELECT s.* FROM (select @pid:=? p) parm, %s s', $this->schema['PHONES_VIEW']));
            $phones->bindParam(1, $person->getId(), PDO::PARAM_INT);
            $phones->execute();
            while ($phone = $phones->fetch(PDO::FETCH_OBJ)) {
              $person->addPhone($phone);
            }
          }

          $persons[] = $person;
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'PDO Connection error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      return $persons;

    }

    /**
     * Saves changes of a Daterange in the DataBase.
     *
     * @param $person
     *
     * @return mixed
     */
    public function updateDaterange($person) {

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Insert the person to the main DB table.
        $person_table = $this->pdo->prepare(sprintf('UPDATE `%s` SET MODIFIED=:modified, FIRSTNAME=:firstname, SURENAME=:surename, PHOTO=:photo WHERE `id`=:id', $this->schema['PERSONS_TABLE']));
        $person_table->bindValue(':modified', date("Y-m-d H:i:s"), PDO::PARAM_STR);
        $person_table->bindValue(':firstname', $person->getFirstname(), PDO::PARAM_STR);
        $person_table->bindValue(':surename', $person->getSurename(), PDO::PARAM_STR);
        $person_table->bindValue(':photo', $person->getPhoto(), PDO::PARAM_STR);
        $person_table->bindValue(':id', $person->getId(), PDO::PARAM_INT);
        $person_table->execute();


        // Insert the person emails to the DB.
        $person_table = $this->pdo->prepare(sprintf('INSERT INTO `%s` (`person_id`, `email`, `type`) VALUES (:email_id, :email, :email_type)', $this->schema['EMAILS_TABLE']));
        foreach ($person->getEmails() as $key => $value) {
          $person_table->bindParam(':email_id', $person->getId(), PDO::PARAM_INT);
          $person_table->bindParam(':email', $value['email'], PDO::PARAM_STR);
          $person_table->bindParam(':email_type', $value['type'], PDO::PARAM_STR);
          $person_table->execute();
        }

        // Insert the person phones to the DB.
        $person_table = $this->pdo->prepare(sprintf('INSERT INTO `%s` (`person_id`, `phone`, `type`) VALUES (:phone_id, :phone, :phone_type)', $this->schema['PHONES_TABLE']));
        foreach ($person->getPhones() as $key => $value) {
          $person_table->bindParam(':phone_id', $person->getId(), PDO::PARAM_INT);
          $person_table->bindParam(':phone', $value['phone'], PDO::PARAM_STR);
          $person_table->bindParam(':phone_type', $value['type'], PDO::PARAM_STR);
          $person_table->execute();
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'PDO Connection error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      return [
        "Code:" => "200",
        "Message:" => "Succesfully updated person. Id: " . $person->getId(),
        "Error:" => "",
      ];
    }

    /**
     * Deletes a Daterange from the Database, based on a id.
     *
     * @param $id
     *
     * @return mixed
     */
    public function deleteDaterange($id) {

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Deletes person.
        $person_table = $this->pdo->prepare(sprintf('DELETE FROM `%s` WHERE `id`=:id', $this->schema['PERSONS_TABLE']));
        $person_table->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $person_table->execute();

        // Deletes all emails and phones.
        foreach (['EMAILS_TABLE', 'PHONES_TABLE'] as $table) {
          $some_table = $this->pdo->prepare(sprintf('DELETE FROM `%s` WHERE `person_id`=:id', $this->schema[$table]));
          $some_table->bindValue(':id', (int) $id, PDO::PARAM_INT);
          $some_table->execute();
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'Daterange service error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      return [
        "Code:" => "200",
        "Message:" => "Successfully deleted person. Id: " . $id,
        "Error:" => "",
      ];
    }

    /**
     * Deletes a Daterange Emails from the Database, based on a id.
     *
     * @param $id
     *
     * @param $table
     *
     * @return string
     */
    public function deleteDaterangePivot($id, $table) {

      $table = $this->schema[$table];

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Gets a specific person from the DB.
        $some_table = $this->pdo->prepare(sprintf('DELETE FROM `%s` WHERE `person_id`=:id', $table));
        $some_table->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $some_table->execute();

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException | Exception $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'Daterange service error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      return TRUE;
    }

    /**
     * Check if record exists.
     *
     * @param $id
     *
     * @return string
     */
    public function verifyExist($field, $field_value, $pdo_param, $table) {

      $exist = FALSE;

      try {

        // Begin transaction.
        $this->pdo->beginTransaction();

        // Checks record exist.
        $some_table = $this->pdo->prepare(sprintf('SELECT 1 from `%s` WHERE `%s`=:field_value LIMIT 1', $this->schema[$table], $field));
        $some_table->bindValue(':field_value', $field_value, $pdo_param);
        $some_table->execute();
        if ($some_table->fetchColumn()) {
          $exist = TRUE;
        }

        // Commits transaction.
        $this->pdo->commit();

      }
      catch (PDOException $exception) {
        $this->pdo->rollBack();
        $error = [
          'err_msg' => $exception->getMessage(),
          'err_code' => $exception->getCode(),
          'msg' => 'Daterange service error.',
          'class' => __CLASS__,
          'func' => __METHOD__,
        ];
        throw new RestException($error);
      }

      return $exist;
    }

  }
