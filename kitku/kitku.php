<?php

class Kitku {
	public $version = '0.0.0';
	public $conn;
	public $dbError;
	public $siteName;
	protected $dbInfo = [];
	protected $currentPath;

	function __construct() {
		$this->currentPath = __DIR__.'/';
		$this->get_config();
		if (!empty($this->dbInfo)) {
			$this->open_conn($this->dbInfo['server'], $this->dbInfo['username'], $this->dbInfo['password'], (!empty($this->dbInfo['database'])) ? $this->dbInfo['database'] : '');
		}
	}

	function __destruct() {
		$this->close_conn();
	}

	protected function get_config() {
	// Grabs the config file and applies its values as object variables
	// Returns false if no config file is found
		if (file_exists($this->currentPath.'config.json')) {
			$vars = json_decode(file_get_contents($this->currentPath.'config.json'), true);
			foreach ($vars as $key => $value) {
				$this->$key = $value;
			}
			return true;
		}
		return false;
	}

	public function set_config($overwrite = false, $config = false) {
	// Saves object variables to config.json
	// Allows the config file to be manipulated by plugins and have their configuration made persistent
		if (!$overwrite) {
			$config = [];
			$ignores = $this->configIgnores;
			foreach ($this as $key => $value) {
				if (!in_array($key, $ignores)) {
					$config[$key] = $value;
				}
			}
		}
		if ($config) {
			if (file_put_contents($this->currentPath.'config.json', json_encode($config, JSON_PRETTY_PRINT))) {
				$this->get_config();
				return true;
			}
		}
		return false;
	}
	
	public function close_conn() {
		if ($this->conn) {
			return ($this->conn = NULL) ? true : false;
		}
	}

	public function open_conn($server = false, $user = false, $password = false, $database = false) {
		// Open database connection.
		// Using open_conn() with no arguments cannects using credentials in the config.json

		$this->close_conn();

		$dsn = 'mysql:host='.($server ? $server : $this->dbInfo['server']).';'.
			($database ? 'dbname='.$database.';' : '').
			('charset=utf8mb4');

		try {
			$this->conn = new PDO(
				$dsn,
				($user) ? $user : $this->dbInfo['user'], 
				($password) ? $password : (!empty($this->dbInfo['password']) ? $this->dbInfo['password'] : '')
			);
		} catch (\PDOException $e) {
			$this->parse_errors($e->getMessage());
			return false;
		}
		return true;
	}

	public function select($select, $table, ...$where) {
		// SELECT using prepared statements
		// usage is as follows --> select('selection', 'table', "column=value", "anotherColumn<42")
		// Accepts any operator in the $operators array
		// Returns an associative array

		$operators = ['> ','<','>=','<=','<>','='];
		$wheres = [];
		$executeParams = [];

		foreach ($where as $key => $value) {
			foreach ($operators as $checkOperator) {
				if (strpos($value, $checkOperator) !== false) {
					$operator = $checkOperator;
					$operatorPos = strpos($value, $checkOperator);
				}
			}
			$valueSplit = explode($operator, $value);
			$wheres[$key]['column'] = $valueSplit[0];
			$wheres[$key]['operator'] = $operator;
			$wheres[$key]['value'] = $valueSplit[1];
		}
		$sql = "SELECT $select FROM $table";
		if ($wheres) {
			$sql = $sql.' WHERE ';
			for ($i = 0; $i<count($wheres); $i++) {
				$executeParams += [
					':value'.$i => $wheres[$i]['value']
				];
				$sql = $sql.$wheres[$i]['column'].$wheres[$i]['operator']." :value$i";
				if (($i+1) != count($wheres)) {
					$sql = $sql.' AND ';
				}
			}
		}

		try {
			$stmt = $this->conn->prepare($sql);
			$stmt->execute($executeParams);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (\PDOException $e) {
			$this->parse_errors($e->getMessage());
			return false;
		}
	}

	public function insert($table, $objects) {
		// INSERT using prepared statements
		// $objects is an associative array
		// usage is as follows --> insert('table', ['column0' => 'value0', column1 => value1])
		// Returns true on success, otherwise false
		$keys = '';
		$values = [];
		$placeholder = '';
		foreach ($objects as $key => $value) {
			$keys = $keys.$key.', ';
			array_push($values, $value);
			$placeholder = $placeholder.'?, ';
		}
		$keys = trim($keys, ', ');
		$placeholder = trim($placeholder, ', ');
		$command = "INSERT INTO $table ( $keys ) VALUES ( $placeholder )";
		try {
			$stmt = $this->conn->prepare($command);
			$stmt->execute($values);
			return true;
		} catch (\PDOException $e) {
			$this->parse_errors($e->getMessage());
			return false;
		}
	}

	protected function parse_errors($error) {
		// simplify verbose errors
		if (is_string($error)) {
			if (strpos($error, 'No such host') !== false) {
				$this->dbError = 'noHost';
			} else if (strpos($error, 'Access denied') !== false) {
				$this->dbError = 'badCred';
			} else if (strpos($error, 'Unknown database') !== false) {
				$this->dbError = 'noDB';
			} else {
				$this->dbError = 'other';
			}
			// echo($error); // For testing purposes. Will echo unparsed error to browser console!!!
		} else {
			$this->dbError = 'other';
			// print_r($error); // For testing purposes. Will echo unparsed error to browser console!!!
		}
		return $this->dbError;
	}
}

class Auth extends Kitku {
	function __construct(...$args) {
		foreach ($args as $key0 => $values) {
			foreach ($values as $key1 => $value) {
				$this->$key1 = $value;
			}
		}
	}
}

/* -- HELPER FUNCTIONS -- */
function random_string($length) {  
	// Use only url parameter safe characters!
	$charset = "0123456789abcdefghijklmnopqrstuvwxyz";
	$size = strlen($charset);  
	$randomString = '';
	for($i = 0; $i < $length; $i++) {  
		$str= $charset[ rand(0, $size - 1) ];  
		$randomString = $randomString.$str;
	}
	return $randomString;
}  

function get_client_ip() {
	$ip = getenv('HTTP_CLIENT_IP')?:
	getenv('HTTP_X_FORWARDED_FOR')?:
	getenv('HTTP_X_FORWARDED')?:
	getenv('HTTP_FORWARDED_FOR')?:
	getenv('HTTP_FORWARDED')?:
	getenv('REMOTE_ADDR');

	return $ip;
}

function dump($var) {
	echo '<br /><pre style="color: black; background-color: white;">';
	var_dump($var);
	echo '</pre><br />';
}
?>