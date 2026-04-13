<?php
session_start();
session_unset();
session_destroy();
header("Location: index.html"); // or homepage.html if you prefer
exit();
?>