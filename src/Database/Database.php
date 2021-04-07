<?php

namespace Spier\Database;

use PDO;
use PDOException;
use Spier\Logger\Logger;

/**
 * Class Database
 * @package Spier
 */
class Database extends PDO
{
    private static $dbInstance;
    private bool $error = false;
    private $results;
    private int $count = 0;
    private Logger $logger;

    /**
     * Database constructor.
     * @param $host
     * @param $user
     * @param $pass
     * @param $dbname
     */
    public function __construct($host = 'localhost', $user = 'root', $pass = '', $dbname = '')
    {
        $this->logger = new Logger();
        try {
            parent::__construct('mysql:host=' . $host . ';dbname=' . $dbname, $user, $pass);
            $this->exec("SET time_zone='+05:30';");
        } catch (PDOException $e) {
            $this->logger->logDBError($e->getMessage() . PHP_EOL . $e->getTraceAsString());
        }
    }

    /**
     * Description:
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $database
     * @return Database
     * DateTime: 14/11/2019 8:17 PM
     * Created By: rpurant
     */
    public static function getInstance(string $host, string $user, string $pass, string $database): Database
    {
        if (self::$dbInstance !== null) {
            return self::$dbInstance;
        }

        self::$dbInstance = new self($host, $user, $pass, $database);
        return self::$dbInstance;
    }

    /**
     * Description: Executes the select action for SQL query
     * @param $table
     * @param $where
     * @return bool|Database
     * DateTime: 05/10/2018 1:17 AM
     * Created By: rpurant
     */
    public function get($table, $where)
    {
        return $this->action('SELECT *', $table, $where);
    }

    /**
     * Description: These method will execute the each database action
     * e.g. SELECT *, INSERT, DELETE or UPDATE
     * @param $action
     * @param $table
     * @param array $where
     * @return $this|bool
     * DateTime: 05/10/2018 1:13 AM
     * Created By: rpurant
     */
    public function action($action, $table, array $where = [])
    {
        if (count($where) === 3) {
            $operators = ['=', '>', '<', '<=', '>='];

            [$field, $operator, $value] = $where;

            if (in_array($operator, $operators, true)) {
                $sql = "{$action} FROM {$table} WHERE {$field} {$operator} ?";
                if (!$this->runQuery($sql, [$value])->error()) {
                    return $this;
                }
            }
        }
        return false;
    }

    /**
     * Description: Returns the success or failure of query execution
     * @return bool
     * DateTime: 05/10/2018 1:25 AM
     * Created By: rpurant
     */
    public function error(): bool
    {
        return $this->error;
    }

    /**
     * Description: Runs actual query and return the result
     * @param $sql
     * @param array $params
     * @return $this
     * DateTime: 05/10/2018 1:11 AM
     * Created By: rpurant
     */
    public function runQuery($sql, array $params = [])
    {
        $this->error = false;
        if ($query = $this->prepare($sql)) {
            $x = 1;
            if (count($params)) {
                foreach ($params as $param) {
                    $query->bindValue($x, $param);
                    $x++;
                }
            }

            $start = microtime(true);

            if ($query->execute()) {
                $this->results = $query->fetchAll(PDO::FETCH_OBJ);
                $this->count = $query->rowCount();

                // Log the DB stats
                $time = microtime(true) - $start;
                $res = "SQL Query: $sql \nExecution Time: $time";
                $this->logger->logQueryStats($res);
            } else {
                $time = microtime(true) - $start;
                $this->error = true;
                $errors = '';
                foreach ($query->errorInfo() as $error) {
                    $errors .= $error . ' ';
                }
                $errors .= "\r\nSQL Query: $sql";
                $errors .= "\r\nExecution Time: $time";

                $this->logger->logDBError($errors);
            }
        }
        return $this;
    }

    /**
     * Description: Executes the delete action for SQL query
     * @param $table
     * @param $where
     * @return bool|Database
     * DateTime: 05/10/2018 1:18 AM
     * Created By: rpurant
     */
    public function delete($table, $where)
    {
        return $this->action('DELETE', $table, $where);
    }

    /**
     * Description: Executes insert action for SQL query
     * @param $table
     * @param $fields
     * @return bool
     * DateTime: 05/10/2018 1:19 AM
     * Created By: rpurant
     */
    public function insert($table, $fields): bool
    {
        $keys = array_keys($fields);
        $values = null;
        $x = 1;

        foreach ($fields as $field) {
            $values .= '?';
            if ($x < count($fields)) {
                $values .= ', ';
            }
            $x++;
        }

        $sql = "INSERT INTO {$table} (`" . implode('`, `', $keys) . "`) VALUES ({$values})";

        return !$this->runQuery($sql, $fields)->error();
    }

    /**
     * Description: Executes update action  for SQL query
     * @param $table
     * @param array $where
     * @param $fields
     * @return bool
     * DateTime: 05/10/2018 1:19 AM
     * Created By: rpurant
     */
    public function update($table, array $where, $fields): bool
    {
        if (count($where) === 3) {
            $operators = ['=', '>', '<', '<=', '>='];

            [$field, $operator, $value] = $where;

            $set = null;
            $x = 1;

            foreach ($fields as $name => $val) {
                $set .= "{$name} = ?";
                if ($x < count($fields)) {
                    $set .= ', ';
                }
                $x++;
            }
            if (in_array($operator, $operators, true)) {
                $sql = "UPDATE {$table} SET {$set} WHERE {$field} {$operator} {$value}";
                return !$this->runQuery($sql, $fields)->error();
            }
        }
        return false;
    }

    /**
     * Description: Returns the first row of result set
     * @return array
     * DateTime: 05/10/2018 1:25 AM
     * Created By: rpurant
     */
    public function first()
    {
        return count($this->results()) > 0 ? $this->results()[0] : [];
    }

    /**
     * Description: Returns query execution results
     * @return mixed
     * DateTime: 05/10/2018 1:25 AM
     * Created By: rpurant
     */
    public function results()
    {
        return $this->results;
    }

    /**
     * Description: Returns row count
     * @return int
     * DateTime: 05/10/2018 1:26 AM
     * Created By: rpurant
     */
    public function count(): int
    {
        return $this->count;
    }

    /**
     * Description: Magic method clone is empty to prevent duplication of connection
     * DateTime: 05/10/2018 1:26 AM
     * Created By: rpurant
     */
    private function __clone()
    {
    }
}