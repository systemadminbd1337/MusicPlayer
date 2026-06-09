<?php
	include "config.php";
	unset($_SESSION["user"]);	
	redirect('login.php');