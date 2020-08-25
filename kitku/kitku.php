<?php
	$kitku = new Kitku();

	if (empty(!$kitku->firstRun)) {
		require_once $kitku->homeServer.'install.php';
	}

	class Kitku {
		public $conn;
		public $dbError;
		public $dbInfo = array();

		function __construct() {
			$this->get_config();
		}

		private function get_config() {
			if (file_exists('config.json')) {
				$vars = json_decode(file_get_contents('config.json'), true);
				foreach ($vars as $key => $value) {
					$this->$key = $value;
				}
				return true;
			} else {
				$this->firstRun = true;
				$this->set_home();
				$this->set_config();
				return false;
			}
		}

		/* -- INIT FUNCTIONS -- */
		public function set_config() {
			$config = [
				'firstRun' => $this->firstRun,
				'homeUrl' => $this->homeUrl,
				'homeServer' => $this->homeServer,
				'dbInfo' => $this->dbInfo
			];
			if (file_put_contents('config.json', json_encode($config))) {
				$this->get_config();
				return true;
			} else {
				return false;
			}
		}

		private function set_home() {
			$this->homeUrl = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].str_replace('kitku.php', '', $_SERVER['PHP_SELF']);
			$this->homeServer = str_replace('kitku.php', '', $_SERVER['SCRIPT_FILENAME']);
		}

		/* -- DATABASE FUNCTIONS --*/
		private function parse_errors($error) {
			if (strpos($error, 'No such host') !== false) {
				return 'noHost';
			} else if (strpos($error, 'Access denied') !== false) {
				return 'badCred';
			} else if (strpos($error, 'Unknown database') !== false) {
				return 'noDB';
			} else {
				return $error;
			}
		}

		public function close_conn() {
			return ($this->conn->close()) ? true : false;
		}

		public function open_conn($server = false, $user = false, $password = false, $database = false) {

			$this->conn = new mysqli(
				($server) ? $server : $this->dbInfo['server'], 
				($user) ? $user : $this->dbInfo['user'], 
				($password) ? $password : $this->dbInfo['password'], 
				($database) ? $database : ($this->dbInfo['database'] ? $this->dbInfo['database'] : '')
			);

			if ($this->conn->connect_error) {
				$this->dbError = $this->parse_errors($this->conn->connect_error);
				return false;
			} else {
				$this->dbError = false;
				return true;
			}
		}

		public function create_database($name, $connectAfterCreate = false) {
			$result = ($this->conn->query("CREATE DATABASE $name")) ? true : false;
			if ($result && $connectAfterCreate) {
				$this->dbInfo['database'] = $name;
				$this->close_conn();
				$this->open_conn();
				if ($this->conn->connect_error) {
					echo "ERROR: " . $this->conn->connect_error;
				}
			} else {
				return $result;
			}
			return $result;
		}

		public function drop_database($name) {
			return ($this->conn->query("DROP DATABASE $name")) ? true : false;
		}

		public function create_table() {
			$command = "CREATE TABLE tebet(id INT(6) PRIMARY KEY AUTO_INCREMENT)";
			return ($this->conn->query($command)) ? true : false;
		}

		/* -- HELPER FUNCTIONS -- */
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
			echo '<br /><p>';
			var_dump($var);
			echo '</p><br />';
		}
	}

?>