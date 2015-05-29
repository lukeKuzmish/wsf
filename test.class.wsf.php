<?php
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors',1);

$email = "YOUR EMAIL";
$password = "YOUR PASSWORD";
$mobile_num = '999-999-9999';
$fromTerminalID = '11';
$toTerminalID = '17';
$journeyID = '186656';
$journeyDate = '2015-09-19 22:40:0';
$vehicleTypeID				= 3;
$vehicleLength				= 22;
$vehicleOptionID			= 1000;

if (!class_exists('WSF', false)) {
    require_once __DIR__ . "/lib/class.wsf.php";
}

$wsf = new WSF();

// Test login
$loginResult = $wsf->login($email, $password);
if ($loginResult == true) {
    echo "\nLogin with $email was successful!\n";
}
else {
    echo "\nLogin was unsuccessful!!! Exiting...";
    exit(1);
}



// test addReservation
$reservationInfo = array(
    "fromTerminalID"    =>  $fromTerminalID,
    "toTerminalID"      =>  $toTerminalID,
    "journeyID"         =>  $journeyID,
    "journeyDate"       =>  $journeyDate,
    "vehicleTypeID"     =>  $vehicleTypeID,
    "vehicleLength"     =>  $vehicleLength,
    "vehicleOptionID"   =>  $vehicleOptionID,
);
$reservationResponse = $wsf->addReservation($reservationInfo);
if ($reservationResponse === true) {
    echo "\nSuccessfully reserved a spot on journey $journeyID\n";
}
else {
    if ($reservationResponse === false) {
        echo "\nNo response from WSF! Exiting...";
    }
    else {
        echo "\nThere was an error adding this reservation: " . $reservationResponse . "\nExiting...";
    }
    exit(1);
}

// check cart
$cartResponse = $wsf->checkCart();
//var_dump($cartResponse);

// test placeOrder
$placeOrderResponse = $wsf->placeOrder();
echo "\n!!*!!\n\nplace order response:\n\n";
var_dump($placeOrderResponse);

$orderToCancel = $placeOrderResponse['reservationNumber'];
sleep(5);
echo "\nNow cancelling $orderToCancel \n";
$cancelResponse = $wsf->cancelReservation($orderToCancel);
var_dump($cancelResponse);