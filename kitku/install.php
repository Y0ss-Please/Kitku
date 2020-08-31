<?php

require_once 'kitku.php';

class KitkuInstaller extends Kitku {
	function __construct() {
		parent::__construct();
		if (!$this->get_config()) {
			$this->set_home();
			$defaultConfig = [
				'siteName' => 'NewKitkuSite',
				'installed' => 0,
				'dbInfo' => '',
				'home' => $this->home,
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

	public function set_dbInfo($dbInfo) {
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
			$this->parse_errors($this->conn->errorInfo());
			return false;
		}
	}
}

$installer = new KitkuInstaller;

if (isset($installer->installed) && $installer->installed === true) {
	header("Location: ".$installer->home['url']);
	exit();
}

if (isset($_GET['formdata']) && $_GET['formdata'] == true) {
	switch ($_POST['page']) {
		case 0:
		// Check if user provided data allows for server connection.
			$dbInfo = [
				'server' => $_POST['database-servername'], 
				'username' => $_POST['database-username'], 
				'password' => $_POST['database-password'],
				'database' => 'kitku_'.random_string(5)
			];

			if ($installer->open_conn($dbInfo['server'], $dbInfo['username'], $dbInfo['password'])) {
				$dbCreated = 0;
				while($dbCreated !== true) {
					if ($installer->create_database($dbInfo['database'], false)) {
						$dbCreated = true;
						$installer->set_dbInfo($dbInfo);
						$installer->installed = 1;
						if ($installer->set_config()) {
							exit('success');
						} else {
							exit('serveErr');
						}
					} else {
						$dbInfo['database'] = random_string(6);
						$dbCreated++;
					}
					if ($dbCreated > 10) {
						exit('createDBFail');
					}
				}
			} else {
				exit($installer->dbError);
			}
			break;

		case 1:
		// Build basic database structure
			$usersColumns = [
				'username' => 'VARCHAR(30) NOT NULL',
				'email' => 'VARCHAR(30)',
				'password' => 'VARCHAR(255) NOT NULL'
			];
			$postsColumns = [
				'title' => 'VARCHAR(80) NOT NULL',
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
			if (!$installer->create_table('users', $usersColumns)) {
				exit('serveErr-1');
			}
			if (!$installer->create_table('posts', $postsColumns)) {
				exit('serveErr-2');
			}
			if (!$installer->create_table('pages', $pagesColumns)) {
				exit('serveErr-3');
			}

			$userInfo = [
				'username' => $_POST['username'],
				'email' => $_POST['email'],
				'password' => password_hash($_POST['password'], PASSWORD_DEFAULT)
			];
			// Should make these defaults more professional looking...
			$defaultPost = [
				'title' => 'My new Kitku Site!',
				'author' => 'Kitku',
				'category' => 'uncategorized',
				'date' => time(),
				'tags' => 'first-post,default',
				'views' => '',
				'content' => 'A dove and a fist'
			];
			$defaultBlog = [
				'title' => 'Blog',
				'parent' => '',
				'views' => '',
				'content' => 'The blog page!',
				'blogPage' => 1,
				'showInMenu' => 1
			];
			$defaultAbout = [
				'title' => 'About',
				'parent' => '',
				'views' => '',
				'content' => 'This is the about page. <h2>This is an h2 tag</h2>',
				'blogPage' => 0,
				'showInMenu' => 1
			];

			if (!$installer->insert('posts', $defaultPost)) {
				echo $installer->dbError;
				exit('sqlErr-1');
			}

			if (!$installer->insert('pages', $defaultBlog)) {
				exit('sqlErr-2');
			}

			if (!$installer->insert('pages', $defaultAbout)) {
				exit('sqlErr-3');
			}

			if ($installer->insert('users', $userInfo)) {
				$installer->installed = 2;
				if ($installer->set_config()) {
					exit('success');
				}
			}
			exit('serveErr-0');
			break;
		case 2:
		// Get the site name and redirect
			$installer->siteName = $_POST['sitename'];
			$installer->installed = true;
			if ($installer->set_config()) {
				exit('goHome');
			}
			exit('serveErr');
			break;
	}	
} else {
?>
<!DOCTYPE html>
<html>
<head>

	<meta charset="utf-8">
	<title>Kitku Installer</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" type="text/css" href="<?= $installer->home['installUrl'].'res/normalize.css'?>">
	<link rel="stylesheet" type="text/css" href="<?= $installer->home['installUrl'].'res/default-style.css'?>">
	<link rel="icon" type="image/png" href="<?= $installer->home['installUrl'].'res/images/favicon.png'?>"/>
</head>
<body>
	<div id="installer">

		<div class="content-container">

			<div class="content-header">
				<img id="kitku-logo" width="64px" height="64px" src="<?= $installer->home['installUrl'].'res/images/logo.png'?>" />
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

	<script>
		const containerBody = document.querySelector('.content-body'),
		nextButton = document.getElementById('next-button'),
		backButton = document.getElementById('back-button'),
		buttons = document.querySelectorAll('.button'),
		paginatePages = document.querySelectorAll('.paginate-page'),
		paginateButtons = document.querySelectorAll('.paginate-button'),
		paginateCount = paginatePages.length,
		pageMessage = document.getElementById('message');

		const installUrl = '<?= $installer->home['installUrl'] ?>';
		const homeUrl = '<?= $installer->home['url'] ?>';

		let activePage = <?= (isset($installer->installed) ? $installer->installed : 0); ?>;

		function paginate(num) {

			setVisibility(paginatePages);
			setVisibility(paginateButtons);

			if (num == 'msg') {
				buttons.forEach((e)=>e.classList.add('hidden'));
				pageMessage.classList.remove('hidden');
			} else {
				pageMessage.classList.add('hidden');
			}

			function setVisibility(elements) {
				elements.forEach( function(element) {
					if (element.classList.contains('page'+num)) {
						element.classList.remove('hidden');
					} else {
						element.classList.add('hidden');
					}
				});
			}
		}

		function formSubmit(e, page) {
			e.preventDefault();

			paginate('msg');

			let count = 0;
			const interval = setInterval(function() {
				pageMessage.textContent.includes('...') ? pageMessage.textContent = 'Working on it' : pageMessage.textContent += '.';
				count++;
				if (count > 20) {
					clearInterval(interval);
					pageMessage.firstChild.textContent = 'This is taking far longer than it should. Check your internet connection, or try restarting your browser.';
				}
			}, 500);

			const fd = new FormData(e.srcElement);
			fd.append('page', page);

			const xhttp = new XMLHttpRequest();
				xhttp.open('POST', installUrl+'install.php?formdata=true', true);
				xhttp.send(fd);

			xhttp.onreadystatechange = function() {
		            if (this.readyState == 4 && this.status == 200){
						clearInterval(interval);
		            	if (this.responseText.includes('success')) {
		            		activePage++;
							paginate(activePage);	
		            	} else if (this.responseText.includes('goHome')){
		            		window.location.replace(homeUrl+'admin');
		            	} else {
		            		if (this.responseText.includes('noHost')) {
		        				resetPage('Host not found. Check your server name and try again.');
		                	} else if (this.responseText.includes('badCred')) {
		                		resetPage('Bad credentials. Confirm your username and password are correct.');
		                	} else if (this.responseText.includes('serveErr')){
		                		resetPage('Server Error. Please try again.');
		                	} else if (this.responseText.includes('createDBFail')){
		                		resetPage("Can't create database. Check your database priveledges.");
		                	} else {
		                		resetPage('There was an unknown error, please contact the Kitku team.');
		                	}
		            	}
		            }
		        }

		    function resetPage(msg) {
		    	clearInterval(interval);
				pageMessage.textContent = msg;
				backButton.classList.remove('hidden');
				backButton.addEventListener('click', function _listener() {
					paginate(activePage);
					pageMessage.textContent = 'Working on it';
					backButton.classList.add('hidden');
					backButton.removeEventListener("click", _listener, true);
				}, true);
		    }    
		}

		function init() {
			paginate(activePage);
			document.addEventListener('keydown', (ele) => {
			// Enter key progresses page
				if (ele.keyCode == 13) {
					buttons.forEach( (e)=> {
						if (!e.classList.contains('hidden')) {
							e.click();
						}
					})
				}
			});
		}

		init();
	</script>
</body>
</html>
<?php } ?>