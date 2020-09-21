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
		if (!empty($this->imageMaxSizes)) {
			$this->imageMaxSizes['max'] = '';
			$this->imageMaxSizes['source'] = '';
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
		// usage is as follows --> insert('table', ['column0' => 'value0', column1 => value1], ['col1=val1', 'col2=val2'], ['OR'])
		// $condition will default to AND
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

	public function insert(string $table, array $objects) {
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

	public function delete(string $table, array $where, $condition = null) {
		// DELETE FROM using prepared statements
		// usage is as follows --> delete('table', ['col1=val1', 'col2=val2'], ['OR'])
		// $condition will default to AND
		// Returns true on success
		$values = [];
		$executeParams = [];

		$command = "DELETE FROM $table";

		$i = 0;

		$getWheres = $this->get_command_wheres($where, $condition);
		$command = $command.$getWheres[0];
		foreach ($getWheres[1] as $key => $value) {
			$executeParams[$key] = $value;
		}

		try {
			$stmt = $this->conn->prepare($command);
			$stmt->execute($executeParams);
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
			//echo($error); // For testing purposes. Will echo unparsed error to browser console!!!
		} else {
			$this->dbError = 'other';
			//print_r($error); // For testing purposes. Will echo unparsed error to browser console!!!
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

	/* == POST / PAGE FUNCTIONS == */
	public function get_content_data($urlTitle) {
		$imgSrcs;
		$images;
		$sizes = $this->imageMaxSizes;
		$path = $this->home['server'].'images/'.$urlTitle.'/';
		$urlPath = $this->home['url'].'images/'.$urlTitle.'/';
		$files = glob($path.'*');

		$content = $this->select('content', 'posts', 'urlTitle='.$urlTitle)[0]['content'];
		if (!$content) {
			$content = $this->select('content', 'posts', 'urlTitle='.$urlTitle)[0]['content'];
		}

		preg_match_all('/<img src="image-\d?"/', $content, $imgSrcs);
		foreach ($imgSrcs[0] as $value) {
			$value1 = trim(substr($value, 9), '"');
			$image;
			foreach($sizes as $key => $value2) {
				$image[$key] = (glob($path.$value1.'_'.$key.'.*')[0] ?? '');
			}
			/*
			$image['max'] = (glob($path.$value1.'_max'.'.*')[0] ?? '');
			$image['source'] = (glob($path.$value1.'_source'.'.*')[0] ?? '');
			*/
			$images[$value1] = $image;
		}

		foreach($images as $key1 => $value1){
			foreach($value1 as $key2 => $value2) {
				$newValue2 = str_replace($path, $urlPath, $value2);
				$images[$key1][$key2] = $newValue2;
			}
		}

		return [$images, $imgSrcs[0], $content];
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
				$userInfo = $this->get_user_info($user[0]['username']);
				$this->set_session_cookie($userInfo);
				$this->set_auth_cookie($userInfo); 
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
		$userInfo = $this->select(['authToken', 'authSelector'], 'users', 'username='.$username);
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
		$string = trim($string, '-');
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

	public static function dump($val, $title = false) {
		echo '<pre style="background-color: black; color: ivory; padding: 1rem; border-radius: 7px"><h2>'.($title ?: 'DUMP').':</h2>';
		var_dump($val, true);
		echo '</pre>';
	}
}

class KitkuImage {
	public $error = false;
	private $allowedTypes = ['image/png', 'image/gif', 'image/jpg', 'image/jpeg'];

	private $image;

	private $width;
	private $height;
	private $mime;
	private $rotation;
	private $orientation; // 0 = square, 1 = landscape, 2 = portrait
	private $aspectRatio; // stored as float. 1.6 = 1280x800 = 16:10

	private $path;
	private $ext;
	private $sourceImagePath;

	private $animated;

	public function __destruct() {
		if (!empty($this->tempFilename) && file_exists($this->tempFilename)) {
			unlink($this->tempFilename);
		}
	}

	public function __construct(string $data, string $targetPath, string $filename, string $type = 'path') {
		try {
			if ($type == 'base64') {
				$base64 = explode(',', $data)[1];
				$this->tempFilename = $targetPath.'temp-'.time().'-'.rand(100, 999);
				file_put_contents($this->tempFilename, base64_decode($base64));
			} else {
				$this->tempFilename = $data;
			}
	
			$imageData = getimagesize($this->tempFilename);
			$this->set_image_data($imageData);
	
			$this->image = $this->imagecreate($this->tempFilename, $this->mime);
			
			$this->rotation = $this->get_rotation($this->tempFilename);
			$this->orientation = $this->get_orientation($this->width, $this->height, $this->rotation);
			$this->aspectRatio = $this->get_aspect_ratio($this->width, $this->height);
	
			$this->ext = '.'.substr($this->mime, 6);
			$this->path = $targetPath.$filename;
			$this->sourceImagePath = $this->path.'_source'.$this->ext;
	
			$this->mime_allowed();
	
			if ($this->rotation !== 0) {
				if (abs($this->rotation) === 90) {
					$tempW = $this->width;
					$this->width = $this->height;
					$this->height = $tempW; 
				}
				$this->image = imagerotate($this->image, $this->rotation, 0);
			}
	
			$this->animated = $this->is_animated() ? true : false;
		} catch (Exception $e) {
			$this->error = $e->getMessage();
		}
	}

	public function save_source() {
		if (!$this->error) {
			if ($this->animated) {
				$this->save_animated();
			} else {
				copy($this->tempFilename, $this->sourceImagePath);
			}
		} else {
			return false;
		}
	}

	public function save_max() {
		if (!$this->error) {
			if ($this->animated) {
				$this->save_animated();
			} else {
				$this->save_as($this->image, $this->path.'_max'.$this->ext);
				return true;
			}
		} else {
			return false;
		}
	}

	public function save_reduced(array $sizes) {
		// Receives an array of target sizes to save the images as.
		// example ----> 'key' => ['max-x', 'max-y']

		if (array_key_exists('max', $sizes)) {
			unset($sizes['max']);
		}
		if (array_key_exists('source', $sizes)) {
			unset($sizes['source']);
		}

		if (!$this->error) {
			if ($this->animated) {
				$this->save_animated();
			} else {
				foreach($sizes as $size => $xy) {
					$x = $xy[0];
					$y = $xy[1];

					if ($this->width >= $x || $this->height >= $y){
						if ($this->orientation < 2) { // if not portrait, x priority
							if ($this->width > $x) {
								$newWidth = $x;
								$newHeight = round($x / $this->aspectRatio);
							} else if ($this->height >= $y) {
								$newWidth = round($y / $this->aspectRatio);
								$newHeight = $y;
							}
						} else { // portrait, y priority
							if ($this->height >= $y) {
								$newWidth = round($y / $this->aspectRatio);
								$newHeight = $y;
							} else if ($this->width > $x) {
								$newWidth = $x;
								$newHeight = round($x / $this->aspectRatio);
							}
						}
						$newImage = imagecreatetruecolor($newWidth, $newHeight);
						imagecopyresampled($newImage, $this->image, 0, 0, 0, 0, $newWidth, $newHeight, $this->width, $this->height);
						
						$this->save_as($newImage, $this->path.'_'.$size.$this->ext);
					}
				}
			}
		} else {
			return false;
		}
	}

	public function save(array $sizes) {
		if (!$this->error) {
			if ($this->animated) {
				$this->save_source();
			} else {
				$this->save_reduced($sizes);
				$this->save_max();
				$this->save_source();
			}
		} else {
			return false;
		}
	}

	private function save_as($image, $target, $quality = -1) {
		if ($this->mime == 'image/png' || $this->mime == 'image/gif'){
			imagealphablending($image, true);
			imagesavealpha($image, true);
		}
		header('Content-Type: '.$this->mime);
		$this->imagefrom($image, $target, $quality);
	}

	private function save_animated() {
		// Manipulating animated gifs may come at a future date. For now save them exactly as uploaded.
		if (!file_exists ($this->sourceImagePath)) {
			copy($this->tempFilename, $this->sourceImagePath);
		}
	}

	private function imagefrom($image, $targetPath, int $quality = -1) {
		switch (substr($this->mime, 6)) {
			case 'jpeg':
			case 'jpg':
				return imagejpeg($image, $targetPath, $quality);
			break;
			case 'png':
				return imagepng($image, $targetPath);
			break;
			case 'gif':
				return imagegif($image, $targetPath);
			break;
		}
	}

	private function set_image_data($imageData) {
		$this->width = $imageData[0];
		$this->height = $imageData[1];
		$this->mime = $imageData['mime'];
	}

	private function imagecreate($path, $mime) {
		switch (substr($this->mime, 6)) {
			case 'jpeg':
			case 'jpg':
				return imagecreatefromjpeg($path);
			break;
			case 'png':
				return imagecreatefrompng($path);
			break;
			case 'gif':
				return imagecreatefromgif($path);
			break;
		}
	}

	private function get_rotation($imagePath) {
		// Gets the rotation of the image set by it's exif data
		// Returns degree of rotaion.
		// deafaults to 0 if no exif data is found.
		$aspect = 0;
		if ($exif = @exif_read_data($imagePath)) {
			if ($exif && !empty($exif['Orientation'])) {
				switch ($exif['Orientation']) {
					case 3:
						$aspect = 180;
						break;
		  
					case 6:
						$aspect = -90;
						break;
		  
					case 8:
						$aspect = 90;
						break;
				}
			}
		}
		return $aspect;
	}

	private function get_orientation($width, $height, $rotation = 0) {
		if ($width === $height) {
			return 0;
		}
		$landscape = ($width > $height) ? true : false;
		$landscape = (abs($rotation) === 90) ? !$landscape : $landscape;

		return ($landscape) ? 1 : 2;
	}	

	private function get_aspect_ratio($width, $height) {
		if ($width > $height) {
			$big = $width;
			$small = $height;
		} else {
			$big = $height;
			$small = $width;
		}
		return round($big/$small, 6);
	}

	private function mime_allowed() {
		if (!in_array($this->mime, $this->allowedTypes)) {
			throw new Exception('Mime type not allowed');
		}
	}

	function is_animated() {
		$filecontents = file_get_contents($this->tempFilename);

		$str_loc = 0;
		$count = 0;
		while ($count < 2) {
			$where1=strpos($filecontents,"\x00\x21\xF9\x04",$str_loc);
			if ($where1 === FALSE) {
				break;
			} else {
				$str_loc=$where1+1;
				$where2=strpos($filecontents,"\x00\x2C",$str_loc);
				if ($where2 === FALSE) {
					break;
				} else {
				if ($where1+8 == $where2) {
					$count++;
				}
				$str_loc=$where2+1;
				}
			}
		}

		if ($count > 1) {
		return(true);

		} else {
			return(false);
		}
	}

	public function get_error() {
		return $this->error;
	}
}

?>