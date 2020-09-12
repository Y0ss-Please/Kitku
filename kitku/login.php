<?php

require_once 'kitku.php';

$kitku = new Kitku();

if (!empty($_POST)) {
    $source = !empty($_GET['source']) ? $_GET['source'] : false;
    $remember = (empty($_POST['remember']) ? false : true );
    echo($kitku->login($_POST['username'], $_POST['password'], $remember, $source));
    exit();
}

include $kitku->home['installServer'].'res/header.php';

?>

<script>
    const homeUrl = '<?= $kitku->home['url'] ?>',
        installUrl = '<?= $kitku->home['installUrl'] ?>';
</script>

<body>
	<div id="login">

		<div id="login-container" class="content-container">

			<div class="content-header">
				<img id="kitku-logo" width="64px" height="64px" src="<?= $kitku->home['installUrl'].'res/images/logo.png'?>" />
				<h1>Kitku</h1>
				<em>- Simple Content Management -</em>
			</div>

			<div class="content-body">
                <form method="post" onsubmit="return form_submit(event)">
                    <div class="form-grid">
                        <label for="username">Username:</label>
                        <input name="username" type="text" required="true">
                        <label for="password">Password:</label>
                        <input name="password" type="password" required="true">
                        <input type="submit" id="submit" class="hidden">
                        <div></div>
                        <div id="remember-container">
                            <label for="remember">Remember Me:</label>
                            <input name="remember" type="checkbox">
                        </div>
                    </div>
                </form>
            </div>

			<div class="content-footer">
                <span id="login-error"></span>
				<label id="login-button" for="submit" class="button right" tabindex="0">Login</label>
			</div>
		</div>

	</div>

	<script src="<?= $kitku->home['installUrl'].'res/js/login.js' ?>"></script>
</body>
</html>