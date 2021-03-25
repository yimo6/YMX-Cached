<?php
include 'class.YMX-Cached.php';
$sync = new YMX_Cached();
$sync -> server_create();
$sync -> server_run();