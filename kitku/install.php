<?php

require_once 'kitku.php';

if (empty($kitku)) {
	$kitku = new Kitku();
}

if (isset($_GET['formdata']) && $_GET['formdata'] == true) {
	switch ($_POST['page']) {
		case 0:
			// Check if user provided data allows for server connection.
			$dbInfo = array(
				'server' => $_POST['database-servername'], 
				'username' => $_POST['database-username'], 
				'password' => $_POST['database-password'],
				'dbName' => 'kitku_'.$kitku->random_string(5)
			);

			if ($kitku->open_conn($dbInfo['server'], $dbInfo['username'], $dbInfo['password'])) {
				// Build kitku database
				$dbCreated = 0;
				while($dbCreated !== true) {
					if ($kitku->create_database($dbInfo['dbName'], false)) {
						$dbCreated = true;
						$kitku->dbInfo = $dbInfo;
						if ($kitku->set_config()) {
							echo 'success';
						} else {
							echo 'serveErr';
						}
					} else {
						$dbInfo['dbName'] = 'kitku_'.random_string(6);
						$dbCreated++;
					}
					if ($dbCreated > 10) {
						die('createDBFail');
					}
				}
			} else {
				echo $kitku->dbError;
			}
			break;

		case 1:
			var_dump($_POST);
			// TODO: THE NEXT PAGES OF THE INSTALL
			break;
		
		default:
			echo 'this is the default';
			break;
	}

	// THIS WILL BREAK THINGS LATER
	(!empty($dbInfo)) ? $kitku->drop_database($kitku->dbInfo['dbName']) : ''; // "THIS LINE HERE, OFFICER.""
	// ^^ THIS IS PROBABLY THE DROID YOU'RE LOOKING FOR ^^
	
} else {

	include $kitku->homeServer."defaults/header.php";

	?>
	<div class="main">
		<div class="content-container">
			<div class="content-header">
				<h1>Welcome to Kitku!</h1>
				<em>- Simple Content Management -</em>
			</div>
			<div class="content-body">
				<div class="paginate-page page0 hidden">
					<p>Thanks for choosing Kitku! If you need any help with setup, please click <a href="">here.</a></p>
					<p>First, let's connect to your database.</p>
					<form id="page0-form" method="post" onsubmit="return formSubmit(event, 0)">
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
					<p>Now we need to set up a user. This will be used to login and make changes to your website.</p>
					<form id="page1-form" onsubmit="return formSubmit(event, 1)">
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
					<p class="hidden" id="message">Working on it</p>
			</div>

			<div class="content-footer">
				<label for="page0-form-submit" class="button paginate-button page0 right hidden" tabindex="0">Next</label>
				<label for="page1-form-submit" class="button paginate-button page1 right hidden" tabindex="0">Next</label>
				<span id="back-button" class="button left hidden">Back</span>
			</div>
		</div>
	</div>

	<script src="<?= $kitku->homeUrl ?>install.js"></script>

	<?php

	include $kitku->homeServer."defaults/footer.php";
}

?>