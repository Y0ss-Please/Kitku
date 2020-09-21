<?php

require 'kitku/admin.php';

$kitku = new Admin();
$kitku->delete_files($kitku->home['server'].'images/'.'test');

?>