<?php
// Admin login redirects to user/login.php since we use the same login system
// This file is just a redirect for convenience
header("Location: ../user/login.php");
exit();
?>