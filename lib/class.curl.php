<?php

class Curl {

    // private properties
    private $_ch            =   null;   // the meat 'n' potatoes
    private $debug          =   false;  // sets curl to verbose
    private $retries        =   0;      // number of attempts to retry a URL
    // RETRIES is messing stuff up when APIs (mostly) return non-200 results to indicate success
    // the workaround I've found is to getCH() and then check the status code
    // TODO -- create method to get last status code 

    // public properties
    public $url             =   null;
    public $cookiesFile     =   null;
    public $userAgent       =   "PHPCurlWrapper v0.4";


    // public methods
    public function __construct($url = null, $cookiesLoc = null) {

        $this->_ch = curl_init();
        
        if ($url) {
            $this->url = $url;
            curl_setopt($this->_ch, CURLOPT_URL, $url);
        }
        
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->_ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->_ch, CURLOPT_SSL_VERIFYPEER, false);

        $this->setDebug(false);        

        if ($cookiesLoc != null) {
            $this->cookiesFile = $cookiesLoc;
            curl_setopt($this->_ch, CURLOPT_COOKIEJAR, $this->cookiesFile);
            curl_setopt($this->_ch, CURLOPT_COOKIEFILE, $this->cookiesFile);
        }

    } // __construct


    public function setDebug($newval = true) {
      
        $this->debug = $newval;
        if ($this->debug) {
            curl_setopt($this->_ch, CURLOPT_VERBOSE, true);
        }
        else {
            curl_setopt($this->_ch, CURLOPT_VERBOSE, false);
        }

    } // setDebug

    
    public function setUserAgent($userAgent = "") {
      
        $this->userAgent = $userAgent;
    
    } // setUserAgent
    
    
    public function setOpt($opt, $val) {

        curl_setopt($this->_ch, $opt, $val);

    } // setOpt

    public function setHeaders($headers) {

        curl_setopt($this->_ch, CURLOPT_HTTPHEADER, $headers);

    }

    public function getCH() {
      
        return $this->_ch;
        
    } // getCH
    
    
    // XmlHttpRequest methods
    public function getXHR($url = null) {
    
        $this->setXHR();
        return $this->getRequest($url);

    } // getXHR
    
    
    public function postXHR($payload, $url = null) {
    
        $this->setXHR();
        return $this->postRequest($payload, $url);
        
    } // postXHR
    
    
    public function getRequest($url = null) {
    
        curl_setopt($this->_ch, CURLOPT_HTTPGET, true);
        return $this->exec($url);

    } // getRequest

    
    public function getDOMDocument($url = null) {
    
      $html = $this->getRequest($url);
      $dom = $this->htmlToDOMDocument($html);
      return $dom;
        
    } // getDOMDocument

    public function postDOMDocument($payload, $url = null) {
      // this doesn't POST a domdocument, but rather sends a post payload
      // and returns  a DOMDocument for the result
      $html = $this->postRequest($payload, $url);
      $dom = $this->htmlToDOMDocument($html);
      return $dom;
    } // postDOMDocument
    
    
    public function getXPath($url = null) {
      
      $dom = $this->getDOMDocument($url);
      $xpath = new DOMXPath($dom);
      return $xpath;
      
    } // getXPath
    

    public function postXPath($payload, $url = null) {
      // this doesn't POST an XPath object, but rather posts the payload
      // and converts the HTML to an XPath obj
      $dom = $this->postDOMDocument($payload, $url);
      $xpath = new DOMXPath($dom);
      return $xpath;
      
    } // postXPath
    
    
    public function postRequest($payload, $url = null) {
    
        if (is_array($payload)) {
          $payload = http_build_query($payload, '', '&');
        }
        
        curl_setopt($this->_ch, CURLOPT_POST, true);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($this->_ch, CURLOPT_POSTREDIR, 2);
        
        if ($this->debug) {
            echo "http query: $payload\n";
        }        
        
        return $this->exec($url);

    }    
    
    public function deleteRequest($url = null) {
                
            curl_setopt($this->_ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            return $this->exec($url);
                
        } // deleteRequest
        
        
    public function putRequest($payload, $url = null) {
                
        if (is_array($payload)) {
          $payload = http_build_query($payload, '', '&');
        }
                
        curl_setopt($this->_ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $payload);
                
        if ($this->debug) {
            echo "http query: $payload\n";
        }        
                
        return $this->exec($url);
                
    } // putRequest
  
    // private methods
    private function setXHR() {
      
        curl_setopt($this->_ch, CURLOPT_HTTPHEADER, array("X-Requested-With: XMLHttpRequest"));
    
    }
    
    private function htmlToDOMDocument($html) {
      
      $dom = new DOMDocument();
      @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
      return $dom;
      
    }
    private function exec($url = null) {
    
        // if neither URL is set, return false
        if (!$url AND !$this->url) {
            return false;
        }
        
        // if user has passed in URL, update the object's property
        if ($url != null) {
            $this->url = $url;
        }
        
        curl_setopt($this->_ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($this->_ch, CURLOPT_URL, $this->url);
        
        // retry until HTTP status is 200 or we've met $Curl->retries
        $attempts = 1;
        do {
            $result = curl_exec($this->_ch);
        
            $statusCode = curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);
        } while (($attempts++ < $this->retries) and ($statusCode != 200));

        // if there is a redirect, update object's url property 
        $this->url = curl_getinfo($this->_ch, CURLINFO_EFFECTIVE_URL);
        
        
        return $result;

    }
    

} // Curl
