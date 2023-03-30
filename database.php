<?php



$DBServer = ''; 
$DBName = ''; 
$DBUser = '';
$DBPass = "";
//$DBPass="!swg2018!BC";


$mysqli_sfp = new mysqli($DBServer, $DBUser, $DBPass, $DBName);
if ($mysqli_sfp->connect_errno) {echo "Failed to connect to MySQL SFP";}

$host="localhost";
$namelogin="root";
$passlogin="";
$db="vectura";

$mysqli = new mysqli($host, $namelogin, $passlogin, $db);
if ($mysqli->connect_errno) {
  echo "Failed to connect to MySQL Aurora: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
  exit();
}

