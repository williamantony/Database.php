<?php

namespace WA\Database;

use Ramsey\Uuid\Uuid;
use WA\SQL\{Selector, Insert, Update};
use WA\ErrorHandler\Error;

class Table
{
    public function __construct($values = null)
    {
        if (is_string($values))
            $this->setPrimaryKey($values);

        if (is_array($values))
            $this->fromArray($values);
    }

    protected function uuid(string $uuid = "")
    {
        if (!Uuid::isValid($uuid))
            return Uuid::uuid4()->toString();

        return $uuid;
    }

    public function fromArray(array $values = [])
    {
        foreach ($values as $key => $value) {
            if (property_exists(get_class($this), $key)) {
                $setter = "set" . ucfirst($key);
                $this->$setter($value);
            }
        }
    }

    public function toArray(bool $strict = false)
    {
        $array = array();
        $attributes = static::$attributes;

        foreach ($attributes as $value) {
            if ($strict || isset($this->$value))
                $array[$value] = $this->$value;
        }

        return $array;
    }

    public function toString()
    {
        $primaryKey = static::$primaryKey;
        return $this->$primaryKey;
    }

    public function setPrimaryKey(string $value = null, bool $create_new = false)
    {
        $primaryKey = static::$primaryKey;
        $set_pk = "set" . ucfirst(static::$primaryKey);

        if (is_string($value))
            return $this->$set_pk($value);
        
        if ($create_new)
            return $this->$set_pk($this->uuid());

        if (!isset($this->$primaryKey))
            return $this->$set_pk($this->uuid());
    }



    static public function execute()
    {
        global $mysqli;

        $query = implode("", func_get_args());

        if ($mysqli->multi_query($query))
        {
            $results = array();

            do {
                if ($result = $mysqli->store_result()) {
                    array_push($results, $result->fetch_all(MYSQLI_ASSOC));
                    $result->free();
                }
                
                if (!$mysqli->more_results())
                    break;

            } while ($mysqli->next_result());

            return $results;
        }

        return false;
    }

    public function truncateQuery()
    {
        $tableName = static::$tableName;
        return "TRUNCATE `$tableName`;";
    }



    public function insert(bool $new_pk = false)
    {
        global $mysqli;

        $tableName = static::$tableName;

        if ($new_pk)
            $this->setPrimaryKey($create_new = true);

        $sql = new Insert();
        $query = $sql
            ->into($tableName)
            ->values($this->toArray())
            ->getQuery();

        if (!$inserted = $mysqli->query($query))
            new Error($mysqli->error, __METHOD__, $query);

        return $inserted;
    }

    public function update()
    {
        global $mysqli;

        $tableName = static::$tableName;
        $primaryKey = static::$primaryKey;
        
        $sql = new Update($tableName);
        $query = $sql
            ->values($this->toArray())
            ->where(array( "$primaryKey" => $this->$primaryKey ))
            ->getQuery();

        if (!$updated = $mysqli->query($query))
            new Error($mysqli->error, __METHOD__, $query);

        return $updated;
    }



    static public function search(array $where = null)
    {
        global $mysqli;

        $sql = new Selector();
        $sql->table(static::$tableName);

        $query_1 = $sql
            ->where($where)
            ->getQuery();

        /* LIKE - starting with */
        $query_2 = $sql
            ->clear($except = array( "tablename" ))
            ->whereLike($where, "-%")
            ->getQuery();

        /* LIKE - anywhere */
        $query_3 = $sql
            ->clear($except = array( "tablename" ))
            ->whereLike($where, "%-%")
            ->getQuery();

        $results = static::execute( $query_1, $query_2, $query_3 );

        if ($results)
        {
            $results = array_merge(...$results);
            $results = array_unique($results, SORT_REGULAR);

            return $results;
        }

        return false;
    }

    static public function fetch(array $where = null, int $limit = null)
    {
        global $mysqli;

        $sql = new Selector();
        $sql->table(static::$tableName)
            ->where($where);

        if (is_int($limit))
            $sql->limit($limit);

        $query = $sql->getQuery();

        if ($results = $mysqli->query($query))
            return $results;

        return false;
    }

    private function fetchByPrimaryKey()
    {
        $primaryKey = static::$primaryKey;

        if (!isset($this->$primaryKey))
            return false;

        $where = array(
            "$primaryKey" => $this->$primaryKey,
        );

        if ($results = static::fetch($where, $limit = 1))
        {
            if ($results->num_rows == 1)
                return $results;
        }

        return false;
    }

    private function fetchOneByValues()
    {
        $strict = false;

        if ($results = static::fetch($this->toArray($strict), $limit = 2))
        {
            if ($results->num_rows == 1)
                return $results;

            elseif ($results->num_rows == 0)
                return false;
        }

        $strict = true;

        if ($results = static::fetch($this->toArray($strict), $limit = 1))
        {
            if ($results->num_rows == 1)
                return $results;
        }

        return false;
    }

    public function fetchOne()
    {
        if ($results = $this->fetchByPrimaryKey())
            return $results;

        if ($results = $this->fetchOneByValues())
            return $results;

        return false;
    }



    public function exists()
    {
        if ($result = $this->fetchByPrimaryKey())
        {
            if ($result->num_rows > 0)
                return true;
        }

        return false;
    }

    public function populate(bool $overwrite = false)
    {
        if (!$results = $this->fetchOne()) {
            $this->setPrimaryKey($create_new = true);
            return false;
        }

        while ($row = $results->fetch_assoc())
        {
            foreach ($row as $name => $value) {
                if (!$overwrite && isset($this->$name))
                    continue;

                if (!in_array($name, static::$attributes))
                    continue;

                $setter = "set" . ucfirst($name);
                $this->$setter($value);
            }
        }

        return true;
    }

    public function save(bool $allowDuplicates = false)
    {
        $this->populate();

        if (!$this->exists())
            return $this->insert();

        else if ($allowDuplicates)
            return $this->insert($create_new = true);
        
        return $this->update();
    }

}
