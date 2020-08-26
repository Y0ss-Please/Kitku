<?php
	require 'kitku/kitku.php';	

	$kitku = new Kitku();

	if (empty($kitku->installed) || $kitku->installed !== true) {
		require_once 'kitku/install.php';
		exit();
	}

	if (!empty($_GET['page'])) {
		switch ($_GET['page']) {
			case 'admin':
				require $kitku->home['installServer'].'admin.php';
				exit();
		}
	}
?>