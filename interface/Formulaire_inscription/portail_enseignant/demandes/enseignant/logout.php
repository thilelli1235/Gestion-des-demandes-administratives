<?php
session_start();
session_destroy();
header("Location: ../../interface.html");
exit;
?>