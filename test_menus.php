<?php
define('NOLOGIN', 1);
require 'main.inc.php';
require 'custom/pressing/core/modules/modPressing.class.php';
$mod = new modPressing($db);
$res = $mod->init();
var_dump($res);
if (!empty($mod->error)) echo "Error: " . $mod->error . "\n";
if (!empty($mod->errors)) print_r($mod->errors);
