<?php
// hash_generator.php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);
echo $hash;
?>