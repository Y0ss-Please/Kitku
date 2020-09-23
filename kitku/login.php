<?php

require_once 'kitku.php';

$kitku = new Kitku();

if (!empty($_POST)) {
    if ($_POST['func'] === 'login') {
        $source = !empty($_GET['source']) ? $_GET['source'] : false;
        $remember = (empty($_POST['remember']) ? false : true );
        echo($kitku->login($_POST['username'], $_POST['password'], $remember, $source));
        exit();
    } else if($_POST['func'] === 'forgot') {

        $username = $kitku->select('username', 'users', 'email='.$_POST['email'])[0]['username'];
        $authToken = (time() + 1800).':'.$kitku->random_string(25);
        $authPath = $kitku->home['installUrl'].'password_reset.php?&auth='.$authToken;

        $headers = "From: Kitku\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";

        $msg = 'Let\'s get you back into you account.<br /><br />Your login name is: '.$username.'<br /><br />Please click this link to reset your password.<br /><a href="'.$authPath.'">Password Reset</a><br /><br />Or paste the following into your browsers url bar:<br />'.$authPath.'<br /><br />Thanks';

        $kitku->update('users', ['passwordReset' => $authToken], 'email='.$_POST['email']);

        if (mail($_POST['email'], 'Kitku Password Reset', $msg, $headers)) {
            echo('sent');
        } else {
            echo('failed');
        }
        exit();
    }
}

include $kitku->home['installServer'].'res/header.php';

?>

<body>
	<div id="login">

		<div id="login-container" class="content-container">

			<div class="content-header">
				<img id="kitku-logo-static" width="64px" height="64px" src="<?= $kitku->home['installUrl'].'res/images/logo.png'?>" />
				<h1>Kitku</h1>
				<em>- Simple Content Management -</em>
			</div>

			<div id="login-box" class="content-body">
                <form method="post" onsubmit="return login_submit(event)">
                    <div class="form-grid">
                        <label for="username">Username:</label>
                        <input name="username" type="text" required="true">
                        <label for="password">Password:</label>
                        <input name="password" type="password" required="true">
                        <input type="submit" id="login-submit" class="hidden">
                        <div></div>
                        <div id="remember-container">
                            <label for="remember">Remember Me:</label>
                            <input name="remember" type="checkbox">
                        </div>
                    </div>
                </form>
            </div>

            <div id="forgot-box" class="content-body hidden">
                <p>Whoops. Enter your email to get back into your account.</p>
                <form method="post" onsubmit="return forgot_submit(event)">
                    <div class="form-grid">
                        <label for="email">email:</label>
                        <input name="email" type="email" required>
                        <input type="submit" id="forgot-submit" class="hidden">
                    </div>
                </form>
            </div>

			<div class="content-footer">
                <span id="login-error"></span>
				<label id="login-button" for="login-submit" class="button right margin-1-right" tabindex="0">Login</label>
				<label id="forgot-button" for="forgot-submit" class="hidden button right margin-1-right" tabindex="0">Send e-mail</label>
            </div>
        </div>
        <div id="forgot-password">forgot?</div>
	</div>

	<script src="<?= $kitku->home['installUrl'].'res/js/login.js' ?>"></script>
</body>
</html>