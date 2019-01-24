<?php

class MplusQAPIclient_VersionTester
{
  const VERSION_TESTER_VERSION  = '1.1.0';

  var $debug = false;

   /**
   * @var string
   */
  private $apiServer = null;
  /**
   * @var string
   */
  private $apiPort = null;
  /**
   * @var string
   */
  private $apiPath = null;
  /**
   * @var string
   */
  private $apiFingerprint = null;
  /**
   * @var string
   */
  private $detectedFingerprint = null;
  /**
   * @var string
   */
  private $apiIdent = null;
  /**
   * @var string
   */
  private $apiSecret = null;
  /**
   * @var 
   */
  private $client = null;
  
  /**
   * @param string $apiServer The api server
   * @param string $apiPort The api port
   * @param string $apiPath The api path
   * @param string $apiFingerprint The api fingerprint
   * @param string $apiIdent The api ident
   * @param string $apiSecret The api secret
   *
   * @throws WebshopappApiException
   */
  public function __construct($params=null)
  {
    if( ! function_exists('curl_init'))
    {
      throw new MplusQAPIException_VersionTester('MplusQAPIclient_VersionTester needs the CURL PHP extension.');
    }
    if( ! function_exists('json_decode'))
    {
      throw new MplusQAPIException_VersionTester('MplusQAPIclient_VersionTester needs the JSON PHP extension.');
    }

    if ( ! is_null($params)) {
      $this->setApiServer($params['apiServer']);
      $this->setApiPort($params['apiPort']);
      $this->setApiPath($params['apiPath']);
      $this->setApiFingerprint($params['apiFingerprint']);
      $this->setApiIdent($params['apiIdent']);
      $this->setApiSecret($params['apiSecret']);
      $this->initClient();
    }

    $this->parser = new MplusQAPIDataParser_VersionTester();
  }

  /**
   * @param $apiServer
   */
  public function setApiServer($apiServer)
  {
    $this->apiServer = $apiServer;
  } // END setApiServer()

  //----------------------------------------------------------------------------

  /**
   * @param $apiPort
   */
  public function setApiPort($apiPort)
  {
    $this->apiPort = $apiPort;
  } // END setApiPort()

  //----------------------------------------------------------------------------

  /**
   * @param $apiPath
   */
  public function setApiPath($apiPath)
  {
    $this->apiPath = $apiPath;
  } // END setApiPort()

  //----------------------------------------------------------------------------

  /**
   * @param $apiFingerprint
   */
  public function setApiFingerprint($apiFingerprint)
  {
    $this->apiFingerprint = trim(strtolower(str_replace(' ', '', $apiFingerprint)));
  } // END setApiPort()

  //----------------------------------------------------------------------------

  /**
   * @param $apiIdent
   */
  public function setApiIdent($apiIdent)
  {
    $this->apiIdent = $apiIdent;
  } // END setApiPort()

  //----------------------------------------------------------------------------

  /**
   * @param $apiSecret
   */
  public function setApiSecret($apiSecret)
  {
    $this->apiSecret = $apiSecret;
  } // END setApiPort()

  //----------------------------------------------------------------------------   

  //----------------------------------------------------------------------------

  /**
   * @param $debug
   */
  public function setDebug($debug)
  {
    $this->debug = $debug;
  } // END setDebug()

  //----------------------------------------------------------------------------

  public function initClient()
  {
    $location = $this->apiServer;
    $require_fingerprint_check = true;
    if (false === stripos($location, 'http://') and false === stripos($location, 'https://')) {
      $location = 'https://'.$location;
    }
    if (false !== stripos($location, 'http://')) {
      $require_fingerprint_check = false;
    }
    if (isset($this->apiPort) and ! empty($this->apiPort) and $this->apiPort != '80') {
      $location .= ':'.$this->apiPort;
    }
    if (isset($this->apiPath) and ! empty($this->apiPath)) {
      $location .= '/'.$this->apiPath;
    }
    $location_with_credentials = $location;
    $params_started = false;
    if (isset($this->apiIdent) and ! empty($this->apiIdent)) {
      $location_with_credentials .= '?ident='.urlencode($this->apiIdent);
      $params_started = true;
    }
    if (isset($this->apiSecret) and ! empty($this->apiSecret)) {
      $location_with_credentials .= $params_started ? '&' : '?';
      $location_with_credentials .= 'secret='.urlencode($this->apiSecret);
    }

    $options = array(
      'location' => $location_with_credentials,
      'uri' => 'urn:mplusqapi',
      'trace' => $this->debug,
      'exceptions' => true, 
      'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
      'cache_wsdl' => WSDL_CACHE_NONE,
      );

    $wsdl_url = $location.'?wsdl';
    try {
      // Don't wait longer than 5 seconds for the headers.
      // We call get_headers() here because we want a relatively fast check if the API is available at all
      // , before we actually initialize the SoapClient and start running requests
      ini_set('default_socket_timeout', 5);
      if (false === @get_headers($wsdl_url)) {
        throw new MplusQAPIException_VersionTester(sprintf('Cannot find API WSDL @ %s', $wsdl_url));
      }
      $this->client = @new SoapClient($wsdl_url, $options);
      if (false === $this->client or is_null($this->client)) {
        throw new MplusQAPIException_VersionTester('Unable to load SoapClient.');
      }
    } catch (SoapFault $exception) {
      throw new MplusQAPIException_VersionTester($exception->getMessage());
    }

    return true;
  } // END initClient()

  //----------------------------------------------------------------------------

  public function getLastRequest()
  {
    if ($this->debug and ! is_null($this->client)) {
      return $this->client->__getLastRequest();
    } else {
      return false;
    }
  } // END getLastRequest()

  //----------------------------------------------------------------------------

  public function getLastResponse()
  {
    if ($this->debug and ! is_null($this->client)) {
      return $this->client->__getLastResponse();
    } else {
      return false;
    }
  } // END getLastResponse()

  //----------------------------------------------------------------------------

  public function getLastErrorMessage()
  {
    if (isset($this->parser) and ! is_null($this->parser)) {
      return $this->parser->getLastErrorMessage();
    }
    return null;
  } // END getLastErrorMessage()

  //----------------------------------------------------------------------------

  protected function checkFingerprint($location)
  {
    $this->detectedFingerprint = null;
    $fingerprint_matches = false;
    $g = stream_context_create (array('ssl' => array('capture_peer_cert' => true)));
    if (false === ($r = @stream_socket_client(str_replace('https', 'ssl', $location), $errno,
      $errstr, 30, STREAM_CLIENT_CONNECT, $g))) {
      $this->detectedFingerprint = 'Unable to stream_socket_client()';
      return $fingerprint_matches;
    }
    $cont = stream_context_get_params($r);
    if (isset($cont['options']['ssl']['peer_certificate'])) {
      // $certificate_info = openssl_x509_parse($cont['options']['ssl']['peer_certificate']);
      $resource = $cont['options']['ssl']['peer_certificate'];
      $output = null;
      if (false !== ($result = openssl_x509_export($resource, $output))) {
        $output = str_replace('-----BEGIN CERTIFICATE-----', '', $output);
        $output = str_replace('-----END CERTIFICATE-----', '', $output);
        $output = base64_decode($output);
        $this->detectedFingerprint = sha1($output);
        if ($this->detectedFingerprint == $this->apiFingerprint) {
          $fingerprint_matches = true;
        }
      } else {
        $this->detectedFingerprint = 'Unable to openssl_x509_export()';
      }
    } else {
        $this->detectedFingerprint = 'Cannot find \'peer_certificate\'';
    }
    return $fingerprint_matches;
  } // END checkFingerprint()

  //----------------------------------------------------------------------------

  public function getApiVersion()
  {
    try {
      $result = $this->client->getApiVersion();
      return $this->parser->parseApiVersion($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException_VersionTester('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException_VersionTester('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getApiVersion()

  //----------------------------------------------------------------------------

}

//==============================================================================

class MplusQAPIDataParser_VersionTester
{

  var $lastErrorMessage = null;

  //----------------------------------------------------------------------------

  public function getLastErrorMessage()
  {
    return $this->lastErrorMessage;
  } // END getLastErrorMessage()

  //----------------------------------------------------------------------------

  public function parseApiVersion($soapApiVersion)
  {
    $apiVersion = false;
    if (isset($soapApiVersion->majorNumber)) {
      $apiVersion = objectToArray($soapApiVersion);
    }
    else if (isset($soapApiVersion['majorNumber'])) {
      $apiVersion = $soapApiVersion;
    }
    return $apiVersion;
  } // END parseApiVersion()

  //----------------------------------------------------------------------------
}

//------------------------------------------------------------------------------

class MplusQAPIException_VersionTester extends Exception
{

}

//------------------------------------------------------------------------------

if ( ! function_exists('objectToArray')) {
  function objectToArray($d) {
    if (is_object($d)) {
      // Gets the properties of the given object
      // with get_object_vars function
      $d = get_object_vars($d);
    }

    if (is_array($d)) {
      /*
      * Return array converted to object
      * Using __FUNCTION__ (Magic constant)
      * for recursive call
      */
      return array_map(__FUNCTION__, $d);
    }
    else {
      // Return array
      return $d;
    }
  } // END objectToArray()
}

//------------------------------------------------------------------------------


if ( ! function_exists('arrayToObject')) {
  $global_leave_as_array = null;
  function arrayToObject($d, $leave_as_array=null, $debug=false) {
    global $global_leave_as_array;
    if ( ! is_null($leave_as_array)) {
      $global_leave_as_array = $leave_as_array;
    }
    if (is_array($d)) {
      /*
      * Return array converted to object
      * Using __FUNCTION__ (Magic constant)
      * for recursive call
      */
      if (isset($d['articleNumbers']) or isset($d['groupNumbers']) or isset($d['imageIds'])) {
        if ( ! is_null($leave_as_array)) {
          $global_leave_as_array = null;
        }
        return (object) $d;
      }
      elseif ( ! is_null($global_leave_as_array) and is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0][$global_leave_as_array])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['text'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['pluNumber'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['imageName'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['articleNumber'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['amount'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['id'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (isset($d[0]) and is_integer($d[0])) {
        return array_map(__FUNCTION__, $d);
      }
      else {
        return (object) array_map(__FUNCTION__, $d);
      }
    }
    else {
      // Return object
      if ( ! is_null($leave_as_array)) {
        $global_leave_as_array = null;
      }
      return $d;
    }
  } // END arrayToObject()
}

//------------------------------------------------------------------------------