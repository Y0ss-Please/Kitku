<?php

require_once 'kitku.php';

if (empty($kitku)) {
	$kitku = new Kitku();
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
						$dbInfo['database'] = random_string(6);
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
			$usersColumns = [
				'username' => 'VARCHAR(30) NOT NULL',
				'email' => 'VARCHAR(30)',
				'password' => 'VARCHAR(255) NOT NULL'
			];
			$articlesColumns = [
				'title' => 'VARCHAR(80) NOT NULL',
				'tags' => 'VARCHAR(255)',
				'views' => 'INT(6) NOT NULL',
				'content' => 'LONGTEXT NOT NULL'
			];
			if (!$kitku->create_table('users', $usersColumns)) {
				exit('serveErr');
			}
			if (!$kitku->create_table('articles', $articlesColumns)) {
				exit('serveErr');
			}

			$userInfo = [
				'username' => $_POST['username'],
				'email' => $_POST['email'],
				'password' => password_hash($_POST['password'], PASSWORD_DEFAULT)
			];

			if ($kitku->insert('users', $userInfo)) {
				$kitku->installed = 2;
				if ($kitku->set_config()) {
					exit('success');
				}
			}
			exit('serveErr');
			break;
		case 2:
			$kitku->siteName = $_POST['sitename'];
			$kitku->installed = true;
			if ($kitku->set_config()) {
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
	<title>Kitku Install</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<style>
		* {
			font-family: "Lucida Console", Courier, monospace;
		}

		body {
			margin: 0;
			width: 100%;
			background-color: #231f22;
		}

		h1 {
			margin: 0;
		}

		em {
			color: #008080;
		}

		a, a:visited{
			color: #008080;
		}

		.main {
			color: #a5a4a7;
			margin: 10vh auto;
			max-width: 100%;
			width: 40rem;
		}

		.content-container {
			background-color: #38353a;
		}

		.form-grid {
			display: grid;
			grid-template-columns: auto 1fr;
			grid-row-gap: 1rem;
			grid-column-gap: 1rem;
		}

		.form-grid>label {
			text-align: right;
		}

		.content-header {
			background-color: #130f15;
			text-align: center;
			padding: 1rem;
		}

		.content-body {
			padding: 1rem 1rem;
		}

		.content-footer {
			position: relative;
			background-color: #130f15;
			height: 2rem;
			line-height: 2rem;
			padding: 1rem;
		}

		input[type="text"], input[type="email"], input[type="password"] {
			background-color: #a5a4a7;
			border: none;
		}

		button, input[type="submit"], input[type="reset"] {
			background: none;
			color: inherit;
			border: none;
			padding: 0;
			font: inherit;
			cursor: pointer;
			outline: inherit;
		}

		.button {
			background-color: #008080;
			border: 1px solid #273031;
			border-radius: 2px;
			padding: 0 1rem;
			margin: 0 1rem;
			cursor: pointer;
		}

		.button:hover {
			background-color: #1ab3b3;
		}

		.button:active {
			background-color: #135050;
		}

		.right {
			position: absolute;
			right: 0;
		}

		.left {
			position: absolute;
			left: 0;
		}

		.hidden {
			display: none;
		}
	</style>
</head>
<body>
	<div class="main">
		<div class="content-container">
			<div class="content-header">
				<h1>Welcome to Kitku!</h1>
				<em>- Simple Content Management -</em>
			</div>
			<div class="content-body">
				<div class="paginate-page page0 hidden">
					<p>Thanks for choosing Kitku! If you need any help with setup, please click <a href="https://www.google.com">here.</a></p>
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

				<div class="paginate-page page1 hidden">
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

				<div class="paginate-page page2 hidden">
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
				<label for="page0-form-submit" class="button paginate-button page0 right hidden" tabindex="0">Next</label>
				<label for="page1-form-submit" class="button paginate-button page1 right hidden" tabindex="0">Next</label>
				<label for="page2-form-submit" class="button paginate-button page2 right hidden" tabindex="0">Complete</label>
				<button id="back-button" class="button left hidden" tabindex="0">Back</button>
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

		const installUrl = '<?= $kitku->home['installUrl'] ?>';
		const homeUrl = '<?= $kitku->home['url'] ?>';

		let activePage = <?= $kitku->installed; ?>;

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
		                		resetPage("Can't create database. Check your SQL database priveledges.");
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
			containerBody.style.minHeight = containerBody.offsetHeight+'px';
			document.addEventListener('keydown', (ele) => {
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