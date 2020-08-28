<?php

	class Kitku {
		public $version = "0.0.0";
		public $conn;
		public $dbError;
		public $siteName;
		protected $dbInfo = [];
		protected $currentPath;
		protected $hasConfig;

		function __construct() {
			$this->currentPath = __DIR__.'/';
			$this->hasConfig = ($this->get_config() ? true : false);
			if (!empty($this->dbInfo)) {
				$this->open_conn($this->dbInfo['server'], $this->dbInfo['username'], $this->dbInfo['password'], (!empty($this->dbInfo['database'])) ? $this->dbInfo['database'] : '');
			}
		}

		protected function get_config() {
			if (file_exists($this->currentPath.'config.json')) {
				$vars = json_decode(file_get_contents($this->currentPath.'config.json'), true);
				foreach ($vars as $key => $value) {
					$this->$key = $value;
				}
				return true;
			}
			return false;
		}
		
		/* -- DATABASE FUNCTIONS --*/
		public function close_conn() {
			if ($this->conn) {
				return ($this->conn->close()) ? true : false;
			}
		}

		public function open_conn($server = false, $user = false, $password = false, $database = false) {
			$this->close_conn();

			$this->conn = new mysqli(
				($server) ? $server : $this->dbInfo['server'], 
				($user) ? $user : $this->dbInfo['user'], 
				($password) ? $password : (!empty($this->dbInfo['password']) ? $this->dbInfo['password'] : ''), 
				($database) ? $database : (!empty($this->dbInfo['database']) ? $this->dbInfo['database'] : '')
			);

			if ($this->conn->connect_error) {
				$this->dbError = $this->parse_errors($this->conn->connect_error);
				return false;
			} else {
				$this->dbError = false;
				return true;
			}
		}

		public function get($table, $select, $where = false) {
			$result = $this->conn->query("SELECT $select FROM $table".($where ? $where : ''));
			return mysqli_fetch_all($result, MYSQLI_ASSOC);		
		}

		public function insert($table, $objects) {
			$keys = '';
			$values = [];
			$binder = '';
			$placeholder = '';
			foreach ($objects as $key => $value) {
				$keys = $keys.$key.', ';
				array_push($values, $value);
				$binder = $binder.(is_numeric($value) ? (is_float($value)) ? 'd' : 'i' : 's');
				$placeholder = $placeholder.'?, ';
			}
			$keys = trim($keys, ', ');
			$placeholder = trim($placeholder, ', ');
			$command = "INSERT INTO $table ( $keys ) VALUES ( $placeholder )";
			$stmt = $this->conn->prepare($command);
			$stmt->bind_param($binder, ...$values);
			if ($stmt->execute()) {
				return true;
			} else {
				$this->dbError = $this->conn->error;
			}
		}

		protected function parse_errors($error) {
			if (strpos($error, 'No such host') !== false) {
				return 'noHost';
			} else if (strpos($error, 'Access denied') !== false) {
				return 'badCred';
			} else if (strpos($error, 'Unknown database') !== false) {
				return 'noDB';
			} else {
				//return $error; For testing purposes. Will echo unparsed error to console!
				return 'other';
			}
		}
	}

	class KitkuInstaller extends Kitku {
		function __construct() {
			parent::__construct();
			if (!$this->hasConfig) {
				$this->siteName = 'NewKitkuSite';
				$this->installed = 0;
				$this->set_home();
				$this->set_config();
			}
		}

		public function set_config() {
			$config = [
				'siteName' => $this->siteName,
				'installed' => $this->installed,
				'dbInfo' => $this->dbInfo,
				'home' => $this->home
			];
			if (file_put_contents($this->currentPath.'config.json', json_encode($config, JSON_PRETTY_PRINT))) {
				$this->get_config();
				return true;
			} else {
				return false;
			}
		}

		private function set_home() {
			$filename = [];
			preg_match('/(?=\w+\.\w{3,4}$).+/', $_SERVER['SCRIPT_FILENAME'], $filename);
			$this->home = [
				'url' => $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].str_replace('index.php', '', $_SERVER['PHP_SELF']),
				'installUrl' => $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].str_replace('index.php', '', $_SERVER['PHP_SELF']).'kitku/',
				'server' => str_replace($filename[0], '', $_SERVER['SCRIPT_FILENAME']),
				'installServer' => $this->currentPath
			];
		}

		public function set_dbInfo($dbInfo) {
			$this->dbInfo = $dbInfo;
		}

		public function create_database($name, $connectAfterCreate = false) {
			$result = ($this->conn->query("CREATE DATABASE $name")) ? true : false;
			if ($result && $connectAfterCreate) {
				$this->dbInfo['database'] = $name;
				$this->close_conn();
				$this->open_conn();
			} else {
				return $result;
			}
			return $result;
		}

		public function create_table($name, $columns = false) {
			$command = "CREATE TABLE $name(id INT(6) PRIMARY KEY AUTO_INCREMENT)";
			if (!$this->try_command($command)) { 
				return false; 
			}
			if ($columns) {
				if (!$this->alter_table($name, $columns)){
					return false;
				}
			}
			return true;
		}

		public function alter_table($name, $columns) {
			foreach ($columns as $colName => $type) {
				$command = "ALTER TABLE $name ADD $colName $type";
				if (!$this->try_command($command)) { 
					return false; 
				}
			}
			return true;
		}

		private function try_command($try) {
			if ($this->conn->query($try)) {
				return true;
			} else {
				$this->dbError = $this->parse_errors($this->conn->error);
				return false;
			}
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