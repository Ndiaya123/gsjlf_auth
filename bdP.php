<?php


class BDP
{
    public function connect()
    {
        try {
            $DBHOST = "o86fy.myd.infomaniak.com";
            $DBUSER = "o86fy_ndiaya";
            $DBPASS = "Passercriat2022";
            $DBNAME = "o86fy_personnelTest";
            $dsn = "mysql:dbname=" . $DBNAME . ";host=" . $DBHOST;
            $db = new PDO($dsn, $DBUSER, $DBPASS);
            return $db;
        } catch (PDOException $e) {
            echo "erreurConnexion";
            die;
        }


    }
}
