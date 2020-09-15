<?php

require_once 'kitku.php';

class KitkuInstaller extends Kitku {
	function __construct() {
		parent::__construct();
		if (!$this->get_config()) {
			$this->set_home();
			$imageMaxSizes =[
				"small" => ["640", "320"],
				"medium" => ["1440", "720"],
				"large" => ["2048", "1024"],
				"extraLarge" => ["3840", "1920"]
			];
			$defaultConfig = [
				// Default entries for config.json
				'siteName' => 'NewKitkuSite',
				'installed' => 0,
				'dbInfo' => '',
				'home' => $this->home,
				'imageMaxSizes' => $imageMaxSizes,
				'configIgnores' => ['conn', 'dbError', 'currentPath'], // Object variables NOT stored in config.json
				'buildTableIgnores' => ['id', 'content'], // Columns ignored by build_table() in admin javascript
				'buildTableToggles' => ['blogPage', 'showInMenu'] // Columns displayed as a toggle by build_table() in admin javascript
			];
			$this->set_config(true, $defaultConfig);
		}
	}

	function __destruct() {
		parent::__destruct();
	}

	private function set_home() {
		// Creates an array of both url and server paths of the current install
		$filename = [];
		$regex = "([^\\\/]+$)"; // Match last backslash or forward slash.
		preg_match($regex, $_SERVER['SCRIPT_FILENAME'], $filename);
		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].str_replace($filename[0], '', $_SERVER['PHP_SELF']);
		$installFolder = [];
		preg_match($regex, substr($this->currentPath, 0, -1), $installFolder);
		$this->home = [
			'url' => $url,
			'installUrl' => $url.$installFolder[0].'/',
			'server' => str_replace($filename[0], '', $_SERVER['SCRIPT_FILENAME']),
			'installServer' => $this->currentPath
		];
	}

	public function set_dbInfo(array $dbInfo) {
		$this->dbInfo = $dbInfo;
	}

	public function create_database($name, $connectAfterCreate = false) {
		try {
			$this->conn->exec("CREATE DATABASE $name");
			if ($connectAfterCreate) {
				$this->dbInfo['database'] = $name;
				$this->close_conn();
				$this->open_conn();
			}
			return true;
		} catch (\PDOException $e) {
			$this->parse_errors($e->getMessage());
			return false;
		}
	}

	public function create_table($name, $columns = false) {
		$command = "CREATE TABLE $name(id INT(6) PRIMARY KEY AUTO_INCREMENT)";
		if (!$this->do_command($command)) { 
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
			if (!$this->do_command($command)) { 
				return false; 
			}
		}
		return true;
	}

	private function do_command($try) {
		if ($this->conn->query($try)) {
			return true;
		} else {
			$this->parse_errors($this->conn->errorInfo());
			return false;
		}
	}

	public function first_login() {
		$userInfo = ($this->select('*', 'users', 'id=1'))[0];
		$this->set_session_cookie($userInfo);
		return 'success';
	}
}

$kitku = new KitkuInstaller;

// If already installed, return home.
if (isset($kitku->installed) && $kitku->installed === true) {
	header("Location: ".$kitku->home['url']);
	exit();
}

if (!empty($_POST)) {
	switch ($_POST['page']) {
		case 0:
		// Page 0 formdata collects database login information.
		// Check if user provided data allows for server connection.
			$dbInfo = [
				'server' => $_POST['database-servername'], 
				'username' => $_POST['database-username'], 
				'password' => $_POST['database-password'],
				'database' => 'kitku_'.$kitku->random_string(5)
			];

			if ($kitku->open_conn($dbInfo['server'], $dbInfo['username'], $dbInfo['password'])) {
				$dbCreated = 0;
				while($dbCreated !== true) {
					if ($kitku->create_database($dbInfo['database'], false)) {
						$dbCreated = true;
						$kitku->set_dbInfo($dbInfo);
						$kitku->installed = 1;
						if ($kitku->set_config()) {
							exit('success');
						} else {
							exit('serveErr');
						}
					} else {
						$dbInfo['database'] = $kitku->random_string(5);
						$dbCreated++;
					}
					if ($dbCreated > 10) {
						exit('createDBFail');
					}
				}
			} else {
				exit($kitku->dbError);
			}
			break;

		case 1:
		// Page 1 collects admin user information
		// The following builds the database tables and sets them to default values
			$usersColumns = [
				'username' => 'VARCHAR(30) NOT NULL',
				'email' => 'VARCHAR(30)',
				'password' => 'VARCHAR(255) NOT NULL',
				'power' => 'VARCHAR(30) NOT NULL',
				'authToken' => 'VARCHAR(255) NOT NULL',
				'authSelector' => 'VARCHAR(50) NOT NULL',
				'authExpires' => 'INT(12) DEFAULT UNIX_TIMESTAMP() NOT NULL'
			];
			$postsColumns = [
				'title' => 'VARCHAR(80) NOT NULL',
				'urlTitle' => 'VARCHAR(255) NOT NULL',
				'author' => 'VARCHAR(255)',
				'category' => "VARCHAR(30) DEFAULT 'uncategorized' NOT NULL",
				'date' => 'INT(12) DEFAULT UNIX_TIMESTAMP() NOT NULL',
				'tags' => 'VARCHAR(255)',
				'views' => 'INT(12) DEFAULT 0 NOT NULL',
				'content' => 'LONGTEXT'
			];
			$pagesColumns = [
				'title' => 'VARCHAR(80) NOT NULL',
				'parent' => 'VARCHAR(80)',
				'views' => 'INT(12) NOT NULL DEFAULT 0',
				'content' => 'LONGTEXT NOT NULL',
				'blogPage' => 'BOOLEAN NOT NULL DEFAULT FALSE',
				'showInMenu' =>'BOOLEAN NOT NULL DEFAULT TRUE'
			];
			if (!$kitku->create_table('users', $usersColumns)) {
				exit('serveErr-1');
			}
			if (!$kitku->create_table('posts', $postsColumns)) {
				exit('serveErr-2');
			}
			if (!$kitku->create_table('pages', $pagesColumns)) {
				exit('serveErr-3');
			}

			$userInfo = [
				'username' => $_POST['username'],
				'email' => $_POST['email'],
				'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
				'power' => 'admin',
				'authToken' => '',
				'authSelector' => '',
				'authExpires' => '0'
			];

			$defaultPost = [
				'title' => 'My first Kitku post!',
				'urlTitle' => '',
				'author' => 'Kitku',
				'category' => 'uncategorized',
				'date' => time(),
				'tags' => 'first-post,default',
				'views' => '',
				'content' => 'This is a default post. You should probably delete it and replace with something more interesting...'
			];
			$defaultPost['urlTitle'] = $kitku->strip_special_chars($defaultPost['title']);
			
			$defaultBlog = [
				'title' => 'Blog',
				'parent' => '',
				'views' => '',
				'content' => 'This is the default blog page. Your posts will be collected and displayed here',
				'blogPage' => 1,
				'showInMenu' => 1
			];

			if (!$kitku->insert('posts', $defaultPost)) {
				echo $kitku->dbError;
				exit('sqlErr-1');
			}

			if (!$kitku->insert('pages', $defaultBlog)) {
				exit('sqlErr-2');
			}

			if ($kitku->insert('users', $userInfo)) {
				$kitku->installed = 2;
				if ($kitku->set_config()) {
					exit('success');
				}
			}
			exit('serveErr-0');
			break;
		case 2:
		// Page 2 gets the website name
		// The user is logged in before being redirected to the admin panel
			$kitku->siteName = $_POST['sitename'];
			$kitku->installed = true;
			if ($kitku->set_config()) {
				$kitku->first_login();
				exit('goHome');
			}
			exit('serveErr');
			break;
	}	
} else {

	include $kitku->home['installServer'].'res/header.php';

?>

<script>
	const installUrl = '<?= $kitku->home['installUrl'] ?>',
		homeUrl = '<?= $kitku->home['url'] ?>';

	let activePage = <?= (isset($kitku->installed) ? $kitku->installed : 0); ?>;
</script>

<body>
	<div id="installer">

		<div class="content-container">

			<div class="content-header">
				<img id="kitku-logo" width="64px" height="64px" src="<?= $kitku->home['installUrl'].'res/images/logo.png'?>" />
				<h1>Welcome to Kitku!</h1>
				<em>- Simple Content Management -</em>
			</div>

			<div class="content-body">

				<div class="paginate-page  page0  hidden">
					<p>If you need any help with setup, please click <a href="https://www.google.com">here.</a></p>
					<p>First, let's connect to your database.</p>

					<form id="page0-form" method="post" onsubmit="return formSubmit(event, activePage)">
						<div class="form-grid">
							<label for="database-servername">Servername:</label>
							<input name="database-servername" type="text" required="true">
							<label for="database-username">Username:</label>
							<input name="database-username" type="text" required="true">
							<label for="database-password">Password:</label>
							<input name="database-password" type="password">
							<input type="submit" id="page0-form-submit" class="hidden">
						</div>
					</form>

				</div>

				<div class="paginate-page  page1  hidden">
					<p>Now we need to set up an admin user. This will be used to login and make changes to your website.</p>

					<form id="page1-form" onsubmit="return formSubmit(event, activePage)">
						<div class="form-grid">
							<label for="username">Username:</label>
							<input name="username" type="text" required="true">
							<label for="email">Email:</label>
							<input name="email" type="email" placeholder="Not required, but very useful.">
							<label for="password">Password:</label>
							<input name="password" type="password" placeholder="Use a secure password!" required="true">
							<input type="submit" id="page1-form-submit" class="hidden">
						</div>
					</form>

				</div>

				<div class="paginate-page  page2  hidden">
					<p>Finally, what is your website called?</p>

					<form id="page2-form" onsubmit="return formSubmit(event, activePage)">
						<div class="form-grid">
							<label for="sitename">Site name:</label>
							<input name="sitename" type="text" required="true">
							<input type="submit" id="page2-form-submit" class="hidden">
						</div>
					</form>

				</div>

				<p class="hidden" id="message">Working on it</p>

			</div>

			<div class="content-footer">
				<label for="page0-form-submit" class="button  right  hidden [ paginate-button page0 ]" tabindex="0">Next</label>
				<label for="page1-form-submit" class="button  right  hidden [ paginate-button page1 ] " tabindex="0">Next</label>
				<label for="page2-form-submit" class="button  right  hidden  [ paginate-button page2 ]" tabindex="0">Complete</label>
				<button id="back-button" class="button  left  hidden" tabindex="0">Back</button>
			</div>

		</div>

	</div>

	<script src="<?= $kitku->home['installUrl'].'res/js/install.js' ?>"></script>
</body>
</html>
<?php } ?>