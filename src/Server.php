<?php

namespace WA\Database;

class Server
{
    static public function execute($query)
    {
        global $mysqli;

        if (!is_string($query) && !is_array($query))
            return false;

        if (is_array($query))
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
}
