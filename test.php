<?php

require 'kitku/kitku.php';	

$kitku = new Kitku();

var_dump($kitku->select(['selection', 'selector'], 'table', ['column=value', 'otherCol<otherVal', 'thirdCol>=thirdVal'], ['OR', 'OR']));

echo('<br /><br />');

var_dump($kitku->select(['id', 'username'], 'users'));


?>