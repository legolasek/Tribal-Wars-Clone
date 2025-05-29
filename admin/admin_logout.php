<?php
require '../init.php';
unset($_SESSION['is_admin']);
header('Location: admin_login.php');
exit();
?> 