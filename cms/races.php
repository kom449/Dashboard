<?php
include '../cors.php'; // Adjust the path if cors.php is elsewhere

header('Content-Type: application/json');
readfile('races.json');
