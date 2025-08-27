<?php
require_once 'config.php';
header('Content-Type: text/plain; charset=utf-8');
$db = getDBConnection();
$res = $db->query('SELECT id, nom, prenoms FROM employees ORDER BY nom, prenoms');
foreach ($res as $row) {
    echo $row['id'] . ' - ' . $row['nom'] . ' ' . $row['prenoms'] . PHP_EOL;
}
