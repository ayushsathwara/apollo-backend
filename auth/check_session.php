<?php

session_start();

if (isset($_SESSION["student_id"])) {

    echo "Session active. Student ID: " . $_SESSION["student_id"];

} else {

    echo "No session found.";

}

?>