<?php

require_once 'kitku.php';

$kitku = new Kitku();

if (isset($_POST['password'])) {
    $kitku->update('users', 
    [
        'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
        'passwordReset' => ''
    ],
    'passwordReset='.$_SESSION['auth']);
    echo('success');
    header('Location: '.$kitku->home['installUrl'].'admin.php');
    exit();
}

if (empty($_GET)) {
    exit('Error: No authentication found.');
}

if (!isset($_GET['auth'])) {
    exit('Bad link information. Please try again.');
}

$authToken = $_GET['auth'];

if(time() > explode(':', $authToken)[0]) {
    exit('Password Reset expired. Please request a new password reset link');
}

if($kitku->select('passwordReset', 'users', 'passwordReset='.$authToken)) {

    $kitku->update('users', ['authToken' => '', 'authSelector' => ''], 'passwordReset='.$authToken);
    $_SESSION['auth'] = ($authToken);

    include $kitku->home['installServer'].'res/header.php';
    ?>
    <div id="reset">

    <div id="reset-container" class="content-container">

        <div class="content-header">
            <img id="kitku-logo-static" width="64px" height="64px" src="<?= $kitku->home['installUrl'].'res/images/logo.png'?>" />
            <h1>Kitku</h1>
            <em>- Simple Content Management -</em>
        </div>

        <div id="reset-box" class="content-body">
            <form method="post" onsubmit="return reset_submit(event)">
                <div class="form-grid">
                    <label for="password">New Password:</label>
                    <input name="password" type="password" required="true">
                    <input type="submit" id="reset-submit" class="hidden">
                </div>
            </form>
        </div>

        <div class="content-footer">
            <span id="reset-error"></span>
            <label id="reset-button" for="reset-submit" class="button right margin-1-right" tabindex="0">Submit</label>
        </div>
    </div>
    <div id="forgot-password">forgot password?</div>
    </div>

    <script src="<?= $kitku->home['installUrl'].'res/js/reset.js' ?>"></script>
    </body>

    <?php
} else {
    exit('Invalid Authentication. Please try again.');
}
?>
</html>