<?php
namespace DB;
final class MySQLi {
	private $connection;

	public function __construct($hostname, $username, $password, $database, $port = '3306') {
	    
		$this->connection = new \mysqli($hostname, $username, $password, $database, $port);

		if ($this->connection->connect_error) {
			throw new \Exception('Error: ' . $this->connection->error . '<br />Error No: ' . $this->connection->errno);
		}

		$this->connection->set_charset("utf8");
		$this->connection->query("SET SQL_MODE = ''");
	}

public function query($sql) {
    // --- timing start ---
    $t0 = microtime(true);

    $query = $this->connection->query($sql);

    // --- timing end + logging ---
    $dur = microtime(true) - $t0;                 // seconds
    $ms  = number_format($dur * 1000, 1);         // ms for log
    $route = isset($_GET['route']) ? $_GET['route'] : 'common/home';
    $rows  = ($query instanceof \mysqli_result) ? $query->num_rows : 0;

    // compact SQL in logs (avoid huge lines)
    $sql_for_log = $sql;
    if (strlen($sql_for_log) > 2000) {
        $sql_for_log = substr($sql_for_log, 0, 2000) . ' ...[truncated]';
    }

    if ($this->connection->errno) {
        // DB error
        error_log("[DB ERR] {$ms} ms | errno={$this->connection->errno} | route={$route} | {$sql_for_log}");
        throw new \Exception('Error: ' . $this->connection->error  . '<br />Error No: ' . $this->connection->errno . '<br />' . $sql);
    } else {
        // normal / slow logging per toggles
        if ((defined('DB_LOG_ALL') && DB_LOG_ALL) ||
            (defined('DB_SLOW_THRESHOLD') && $dur >= DB_SLOW_THRESHOLD)) {
            $tag = (defined('DB_SLOW_THRESHOLD') && $dur >= DB_SLOW_THRESHOLD) ? 'DB SLOW' : 'DB';
            error_log("[{$tag}] {$ms} ms | rows={$rows} | route={$route} | {$sql_for_log}");
        }
    }

    if ($query instanceof \mysqli_result) {
        $data = [];
        while ($row = $query->fetch_assoc()) {
            $data[] = $row;
        }
        $result = new \stdClass();
        $result->num_rows = $query->num_rows;
        $result->row  = isset($data[0]) ? $data[0] : [];
        $result->rows = $data;
        $query->close();
        return $result;
    } else {
        return true;
    }
}


	public function escape($value) {
		return $this->connection->real_escape_string($value);
	}
	
	public function countAffected() {
		return $this->connection->affected_rows;
	}

	public function getLastId() {
		return $this->connection->insert_id;
	}
	
	public function connected() {
		return $this->connection->ping();
	}
	
	public function __destruct() {
		$this->connection->close();
	}
}
