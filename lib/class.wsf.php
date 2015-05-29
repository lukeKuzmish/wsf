<?php
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors',1);

if (!class_exists('Curl', false)) {
    require_once __DIR__ . "/class.curl.php";
}

/**
 * Class for interacting with WSF pages
 */

class WSF {
    
    public $c;
    protected $email;
    protected $password;
    const startURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Default.aspx';
    const acctMgmtURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Account/Management/MyAccount_AcntMngTab.aspx';
    const loginURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Login.aspx?cookieCheck=true';
    const serviceURL = "https://secureapps.wsdot.wa.gov/ferries/reservations/vehicle/Service/VRSService.svc/AddToCart";
    const orderSummaryURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Schedule/OrderSummary.aspx?cookieCheck=true';
    const termsAndConditionsURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Schedule/TermConditionRedirection.aspx?cookieCheck=true';
    const accountHolderPaymentURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Account/Payment/AccountHolderPayment.aspx?cookieCheck=true';
    const orderConfirmationURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Schedule/OrderConfirmation.aspx?cookieCheck=true';
    const checkCartURL = 'https://secureapps.wsdot.wa.gov/ferries/reservations/vehicle/Service/VRSService.svc/CartList';
    const reservationsURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Account/Management/MyAccount.aspx?cookieCheck=true';
    const cancelSummaryURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Reserve/CancelChangeSummary.aspx?cookieCheck=true';
    const cancelConfirmationURL = 'https://secureapps.wsdot.wa.gov/Ferries/Reservations/Vehicle/Reserve/CancelChangeConfirmation.aspx?cookieCheck=true';
    
    
    
    /**
     * Constructor
     *
     * @param string $cookiesFile  Location to read/store cookies from
     */
    public function __construct($cookiesFile = null) {
        if ($cookiesFile === null) {
            $cookiesFile = __DIR__ . '/cookies.txt';
        }
        $this->c = new Curl(null, $cookiesFile);
        $this->c->setUserAgent('Chrome Mozilla Firefox');
    }
    
    /**
     * login with provided credentials
     * 
     * @param string $email  email address to use to login
     * @param string $password  password associated with email
     */
    public function login($email, $password) {
        // grab the start page, which sets the ASP.net session cookies
        // without this, attempts to access the login page just redirect
        $startXPath = $this->c->getXPath(self::startURL);
        
        $toPost = $this->getFormValues($startXPath);
        $toPost['__EVENTTARGET'] = 'LinkButton2';
        $acctMgmtXPath = $this->c->postXPath($toPost, self::acctMgmtURL);
        
        // post login info
        $toPost = $this->getFormValues($acctMgmtXPath);
        $toPost['ctl00$MainContent$txtEmailID'] = $email;
        $toPost['ctl00$MainContent$txtPassword'] = $password;
        $toPost['ctl00$MainContent$btnLoginAccount'] = "Log+In";
        $toPost['ctl00$MainContent$guestConfirmationNumber'] = '';
        $toPost['ctl00$MainContent$guestLastName'] = '';
        $loginResult = $this->c->postRequest($toPost, self::loginURL);
        // checking to verify that the login was successful
        $pos = strpos($loginResult, 'var IsUserLoggedIn = parseInt("1");');
        return ($pos !== false) ? true : false;
    }
    
    /**
     * Add Reservation to cart
     * reservationInfo contains all the information necessary to book the
     * ferry trip.
     *  reservationInfo
     *                  ['fromTerminalID']  string leaving terminal ID #
     *                  ['toTerminalID']    string destinatino terminal ID #
     *                  ['journeyID']       string ID # of the trip
     *                  ['journeyDate']     string trip datetime
     *                  ['vehicleTypeID']   string type of vehicle ID #
     *                  ['vehicleLength']   string length of vehicle 
     *                  ['vehicleOptionID'] string vehicle option ID #
     *
     * @param array $reservationInfo
     * @return mixed true (if no error message),
     *               string ErrorMessage (if error message),
     *               false (if no response)
     */
    public function addReservation($reservationInfo) {
        extract($reservationInfo);
        $date = date('l, F j, Y g:i A', strtotime($journeyDate));
        $getVars = array(
                "fromTermID"                =>  $fromTerminalID,
                "toTermID"                  =>  $toTerminalID,
                "journeyID"                 =>  $journeyID,
                "vehLength"                 =>  $vehicleLength,
                "dateTime"                  =>  $date,
                "previousReservationID"     =>  0,
                "previousOrderItemID"       =>  0,
                "isReturnTrip"              =>  'false',
                "vehicleOptionID"           =>  $vehicleOptionID,
                "vehicleTypeId"             =>  $vehicleTypeID,
                "_"                         =>  time(),
        );
        $queryStringParams = array();
        foreach($getVars as $k => $v) {
            if ($k == 'dateTime') {
                $queryStringParams[] = rawurlencode($k) . '=' . str_replace(' ', '%20', $v);
            }
            else {
                
                $queryStringParams[] = rawurlencode($k) . '=' . rawurlencode($v);
            }
        }
        $serviceURL = self::serviceURL . "?" . implode('&', $queryStringParams);
        $addToCartJSON = $this->c->getXHR($serviceURL);
        // Verify that this trip has been added to the cart
        $bookingResponse = json_decode($addToCartJSON, true);
        if (array_key_exists("ErrorMessage", $bookingResponse)) {
            if ($bookingResponse['ErrorMessage'] == null) {
                return true;
            }
            else {
                return $bookingResponse['ErrorMessage'];
            }
        }
        else {
            return false;
        }
    }
    
    /**
     * Request cartList info
     *
     * @return array JSON decoded response from cartCheck
     */
    public function checkCart() {
        $checkCartJSON = $this->c->getXHR(self::checkCartURL);
        return json_decode($checkCartJSON, true);
    }
    
    /**
     * Places order for all items in cart
     *
     * @param string    mobile phone # to use (formatted: 999-999-9999)
     *
     * @return mixed
     *          on success --
     *                          array['reservationNumber']    string
     *                          array['confirmationNumber']   string
     *          on failured -- bool false    
     */
    public function placeOrder($mobilePhone = null) {
        // get order summary page
        $orderSummaryXPath = $this->c->getXPath(self::orderSummaryURL);
        $toPost = $this->getFormValues($orderSummaryXPath);
        $toPost['__EVENTTARGET'] = 'ctl00$MainContent$lbtnContinueAsGuest';
        $toPost['__EVENTARGUMENT'] = '';
        $formHeaders = array('Origin: https://secureapps.wsdot.wa.gov', 'Host: secureapps.wsdot.wa.gov', 'Content-Type: application/x-www-form-urlencoded');
        $this->c->setHeaders($formHeaders);
        // post state variables to orderSummaryURL to get terms and conditions page
        $this->c->postXPath($toPost, self::orderSummaryURL);
        
        // "agree" to terms and conditions to get checkout page
        $accountHolderXPath = $this->c->getXPath(self::termsAndConditionsURL);
        
        // use first CC # already on file
        $ccOptionNL = $accountHolderXPath->query("//select[@id='MainContent_ddlPayWithACreditCardOnFile']/option");
        if ($ccOptionNL->length < 1) {
            // there is no credit card on file!
            // TODO better error checking
            return false;
            //echo "\nNo credit card on file!";
        }
        else {
            foreach($ccOptionNL as $ccN) {
                $currVal = intval($ccN->getAttribute('value'));
                if ($currVal < 0) {
                    // the first item in the dropdown says "Select ..."
                    // it has a value of -1
                    continue;
                }
                else {
                    $creditCardID = $currVal;
                    break;
                }
            } // foreach credit card option    
        }
        
        if ($mobilePhone === null) {
            // get phone number value already on page
            $mobileInputNL = $accountHolderXPath->query("//input[@id='MainContent_txtMobile']");
            if ($mobileInputNL->length < 1) {
                // no mobile # found!
                // TODO better error checking
                return false;
            }
            else {
                $mobilePhone = trim($mobileInputNL->item(0)->getAttribute('value'));
            }
        }
            
        $toPost = $this->getFormValues($accountHolderXPath);
        $toPost['ctl00$MainContent$smPayment'] = 'ctl00$MainContent$updatepanel1|ctl00$MainContent$ddlPayWithACreditCardOnFile';
        $toPost['ctl00$MainContent$txtMobile'] = $mobilePhone;
        $toPost['ctl00$MainContent$ddlCarrier'] = '2';
        $toPost['ctl00$MainContent$chkMobileAlert'] = 'on';
        $toPost['ctl00$MainContent$ddlPayWithACreditCardOnFile'] = $creditCardID;
        $toPost['__ASYNCPOST'] = 'true';
        $toPost['__LASTFOCUS'] = '';
        $toPost['__EVENTARGUMENT'] = '';
        $toPost['__EVENTTARGET'] = 'ctl00$MainContent$ddlPayWithACreditCardOnFile';
        $toPost['ctl00$MainContent$btnPayNow'] = 'Finalize';
        
        $this->c->setHeaders(array("X-MicrosoftAjax:Delta=true",
        "X-Requested-With:XMLHttpRequest", "Referer: " . self::accountHolderPaymentURL, 'Content-Type: application/x-www-form-urlencoded'));
        // send a POST request to trigger the dropdown
        $this->c->postRequest($toPost, $accountHolderPaymentURL);
        // seems necessary to trigger this before continuing
        // without the sleep statement, the subsequent requests would just hang
        // can possibly lower it
        sleep(4);
        $toPost['ctl00$MainContent$smPayment'] = 'ctl00$MainContent$updatepanel1|ctl00$MainContent$btnPayNow';
        $toPost['__EVENTTARGET'] = '';
        // this request doesn't really return anything useful
        $this->c->postRequest($toPost, $accountHolderPaymentURL);
        sleep(3);
        $orderConfXPath = $this->c->getXPath(self::orderConfirmationURL);
        $orderNumNL = $orderConfXPath->query("//strong[contains(text(), 'Confirmation#:')]/..");
        $orderNum = '';
        $resNum = '';
        if ($orderNumNL->length < 1) {
            echo "\nNo order number found!\n";
        }
        else {
            $orderNum = $orderNumNL->item(0)->nodeValue;
            $orderNum = str_replace('Confirmation#:', '', $orderNum);
            $orderNum = str_replace('&nbsp;', '', $orderNum);
            $orderNum = str_replace("\xA0", '', $orderNum);
            $orderNum = str_replace("\xC2", '', $orderNum);
            $orderNum = trim($orderNum);
        }
        $resNumNL = $orderConfXPath->query("//div[@class='res_summary']/table[1]/tr[2]/td[1]");
        if ($resNumNL->length < 1) {
            echo "\nNo reservation # found!\n";
        }
        else {
            $resNum = trim($resNumNL->item(0)->nodeValue);
        }
        return array(
            "reservationNumber"     =>  $resNum,
            "confirmationNumber"    =>  $orderNum,
        );
    }
    
    
    /**
     * Cancel a reservation
     *
     * @param string    reservationNumber
     *
     * @return mixed
     *          on success  -- string cancellation confirmation number
     *          on failured -- bool false    
     */
    public function cancelReservation($reservationNumber) {
        $reservationXPath = $this->c->getXPath(self::reservationsURL);
        $formValues = $this->getFormValues($reservationXPath);
        $toPost = $formValues;
        $releventRowNL = $reservationXPath->query("//tr/td[contains(text(), '{$reservationNumber}')]/..");
        if ($releventRowNL->length < 1) {
            echo "\n\nNo such row!!!\n";
            return false;
        }
        $reservationIDNL = $reservationXPath->query(".//input", $releventRowNL->item(0));
        $reservationID = $reservationIDNL->item(0)->getAttribute('value');
        $toPost['ctl00$MainContent$smOverview'] = 'ctl00$MainContent$upOverview|ctl00$MainContent$rptOrderList$ctl01$lnkCancel';
        $toPost['__EVENTTARGET'] = 'ctl00$MainContent$rptOrderList$ctl01$lnkCancel';
        $toPost['__EVENTARGUMENT'] = '';
        $toPost['ctl00$MainContent$rptOrderList$ctl01$hfItemReservationID'] = $reservationID;
        $toPost['__ASYNCPOST'] = 'true';
        $aspResponse = $this->c->postRequest($toPost, self::reservationsURL);
        $startRelevant = strpos($aspResponse, '|0|hiddenField|');
        if ($startRelevant === false) {
            echo "\n\nInvalid response!";
            return false;
        }
        
        $aspString = trim(substr($aspResponse, $startRelevant));
        $respParts = explode('|', $aspString);
        $vsKey = array_search('__VIEWSTATE', $respParts);
        if ($vsKey === false) {
            echo "\nNo view state found in response!";
            return false;
        }
        $evKey = array_search('__EVENTVALIDATION', $respParts);
        if ($evKey === false) {
            echo "\nNo event validationn found in response!";
            return false;
        }
        $toPost = $formValues;
        $toPost['__VIEWSTATE'] = $respParts[$vsKey + 1];
        $toPost['__EVENTVALIDATION'] = $respParts[$evKey + 1];
        // stays the same
        // previous page
        // 
        // eventvalidation needs updated
        // view state
        $toPost['__EVENTTARGET'] = 'ctl00$MainContent$linkBtnFinal';
        $toPost['ctl00$MainContent$smOverview'] = 'ctl00$MainContent$upOverview|ctl00$MainContent$linkBtnFinal';
        $toPost['__EVENTARGUMENT'] = '';
        $toPost['__ASYNCPOST'] = 'true';
        // this response is async and just tells the browser
        // to issue a new get request
        $this->c->postRequest($toPost, self::reservationsURL);
        sleep(3);
        $cancelXPath = $this->c->getXPath(self::cancelSummaryURL);
        $toPost = $this->getFormValues($cancelXPath);
        $toPost['ctl00$MainContent$changeReservSM'] = 'ctl00$MainContent$updpanelleft|ctl00$MainContent$btnContinue';
        $toPost['__EVENTTARGET'] = 'ctl00$MainContent$btnContinue';
        $toPost['__EVENTARGUMENT'] = '';
        $toPost['__ASYNCPOST'] = 'true';
        $cancelResponse = $this->c->postRequest($toPost, self::cancelSummaryURL);
        sleep(2);
        $ccXPath = $this->c->getXPath(self::cancelConfirmationURL);
        $strongNL = $ccXPath->query("//strong[contains(text(), 'CANCELLATION REFERENCE#:')]/..");
        if ($strongNL->length < 1) {
            // TODO error?
            return false;
        }
        $cancelConfirmationNumber = $strongNL->item(0)->nodeValue;
        $cancelConfirmationNumber = str_replace('CANCELLATION REFERENCE#', '', $cancelConfirmationNumber);
        $cancelConfirmationNumber = str_replace(':', '', $cancelConfirmationNumber);
        $cancelConfirmationNumber = str_replace("\xA0", '', $cancelConfirmationNumber);
        $cancelConfirmationNumber = str_replace("\xC2", '', $cancelConfirmationNumber);
        $cancelConfirmationNumber = trim($cancelConfirmationNumber);
        return $cancelConfirmationNumber;
        
    }
    /*
     *  utility functions
     */
    
    /*
     * Get EventArgument, EventTarget, EventValidation, PreviousPage, and ViewState
     * hidden form input values.
     *
     * @param DOMXPath object $xpath XPath of page from which to retrieve values
     */
    private function getFormValues($xpath) {
    
        $targetNL = $xpath->query("//*[@id='__EVENTTARGET']");
        $argNL = $xpath->query("//*[@id='__EVENTARGUMENT']");
        $vsNL = $xpath->query("//*[@id='__VIEWSTATE']");
        $evNL = $xpath->query("//*[@id='__EVENTVALIDATION']");
        $ppNL = $xpath->query("//*[@id='__PREVIOUSPAGE']");
        
        $eventTarget = $eventArgument = $viewState = $eventValidation = $previousPage = '';
        
        if ($targetNL->length > 0) {
            $eventTarget = $targetNL->item(0)->getAttribute('value');
        }
        if ($argNL->length > 0) {
            $eventArgument = $argNL->item(0)->getAttribute('value');
        }
        if ($vsNL->length > 0) {
            $viewState = $vsNL->item(0)->getAttribute('value');
        }
        if ($evNL->length > 0) {
            $eventValidation = $evNL->item(0)->getAttribute('value');
        }
        if ($ppNL->length > 0) {
            $previousPage = $ppNL->item(0)->getAttribute('value');
        }
        
        return array(
            "__EVENTTARGET"         =>  $eventTarget,
            "__EVENTARGUMENT"       =>  $eventArgument,
            "__VIEWSTATE"           =>  $viewState,
            "__EVENTVALIDATION"     =>  $eventValidation,
            "__PREVIOUSPAGE"        =>  $previousPage
        );
    
    } // getFormValues
} // WSF
