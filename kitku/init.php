<?php

	$kitku = new Kitku();

	if ($kitku->isFirstRun) {
		$kitku->homeUrl = substr($_SERVER['HTTP_REFERER'], 0, -1).$_SERVER['REQUEST_URI']; // This bugs out if you navigate straght to index.html without starting at localhost
		$kitku->homeServer = str_replace('index.php', '', $_SERVER['SCRIPT_FILENAME']);
		include $kitku->homeServer.'kitku/install.php';
	}

	class Kitku {

		function __construct() {
			if (file_exists('config.json')) {
				$vars = json_decode(file_get_contents('config.json'), true);
				foreach ($vars as $key => $value) {
					$this->$key = $value;
				}
			} else {
				echo('The config.json file is missing!');
				die();
			}
		}
	}
?>