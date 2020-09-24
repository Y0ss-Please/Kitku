<?php
	require 'kitku/kitku.php';	

	$kitku = new KitkuPage();

	if (empty($kitku->installed) || $kitku->installed !== true) {
		require_once 'kitku/install.php';
		exit();
	}

	if (!empty($_GET['p'])) {
		$kitku->set_active_page($_GET['p']);
		include $kitku->home['server'].'theme/p.php';		
	} else {
		$kitku->set_active_page('blog');
		include $kitku->home['server'].'theme/p.php';
	}
?>