<?php

namespace Mini\Model;

use PDO;

include_once 'csrf.php';

class Model
{
    /**
     * The database connection
     * @var PDO
     */
	private $db;

	/**
	 * The default page size
	 * @var int
	 */
	private $pageSize;

    /**
     * When creating the model, the configs for database connection creation are needed
     * @param $config
     */
    function __construct($config)
    {
        // PDO db connection statement preparation
        $dsn = 'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';port=' . $config['db_port'];

        // note the PDO::FETCH_OBJ, returning object ($result->id) instead of array ($result["id"])
        // @see http://php.net/manual/de/pdo.construct.php
        $options = array(PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ, PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING);

        // create new PDO db connection
        $this->db = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);

        $this->pageSize = $config['page_size'];
	}

    /**
     * @param string $formname
     * @return string
     */
	public function getToken($formname)
	{
	    return generate_token($formname);
    }

    /**
     * @param string $formname
     * @param string $token
     * @return boolean
     */
    public function checkToken($formname, $token)
    {
        return validate_token($formname, $token);
    }

    /**
     * @param string $username
     * @param int $type
     * @param string $description = ""
     */
    public function addSubmission($username, $type, $description = "")
    {
        if($type == 0)
            $type = $description;

        $sql = "INSERT INTO submissions(name,description) VALUES (?,?)";
        $query = $this->db->prepare($sql);
        $query->execute(array($username, $type));
    }

    public function getSubmissions()
    {
        $sql = "SELECT name, description, date FROM submissions ORDER BY date DESC";
        $query = $this->db->prepare($sql);
        $query->execute();

        return $query->fetchAll();
    }

    /**
     * @return int
     */
    public function getLastUpdate($table = "bots", $type = 0)
    {
        $sql = "SELECT date FROM ".$table." ORDER BY date DESC LIMIT 1";
        if($type != 0)
            $sql .= "WHERE type=?";

        $query = $this->db->prepare($sql);
        $query->execute(array($type));

        return strtotime($query->fetch()->date);
    }

    /**
     * @param int $type
     * @return int
     */
    public function getBotCount($type = 0)
    {
        $sql = "SELECT count FROM count";
        if($type != 0)
            $sql = "SELECT count(name) AS count FROM bots WHERE type=?";

        $query = $this->db->prepare($sql);
        $query->execute(array($type));

        return (int)$query->fetch()->count;
    }

    /**
     * @param int $count
     * @return int
     */
    public function getPageCount($limit, $count = null) {
        $limit = isset($limit) ? $limit : $this->pageSize;
        if($count === null)
            $count = $this->getBotCount();
        if($limit > 0)
            return ceil($count / (float)$limit);
        else
            return 0;
    }

    /**
     * @param int $page
     * @return int
     */
    public function getOffset($page)
    {
        return ($page - 1) * $this->pageSize;
    }

    private function doPagination($query, $offset, $limit = null, $start = ":start", $stop = ":stop")
    {
        $offset = isset($offset) ? $offset : 0;
        $limit = $limit !== null ? $limit : $this->pageSize;
        $query->bindValue($start, $offset, PDO::PARAM_INT);
        $query->bindValue($stop, $limit, PDO::PARAM_INT);
    }

    public function getBots($page = 1)
    {
        if($page <= $this->getPageCount($this->pageSize)) {
            $sql = "SELECT * FROM list LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $this->doPagination($query, $this->getOffset($page));
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getAllRawBots($offset, $limit)
    {
        $limit = isset($limit) ? $limit : $this->pageCount;
        if($limit > 0 && $offset < $this->getBotCount()) {
            $sql = "SELECT * FROM bots LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $this->doPagination($query, $offset, $limit);
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBotsByNames($names, $offset, $limit)
    {
        $limit = isset($limit) ? $limit : $this->pageCount;
        $namesCount = count($names);
        if($limit > 0 && $offset < $namesCount) {
            $sql = 'SELECT * FROM bots WHERE name IN ('.implode(',', array_fill(1, $namesCount, '?')).') LIMIT ?,?';
            $query = $this->db->prepare($sql);
            foreach($names as $i => $n) {
                $query->bindValue($i + 1, $n, PDO::PARAM_STR);
            }
            $this->doPagination($query, $offset, $limit, $namesCount + 1, $namesCount + 2);
            $query->execute();

            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBotsByType($type, $offset, $limit)
    {
        $limit = isset($limit) ? $limit : $this->pageCount;
        if($limit > 0 && $offset < $this->getBotCount($type)) {
            $sql = "SELECT * FROM bots WHERE type=:type LIMIT :start,:stop";
            $query = $this->db->prepare($sql);
            $this->doPagination($query, $offset, $limit);
            $query->bindValue(":type", $type, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll();
        }
        else {
            return array();
        }
    }

    public function getBot($name)
    {
        $sql = "SELECT * FROM bots WHERE name=?";
        $query = $this->db->prepare($sql);
        $query->execute(array($name));

        return $query->fetch();
    }

    public function getType($id)
    {
        $sql = "SELECT * FROM types WHERE id=?";
        $query = $this->db->prepare($sql);
        $query->bindValue(1, $id, PDO::PARAM_INT);
        $query->execute();

        return $query->fetch();
    }

    public function getAllTypes()
    {
        $sql = "SELECT * FROM types ORDER BY name ASC";
        $query = $this->db->prepare($sql);
        $query->execute();

        return $query->fetchAll();
    }
}
