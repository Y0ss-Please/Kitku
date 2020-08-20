<?php

function dbConnectionCheck($server, $user, $pass) {
	$conn = new mysqli($server, $user, $pass);
	if ($conn->connect_error) {
		return false;
	} else {
		return true;
	}
}

?>