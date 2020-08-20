<?php

require_once 'functions.php';

if (isset($_GET['formdata']) && $_GET['formdata'] == true) {

	switch ($_POST['page']) {
		case 0:
			$db = array('server' => $_POST['database-servername'], 'username' => $_POST['database-username'], 'password' => $_POST['database-password']);
			if (dbConnectionCheck($db['server'], $db['username'], $db['password'])) {
				if (file_put_contents('temp_db.json', json_encode($db))) {
					echo "success";
				} else {
					echo "Server Error";
				}
			} else {
				echo "failed";
			}
			break;

		case 1:
			echo "Hello there page 1";
			break;

		case 2:
			echo "Hello there page 2";
			break;
		
		default:
			echo "this is the default";
			break;
	}
} else {

	include $kitku->homeServer."kitku/defaults/header.php";

	?>
	<div class="main">
		<div class="content-container">
			<div class="content-header">
				<h1>Welcome to Kitku!</h1>
				<em>- Simple Content Management -</em>
			</div>
			<div class="content-body">
				<div class="paginate-page page0 hidden">
					<p>First, let's connect to your database.</p>
					<form id="page1-form" method="post" onsubmit="return formSubmit(event, 0)">
						<div class="form-grid">
							<label for="database-servername">Servername:</label>
							<input name="database-servername" type="text" required="true">
							<label for="database-username">Username:</label>
							<input name="database-username" type="text" required="true">
							<label for="database-password">Password:</label>
							<input name="database-password" type="password">
							<input type="submit" id="page1-form-submit" class="hidden">
						</div>
					</form>
				</div>

				<div class="paginate-page page1 hidden">
					<p>We're going to build a new table in your database. What should it be called?</p>
					<form id="page2-form" onsubmit="return formSubmit(event, 1)">
						<div class="form-grid">
							<label for="database-tablename">Table name:</label>
							<input name="database-tablename" type="text" placeholder="One word, alphanumeric characters only." required="true">
							<input type="submit" id="page2-form-submit" class="hidden">
						</div>
					</form>
				</div>

				<div class="paginate-page page2 hidden">
					<p>Now we need to set up a user.</p>
					<form id="page3-form" onsubmit="return formSubmit(event, 2)">
						<div class="form-grid">
							<label for="username">Username:</label>
							<input name="username" type="text" required="true">
							<label for="email">Email:</label>
							<input name="email" type="email" placeholder="Not required, but very useful">
							<label for="password">Password:</label>
							<input name="password" type="password" placeholder="Use a secure password!" required="true">
							<input type="submit" id="page3-form-submit" class="hidden">
						</div>
					</form>
				</div>
					<p class="hidden" id="message">Working on it</p>
			</div>

			<div class="content-footer">
				<label for="page1-form-submit" class="button paginate-button page0 right hidden" tabindex="0">Next</label>
				<label for="page2-form-submit" class="button paginate-button page1 right hidden" tabindex="0">Next</label>
				<label for="page3-form-submit" class="button paginate-button page2 right hidden" tabindex="0">Next</label>
				<span id="back-button" class="button left hidden">Back</span>
			</div>
		</div>
	</div>

	<script src="<?= $kitku->homeUrl ?>kitku/install.js"></script>

	<?php

	include $kitku->homeServer."kitku/defaults/footer.php";
}

?>