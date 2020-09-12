<?php

session_start();

class Kitku {
	public $version = '0.0.0';
	public $conn;
	public $dbError;
	public $siteName;
	protected $dbInfo = [];
	protected $currentPath;
	private $authCookieDuration = (7 * 24 * 60 * 60); // one week from now

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

	/* == CONFIG FUNCTIONS == */
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

	/* == DATABASE FUNCTIONS == */
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

	public function select($select, string $table, $where = null, $condition = null) {
		// SELECT using prepared statements
		// single values may be a string. multiple values passed as an array.
		// select('selection', 'table') 
		// becomes --> SELECT selection FROM table
		// select(['selection', 'selector'], 'table', ['column=value', 'otherCol<otherVal', 'thirdCol>=thirdVal'], ['OR', 'OR'])
		// becomes --> SELECT selection, selector FROM table WHERE column=value OR otherCol<otherVal OR thirdCol>=thirdVal
		// $condition will default to AND
		// Returns an associative array

		$select = (is_array($select) ? implode(', ', $select) : $select);
		$command = "SELECT $select FROM $table";

		if (!empty($where)) {
			$getWheres = $this->get_command_wheres($where, $condition);
			$command = $command.$getWheres[0];
			$executeParams = $getWheres[1];
		} else {
			$executeParams = [];
		}

		try {
			$stmt = $this->conn->prepare($command);
			$stmt->execute($executeParams);
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (\PDOException $e) {
			$this->parse_errors($e->getMessage());
			return false;
		}
	}

	public function update(string $table, array $objects, $where, $condition = null) {
		// UPDATE using prepared statements
		// usage is as follows --> insert('table', ['column0' => 'value0', column1 => value1])
		// Returns true on success
		$values = [];
		$executeParams = [];

		$command = "UPDATE $table SET";

		$i = 0;
		foreach ($objects as $key => $object) {
			$command = $command . ' ' . $key . ' = :object' . $i . (($i+1 < count($objects)) ? ',' : '' );
			$executeParams[':object'.$i] = $object;
			$i++;
		}

		$getWheres = $this->get_command_wheres($where, $condition);
		$command = $command.$getWheres[0];
		foreach ($getWheres[1] as $key => $value) {
			$executeParams[$key] = $value;
		}

		try {
			$stmt = $this->conn->prepare($command);
			$stmt->execute($executeParams);
			return (($stmt->rowCount() === 0) ? false : true);
		} catch (\PDOException $e) {
			$this->parse_errors($e->getMessage());
			return false;
		}
	}

	public function insert($table, assoc $objects) {
		// INSERT using prepared statements
		// usage --> insert('table', ['column0' => 'value0', column1 => value1])
		// Returns true on success
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

	protected function get_command_wheres($where, $condition) {
		$operators = ['>','<','>=','<=','<>','=']; // '=' must be after '<=' and '>='

		$condition = (is_string($condition) ? [ $condition ] : $condition);
		$where = (is_string($where) ? [ $where ] : $where);

		$executeParams = [];
		$command = ' WHERE ';
			
		for ($i = 0; $i < count($where); $i++) {
			foreach ($operators as $checkOperator) {
				if (strpos($where[$i], $checkOperator) !== false) {
					$operator = $checkOperator;
					$operatorPos = strpos($where[$i], $checkOperator);
				}
			}
			$valueSplit = explode($operator, $where[$i]);
			$wheres[$i]['column'] = $valueSplit[0];
			$wheres[$i]['operator'] = $operator;
			$wheres[$i]['value'] = $valueSplit[1];
		}

		for ($i = 0; $i<count($wheres); $i++) {
			$executeParams += [
				':value'.$i => $wheres[$i]['value']
			];
			$command = $command.$wheres[$i]['column'].$wheres[$i]['operator']." :value$i";
			if (($i+1) != count($wheres)) {
				$command = $command.($condition[$i] ? ' '.$condition[$i].' ' : ' AND ');
			}
		}

		return [$command, $executeParams];
	}

	/* == USER FUNCTIONS == */
	public function get_user_info($username) {
		return ($this->select('*', 'users', 'username='.$username))[0];
	}

	/* == LOGIN FUNCTIONS == */
	public function login($username, $password, $remember = false, $source = false) {
		if ($this->check_password($username, $password)) {
			$userInfo = $this->get_user_info($username);
			$this->set_session_cookie($userInfo);
			if ($remember) {
				$this->set_auth_cookie($userInfo); 
			}
			return 'success';
		} else {
			return 'badCred';
		}
		return false;
	}

	public function logout($username = false, $allDevices = false, $source = false) {
		try {
			$username = $username ?? $_SESSION['username'];
			$this->destroy_cookies();
			if ($allDevices) {
				$this->destroy_auth_tokens($username);
			}
			return true;
		} catch (execption $e) {
			return false;
		}
	}

	public function check_login() {
		if (!empty($_SESSION['loggedIn']) && $_SESSION['loggedIn'] === true) {
			$this->update_auth_expiry($_SESSION['user']);
			return true;
		} else {
			if (!empty($_COOKIE['auth'])) {
				$split = explode(':', $_COOKIE['auth']);
				$user = $this->select('*', 'users', 'authSelector='.$split[1]);
				if ($user[0]['authExpires'] < time() || !$split[0] || !$split[1]) {
					$this->destroy_cookies();
					return false;
				}
				$this->login($user[0]['username'], $user[0]['password'], true, false);
				return true;
			}		
		}
		return false;
	}

	public function demand_login($source = false) {
		header("Location: ".$this->home['installUrl'].'login.php'.($source ? '?source='.$source : ''));
	}

	private function check_password($username, $password) {
		if ($rows = $this->select('password', 'users','username='.$username)) {
			$userInfo = $rows[0];
			$hash = $userInfo['password'];
			if (password_verify($password, $hash)) {
				return true;
			}
		}
		return false;
	}

	protected function set_session_cookie($userInfo) {
		session_regenerate_id();
		$_SESSION['loggedIn'] = true;
		$_SESSION['userID'] = $userInfo['id'];
		$_SESSION['user'] = $userInfo['username'];
		$_SESSION['power'] = $userInfo['power'];
	}

	private function set_auth_cookie($userInfo) {
		// Will need to check for active auth cookie so user can be remembered accross multiple devices.
		$randomPassword = $this->random_string(50);
		$randomSelector = $this->random_string(7).time();
		$token = password_hash($randomPassword, PASSWORD_DEFAULT);
		$expire = time() + $this->authCookieDuration;
		$this->update('users', ['authToken' => $randomPassword, 'authSelector' => $randomSelector, 'authExpires' => $expire], 'username='.$userInfo['username']);
		setcookie('auth', $token.':'.$randomSelector, $expire, '/', NULL, NULL, true);
	}

	private function destroy_cookies($auth = true, $session = true) {
		if (!empty($_COOKIE['auth']) && $auth) {
			setcookie('auth', '', time()-3600, '/');
		}
		if ($session) {
			session_destroy();
		}
	}

	private function destroy_auth_tokens($username) {
		$this->update('users', ['authToken' => '', 'authSelector' => '', 'authExpires' => 0], 'username='.$userInfo['username']);
		$this->destroy_cookies(null, false);
	}

	private function update_auth_expiry($username) {
		$expire = time() + $this->authCookieDuration;
		$this->update('users', ['authExpires' => $expire], 'username='.$username);
		$userInfo = $this->select('authToken, authSelector', 'users', 'username='.$username);
		setcookie('auth', $userInfo[0]['authToken'].':'.$userInfo[0]['authSelector'], $expire, '/');
	}

	/* == REDIRECTS == */
	public function redirect_url($suffix = false) {
		header("Location: ".$this->home['url'].($suffix ?: ''));
		exit();
	}

	/* == HELPER FUNCTIONS == */
	public function strip_special_chars(string $string) {
		$string = preg_replace('/[^\p{L}\p{N}\s]/u', '', $string);
		$string = str_replace(' ', '-', $string);
		$string = strtolower($string);
		return $string;
	}

	public function random_string($length) {  
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

	public function get_client_ip() {
		$ip = getenv('HTTP_CLIENT_IP')?:
		getenv('HTTP_X_FORWARDED_FOR')?:
		getenv('HTTP_X_FORWARDED')?:
		getenv('HTTP_FORWARDED_FOR')?:
		getenv('HTTP_FORWARDED')?:
		getenv('REMOTE_ADDR');

		return $ip;
	}

	public function dump($var) {
		echo '<br /><pre style="color: black; background-color: white;">';
		var_dump($var);
		echo '</pre><br />';
	}
}

?>