<?php

class MplusQAPIclient
{
  const CLIENT_VERSION  = '1.24.0';
  const WSDL_TTL = 300; // 5 min WSDL TTL

  var $MIN_API_VERSION_MAJOR = 0;
  var $MIN_API_VERSION_MINOR = 9;
  var $MIN_API_VERSION_REVIS = 9;

  var $MAX_API_VERSION_MAJOR = 1;
  var $MAX_API_VERSION_MINOR = 0;
  var $MAX_API_VERSION_REVIS = 0;

  var $debug = false;

  var $parser = null;

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
   * @var
   */
  private $apiVersionBenchmark = null;
  /**
   * @var
   */
  private $skipApiVersionCheck = false;
  /**
   * @var
   */
  private $serviceVersionBenchmark = null;
  /**
   * @var
   */
  private $skipFingerprintCheck = false;
  /**
   * @var
   */
  private $convertToTimestamps = true;
  /**
   * @var
   */
  private $connection_timeout = 30;
  /**
   * @var
   */
  private $default_socket_timeout = 600;
  
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
      throw new MplusQAPIException('MplusQAPIClient needs the CURL PHP extension.');
    }
    if( ! function_exists('json_decode'))
    {
      throw new MplusQAPIException('MplusQAPIClient needs the JSON PHP extension.');
    }
    $ignore64BitWarning = false;
    if (PHP_INT_MAX > 2147483647) {
      $ignore64BitWarning = true;
    } elseif (!is_null($params) and isset($params['ignore64BitWarning']) and $params['ignore64BitWarning']) {
      $ignore64BitWarning = true;
    }
    if (!$ignore64BitWarning) {
      throw new MplusQAPIException(sprintf('Your PHP_INT_MAX is %d. MplusQAPIClient needs to run in a 64-bit system.', PHP_INT_MAX));
    }

    $this->parser = new MplusQAPIDataParser();
    $this->parser->setConvertToTimestamps($this->convertToTimestamps);

    if ( ! is_null($params)) {
      if (isset($params['apiServer'])) {
        $this->setApiServer($params['apiServer']);
      }
      if (isset($params['apiPort'])) {
        $this->setApiPort($params['apiPort']);
      }
      if (isset($params['apiPath'])) {
        $this->setApiPath($params['apiPath']);
      }
      if (isset($params['apiFingerprint'])) {
        $this->setApiFingerprint($params['apiFingerprint']);
      }
      if (isset($params['apiIdent'])) {
        $this->setApiIdent($params['apiIdent']);
      }
      if (isset($params['apiSecret'])) {
        $this->setApiSecret($params['apiSecret']);
      }
      if (isset($params['apiServer']) and isset($params['apiPort']) and isset($params['apiIdent']) and isset($params['apiSecret'])) {
        $this->initClient();
      }
    }
  }

  /**
   * @param $apiServer
   */
  public function setApiServer($apiServer)
  {
    $this->apiServer = $apiServer;
  } // END setApiServer()

  public function getApiServer()
  {
    return $this->apiServer;
  } // END getApiServer()

  //----------------------------------------------------------------------------

  /**
   * @param $apiPort
   */
  public function setApiPort($apiPort)
  {
    $this->apiPort = $apiPort;
  } // END setApiPort()

  public function getApiPort()
  {
    return $this->apiPort;
  } // END getApiPort()

  //----------------------------------------------------------------------------

  /**
   * @param $apiPath
   */
  public function setApiPath($apiPath)
  {
    $this->apiPath = $apiPath;
  } // END setApiPath()

  public function getApiPath()
  {
    return $this->apiPath;
  } // END getApiPath()

  //----------------------------------------------------------------------------

  /**
   * @param $apiFingerprint
   */
  public function setApiFingerprint($apiFingerprint)
  {
    $this->apiFingerprint = trim(strtolower(str_replace(' ', '', $apiFingerprint)));
  } // END setApiFingerprint()

  public function getApiFingerprint()
  {
    return $this->apiFingerprint;
  } // END getApiFingerprint()

  //----------------------------------------------------------------------------

  /**
   * @param $apiIdent
   */
  public function setApiIdent($apiIdent)
  {
    $this->apiIdent = $apiIdent;
  } // END setApiIdent()

  public function getApiIdent()
  {
    return $this->apiIdent;
  } // END getApiIdent()

  //----------------------------------------------------------------------------

  /**
   * @param $apiSecret
   */
  public function setApiSecret($apiSecret)
  {
    $this->apiSecret = $apiSecret;
  } // END setApiSecret()

  public function getApiSecret()
  {
    return $this->apiSecret;
  } // END getApiSecret()

  //----------------------------------------------------------------------------

  /**
   * @param $skipApiVersionCheck
   */
  public function skipApiVersionCheck($skipApiVersionCheck)
  {
    $this->skipApiVersionCheck = $skipApiVersionCheck;
  } // END skipApiVersionCheck()

  public function getSkipApiVersionCheck()
  {
    return $this->skipApiVersionCheck;
  } // END getSkipApiVersionCheck()

  //----------------------------------------------------------------------------

  /**
   * @param $skipFingerprintCheck
   */
  public function skipFingerprintCheck($skipFingerprintCheck)
  {
    $this->skipFingerprintCheck = $skipFingerprintCheck;
  } // END skipFingerprintCheck()

  public function getSkipFingerprintCheck()
  {
    return $this->skipFingerprintCheck;
  } // END getSkipFingerprintCheck()

  //----------------------------------------------------------------------------

  /**
   * @param $setConvertToTimestamps
   */
  public function setConvertToTimestamps($convertToTimestamps)
  {
    $this->convertToTimestamps = $convertToTimestamps;
    if ( ! is_null($this->parser)) {
      $this->parser->setConvertToTimestamps($this->convertToTimestamps);
    }
  } // END setConvertToTimestamps()

  public function getConvertToTimestamps()
  {
    return $this->convertToTimestamps;
  } // END getConvertToTimestamps()

  //----------------------------------------------------------------------------

  /**
   * @param $connection_timeout
   */
  public function setConnectionTimeout($connection_timeout)
  {
    $connection_timeout = (int)$connection_timeout;
    if ($connection_timeout < 5) {
      $connection_timeout = 5;
    }
    if ($connection_timeout > 600) {
      $connection_timeout = 600;
    }
    $this->connection_timeout = $connection_timeout;
  } // END setConnectionTimeout()

  public function getConnectionTimeout()
  {
    return $this->connection_timeout;
  } // END getConnectionTimeout()

  //----------------------------------------------------------------------------

  /**
   * @param $default_socket_timeout
   */
  public function setDefaultSocketTimeout($default_socket_timeout)
  {
    $default_socket_timeout = (int)$default_socket_timeout;
    if ($default_socket_timeout < 5) {
      $default_socket_timeout = 5;
    }
    if ($default_socket_timeout > 600) {
      $default_socket_timeout = 600;
    }
    $this->default_socket_timeout = $default_socket_timeout;
  } // END setDefaultSocketTimeout()

  public function getDefaultSocketTimeout()
  {
    return $this->default_socket_timeout;
  } // END getDefaultSocketTimeout()

  //----------------------------------------------------------------------------

  /**
   * @param $debug
   */
  public function setDebug($debug)
  {
    $this->debug = $debug;
  } // END setDebug()

  public function getDebug()
  {
    return $this->debug;
  } // END getDebug()

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
    if (isset($this->apiPort) and ! empty($this->apiPort)) {
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

    $options = [
      'location' => $location_with_credentials,
      'uri' => 'urn:mplusqapi',
      'trace' => $this->debug,
      'exceptions' => true, 
      'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
      'cache_wsdl' => WSDL_CACHE_DISK,
      'connection_timeout' => $this->connection_timeout,
    ];
    ini_set('soap.wsdl_cache_ttl', MplusQAPIclient::WSDL_TTL);

    $wsdl_url = $location.'?wsdl';
    try {
      // Don't wait longer than 5 seconds for the headers.
      // We call get_headers() here because we want a relatively fast check if the API is available at all
      // , before we actually initialize the SoapClient and start running requests
      ini_set('default_socket_timeout', 5);
      if (false === @get_headers($wsdl_url)) {
        throw new MplusQAPIException(sprintf('Cannot find API WSDL @ %s', $wsdl_url));
      }
      $this->client = @new SoapClient($wsdl_url, $options);
      if (false === $this->client or is_null($this->client)) {
        throw new MplusQAPIException('Unable to load SoapClient.');
      }
    } catch (SoapFault $exception) {
      throw new MplusQAPIException($exception->getMessage());
    }

    // increase max. wait time for API reply
    ini_set('default_socket_timeout', $this->getDefaultSocketTimeout());

    if ( ! $this->skipApiVersionCheck) {
      $this->checkApiVersion();
    }

    return true;
  } // END initClient()

  //----------------------------------------------------------------------------

  public function createApiVersionBenchmark($apiVersion)
  {
    if (is_array($apiVersion)) {
      if (array_key_exists('majorNumber', $apiVersion) and array_key_exists('minorNumber', $apiVersion) and array_key_exists('revisionNumber', $apiVersion)) {
        return ($apiVersion['majorNumber']*1000000)+($apiVersion['minorNumber']*1000)+($apiVersion['revisionNumber']);
      } else {
        return false;
      }
    }
    if (1 === preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $apiVersion, $matches)) {
      return ($matches[1]*1000000)+($matches[2]*1000)+($matches[3]);
    } else {
      return false;
    }
  } // END createApiVersionBenchmark()

  //----------------------------------------------------------------------------

  public function getApiVersionBenchmark()
  {
    if (is_null($this->apiVersionBenchmark) or false === $this->apiVersionBenchmark) {
      if (false !== ($apiVersion = $this->getApiVersion())) {
        $this->apiVersionBenchmark = $this->createApiVersionBenchmark($apiVersion);
      }
    }
    return $this->apiVersionBenchmark;
  } // END getApiVersionBenchmark()

  //----------------------------------------------------------------------------

  public function isApiVersionLowerThan($apiVersion)
  {
    $apiVersionBenchmark = $this->getApiVersionBenchmark();
    if ( ! is_null($apiVersionBenchmark) and false !== $apiVersionBenchmark) {
      if (false !== ($apiVersionCompareBenchmark = $this->createApiVersionBenchmark($apiVersion))) {
        return $apiVersionBenchmark < $apiVersionCompareBenchmark;
      }
    }
    throw new MplusQAPIException('Can\'t check API version compatibility.');
  } // END isApiVersionLowerThan()

  //----------------------------------------------------------------------------

  public function isApiVersionGreaterThan($apiVersion)
  {
    $apiVersionBenchmark = $this->getApiVersionBenchmark();
    if ( ! is_null($apiVersionBenchmark) and false !== $apiVersionBenchmark) {
      if (false !== ($apiVersionCompareBenchmark = $this->createApiVersionBenchmark($apiVersion))) {
        return $apiVersionBenchmark > $apiVersionCompareBenchmark;
      }
    }
    throw new MplusQAPIException('Can\'t check API version compatibility.');
  } // END isApiVersionGreaterThan()

  //----------------------------------------------------------------------------

  public function createServiceVersionBenchmark($apiVersion)
  {
    if (is_array($apiVersion)) {
      if (array_key_exists('serviceMajorNumber', $apiVersion) and array_key_exists('serviceMinorNumber', $apiVersion) and array_key_exists('serviceRevisionNumber', $apiVersion)) {
        return ($apiVersion['serviceMajorNumber']*1000000)+($apiVersion['serviceMinorNumber']*1000)+($apiVersion['serviceRevisionNumber']);
      } else {
        return false;
      }
    }
    if (1 === preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $apiVersion, $matches)) {
      return ($matches[1]*1000000)+($matches[2]*1000)+($matches[3]);
    } else {
      return false;
    }
  } // END createServiceVersionBenchmark()

  //----------------------------------------------------------------------------

  public function getServiceVersionBenchmark()
  {
    if (is_null($this->serviceVersionBenchmark) or false === $this->serviceVersionBenchmark) {
      if (false !== ($serviceVersion = $this->getApiVersion())) {
        $this->serviceVersionBenchmark = $this->createServiceVersionBenchmark($serviceVersion);
      }
    }
    return $this->serviceVersionBenchmark;
  } // END getServiceVersionBenchmark()

  //----------------------------------------------------------------------------

  public function isServiceVersionLowerThan($serviceVersion)
  {
    $serviceVersionBenchmark = $this->getServiceVersionBenchmark();
    if ( ! is_null($serviceVersionBenchmark) and false !== $serviceVersionBenchmark) {
      if (false !== ($serviceVersionCompareBenchmark = $this->createServiceVersionBenchmark($serviceVersion))) {
        return $serviceVersionBenchmark < $serviceVersionCompareBenchmark;
      }
    }
    throw new MplusQAPIException('Can\'t check API Service version compatibility.');
  } // END isServiceVersionLowerThan()

  //----------------------------------------------------------------------------

  public function isServiceVersionGreaterThan($serviceVersion)
  {
    $serviceVersionBenchmark = $this->getServiceVersionBenchmark();
    if ( ! is_null($serviceVersionBenchmark) and false !== $serviceVersionBenchmark) {
      if (false !== ($serviceVersionCompareBenchmark = $this->createServiceVersionBenchmark($serviceVersion))) {
        return $serviceVersionBenchmark > $serviceVersionCompareBenchmark;
      }
    }
    throw new MplusQAPIException('Can\'t check API Service version compatibility.');
  } // END isServiceVersionGreaterThan()

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

  protected function checkApiVersion()
  {
    $compatible = true;
    if (false !== ($api_version = $this->getApiVersion())) {
      if ($api_version['majorNumber'] < $this->MIN_API_VERSION_MAJOR || $api_version['majorNumber'] > $this->MAX_API_VERSION_MAJOR) {
        $compatible = false;
      }
      if ($api_version['majorNumber'] > $this->MIN_API_VERSION_MAJOR && $api_version['majorNumber'] < $this->MAX_API_VERSION_MAJOR) {
        $compatible = true;
      }
      if ($api_version['majorNumber'] == $this->MIN_API_VERSION_MAJOR || $api_version['majorNumber'] == $this->MAX_API_VERSION_MAJOR) {
        if ($api_version['majorNumber'] == $this->MIN_API_VERSION_MAJOR && $this->MIN_API_VERSION_MAJOR < $this->MAX_API_VERSION_MAJOR) {
          $this->MAX_API_VERSION_MINOR = 999999999;
          $this->MAX_API_VERSION_REVIS = 999999999;
        }
        if ($api_version['majorNumber'] == $this->MAX_API_VERSION_MAJOR && $this->MIN_API_VERSION_MAJOR < $this->MAX_API_VERSION_MAJOR) {
          $this->MIN_API_VERSION_MINOR = 0;
          $this->MIN_API_VERSION_REVIS = 0;
        }
        if ($api_version['minorNumber'] < $this->MIN_API_VERSION_MINOR || $api_version['minorNumber'] > $this->MAX_API_VERSION_MINOR) {
          $compatible = false;
        }
        if ($api_version['minorNumber'] > $this->MIN_API_VERSION_MINOR && $api_version['minorNumber'] < $this->MAX_API_VERSION_MINOR) {
          $compatible = true;
        }
        if ($api_version['minorNumber'] == $this->MIN_API_VERSION_MINOR || $api_version['minorNumber'] == $this->MAX_API_VERSION_MINOR) {
          if ($api_version['minorNumber'] == $this->MIN_API_VERSION_MINOR && $this->MIN_API_VERSION_MINOR < $this->MAX_API_VERSION_MINOR) {
            $this->MAX_API_VERSION_REVIS = 999999999;
          }
          if ($api_version['minorNumber'] == $this->MAX_API_VERSION_MINOR && $this->MIN_API_VERSION_MINOR < $this->MAX_API_VERSION_MINOR) {
            $this->MIN_API_VERSION_REVIS = 0;
          }
          if ($api_version['revisionNumber'] < $this->MIN_API_VERSION_REVIS || $api_version['revisionNumber'] > $this->MAX_API_VERSION_REVIS) {
            $compatible = false;
          }
        }
      }
    }
    if ( ! $compatible) {
      throw new MplusQAPIException(sprintf('API version %s is not supported. Supported API versions: %s',
          $api_version['majorNumber'].'.'.$api_version['minorNumber'].'.'.$api_version['revisionNumber'], 
          $this->MIN_API_VERSION_MAJOR.'.'.$this->MIN_API_VERSION_MINOR.'.'.$this->MIN_API_VERSION_REVIS
          .' - '
          .$this->MAX_API_VERSION_MAJOR.'.'.$this->MAX_API_VERSION_MINOR.'.'.$this->MAX_API_VERSION_REVIS));
    }
  } // END checkApiVersion()

  //----------------------------------------------------------------------------

  protected function checkFingerprint($location)
  {
    $fingerprint_matches = false;
    $g = stream_context_create(array('ssl' => array('capture_peer_cert' => true)));
    if (false === ($r = @stream_socket_client(str_replace('https', 'ssl', $location), $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $g))) {
      throw new MplusQAPIException('Could not open connection to server.');
    }
    $cont = stream_context_get_params($r);
    if (isset($cont['options']['ssl']['peer_certificate'])) {
      // $certificate_info = openssl_x509_parse($cont['options']['ssl']['peer_certificate']);
      $resource = $cont['options']['ssl']['peer_certificate'];
      $fingerprint = null;
      $output = null;
      if (false !== ($result = openssl_x509_export($resource, $output))) {
        $output = str_replace('-----BEGIN CERTIFICATE-----', '', $output);
        $output = str_replace('-----END CERTIFICATE-----', '', $output);
        $output = base64_decode($output);
        $fingerprint = sha1($output);
        if ($fingerprint == $this->apiFingerprint) {
          $fingerprint_matches = true;
        }
      } else {
        throw new MplusQAPIException('Could not export certificate as string.');
      }
    } else {
      throw new MplusQAPIException('No certificate found at server.');
    }
    return $fingerprint_matches;
  } // END checkFingerprint()

  //----------------------------------------------------------------------------

  public function getApiVersion($attempts=0)
  {
    try {
      $result = $this->client->getApiVersion();
      return $this->parser->parseApiVersion($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getApiVersion($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getApiVersion()

  //----------------------------------------------------------------------------

  public function getDatabaseVersion($attempts=0)
  {
    try {
      $result = $this->client->getDatabaseVersion();
      return $this->parser->parseDatabaseVersion($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getDatabaseVersion($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getDatabaseVersion()

  //----------------------------------------------------------------------------

  public function getTerminalSettings($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getTerminalSettings($this->parser->convertTerminal($terminal));
      return $this->parser->parseTerminalSettings($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTerminalSettings($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTerminalSettings()

  //----------------------------------------------------------------------------

  public function getMaxTableNumber($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getMaxTableNumber($this->parser->convertTerminal($terminal));
      return $this->parser->parseMaxTableNumber($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getMaxTableNumber($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getMaxTableNumber()

  //----------------------------------------------------------------------------

  public function getCurrentSyncMarkers($attempts=0)
  {
    try {
      $result = $this->client->getCurrentSyncMarkers();
      return $this->parser->parseCurrentSyncMarkers($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCurrentSyncMarkers($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getCurrentSyncMarkers()

  //----------------------------------------------------------------------------

  public function getLicenseInformation($attempts=0)
  {
    try {
      $result = $this->client->getLicenseInformation();
      return $this->parser->parseLicenseInformation($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getLicenseInformation($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getLicenseInformation()

  //----------------------------------------------------------------------------

  public function getCustomFieldLists($attempts=0)
  {
    try {
      $result = $this->client->getCustomFieldLists();
      return $this->parser->parseCustomFieldLists($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCustomFieldLists($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getCustomFieldLists()

  //----------------------------------------------------------------------------

  public function getRelationArticleDiscounts($relationNumbers=array(), $articleNumbers=array(), $attempts=0)
  {
    try {
      $result = $this->client->getRelationArticleDiscounts($this->parser->convertGetRelationArticleDiscountsRequest($relationNumbers, $articleNumbers));
      return $this->parser->parseRelationArticleDiscounts($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getRelationArticleDiscounts($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getRelationArticleDiscounts()

  //----------------------------------------------------------------------------

  public function getCardCategories($attempts=0)
  {
    try {
      $result = $this->client->getCardCategories();
      return $this->parser->parseCardCategories($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCardCategories($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getCardCategories()

  //----------------------------------------------------------------------------

  public function getArticleCardLayout($attempts=0)
  {
    try {
      $result = $this->client->getArticleCardLayout();
      return $this->parser->parseArticleCardLayout($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getArticleCardLayout($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getArticleCardLayout()

  //----------------------------------------------------------------------------

  public function getAvailableTerminalList($attempts=0)
  {
    try {
      $result = $this->client->getAvailableTerminalList();
      return $this->parser->parseTerminalList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getAvailableTerminalList($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getAvailableTerminalList()

  //----------------------------------------------------------------------------

  public function getButtonLayout($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getButtonLayout($this->parser->convertTerminal($terminal));
      return $this->parser->parseButtonLayout($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getButtonLayout($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getButtonLayout()

  //----------------------------------------------------------------------------

  public function getArticlesInLayout($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getArticlesInLayout($this->parser->convertTerminal($terminal));
      return $this->parser->parseArticlesInLayout($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getArticlesInLayout($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getArticlesInLayout()

  //----------------------------------------------------------------------------

  public function getActiveEmployeeList($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getActiveEmployeeList($this->parser->convertTerminal($terminal));
      return $this->parser->parseActiveEmployeeList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getActiveEmployeeList($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getActiveEmployeeList()

  //----------------------------------------------------------------------------

  public function getTableList($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getTableList($this->parser->convertTerminal($terminal));
      return $this->parser->parseTableList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTableList($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTableList()

  //----------------------------------------------------------------------------

  public function getTableListV2($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getTableListV2($this->parser->convertTerminal($terminal));
      return $this->parser->parseTableListV2($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTableListV2($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTableListV2()

  //----------------------------------------------------------------------------

  public function getCurrentTableOrders($request=null, $attempts=0)
  {
    try {
      $result = $this->client->getCurrentTableOrders($this->parser->convertGetCurrentTableOrdersRequest($request));
      return $this->parser->parseGetOrdersResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCurrentTableOrders($request, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function getAvailablePaymentMethods($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getAvailablePaymentMethods($this->parser->convertTerminal($terminal));
      return $this->parser->parseAvailablePaymentMethods($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getAvailablePaymentMethods($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getAvailablePaymentMethods()

  //----------------------------------------------------------------------------

  public function getCourseList($terminal, $attempts=0)
  {
    try {
      $result = $this->client->getCourseList($this->parser->convertTerminal($terminal));
      return $this->parser->parseCourseList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCourseList($terminal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getCourseList()

  //----------------------------------------------------------------------------

  public function getVatGroupList($attempts=0)
  {
    try {
      $result = $this->client->getVatGroupList();
      return $this->parser->parseVatGroupList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getVatGroupList($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getVatGroupList()

  //----------------------------------------------------------------------------

  public function getPriceGroupList($attempts=0)
  {
    try {
      $result = $this->client->getPriceGroupList();
      return $this->parser->parsePriceGroupList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPriceGroupList($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPriceGroupList()

  //----------------------------------------------------------------------------

  public function getSalesPriceList($attempts=0)
  {
    try {
      $result = $this->client->getSalesPriceList();
      return $this->parser->parseSalesPriceList($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getSalesPriceList($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getSalesPriceList()

  //----------------------------------------------------------------------------

  public function getDeliveryMethods($attempts=0)
  {
    try {
      $result = $this->client->getDeliveryMethods();
      return $this->parser->parseGetDeliveryMethodsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getDeliveryMethods($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function getDeliveryMethodsV2($request, $attempts=0)
  {
    try {
      $result = $this->client->getDeliveryMethodsV2($this->parser->convertGetDeliveryMethodsV2Request($request));
      return $this->parser->parseGetDeliveryMethodsV2Result($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getDeliveryMethodsV2($request, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function createDeliveryMethod($createDeliveryMethod, $attempts=0)
  {
    try {
      $result = $this->client->createDeliveryMethod($this->parser->convertCreateDeliveryMethodRequest($createDeliveryMethod));
      return $this->parser->parseCreateDeliveryMethodResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createDeliveryMethod($createDeliveryMethod, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function updateDeliveryMethod($updateDeliveryMethod, $attempts=0)
  {
    try {
      $result = $this->client->updateDeliveryMethod($this->parser->convertUpdateDeliveryMethodRequest($updateDeliveryMethod));
      return $this->parser->parseUpdateDeliveryMethodResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->updateDeliveryMethod($updateDeliveryMethod, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function getPaymentMethods($attempts=0)
  {
    try {
      $result = $this->client->getPaymentMethods();
      return $this->parser->parseGetPaymentMethodsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPaymentMethods($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPaymentMethods()

  //----------------------------------------------------------------------------

  public function getPaymentMethodsV2($accountNumber=null)
  {
    try {
      $result = $this->client->getPaymentMethodsV2($this->parser->convertGetPaymentMethodsV2Request($accountNumber));
      return $this->parser->parseGetPaymentMethodsResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPaymentMethodsV2()

  //----------------------------------------------------------------------------

  public function getRetailSpaceRental($retailSpaceRentalNumber=null, $retailSpaceRentalBarcode=null, $attempts=0)
  {
    try {
      $result = $this->client->getRetailSpaceRental($this->parser->convertGetRetailSpaceRentalRequest($retailSpaceRentalNumber, $retailSpaceRentalBarcode));
      return $this->parser->parseGetRetailSpaceRentalResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getRetailSpaceRental($retailSpaceRentalNumber, $retailSpaceRentalBarcode, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getRetailSpaceRental()

  //----------------------------------------------------------------------------

  public function getRetailSpaceRentals($attempts=0)
  {
    try {
      $result = $this->client->getRetailSpaceRentals();
      return $this->parser->parseGetRetailSpaceRentalsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getRetailSpaceRentals($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getRetailSpaceRentals()

  //----------------------------------------------------------------------------

  public function getExchangeRateHistory($sinceHistoryId, $attempts=0)
  {
    try {
      $result = $this->client->getExchangeRateHistory($this->parser->convertGetExchangeRateHistoryRequest($sinceHistoryId));
      return $this->parser->parseGetExchangeRateHistoryResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getExchangeRateHistory($sinceHistoryId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getExchangeRateHistory()

  //----------------------------------------------------------------------------

  public function updateExchangeRate($exchangeRates, $attempts=0)
  {
    try {
      $result = $this->client->updateExchangeRate($this->parser->convertUpdateExchangeRateRequest($exchangeRates));
      return $this->parser->parseUpdateExchangeRateResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->updateExchangeRate($exchangeRates, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateExchangeRate()

  //----------------------------------------------------------------------------

  public function getProducts($articleNumbers = array(), $groupNumbers = array(), $pluNumbers = array(), $changedSinceTimestamp = null, $changedSinceBranchNumber = null, $syncMarker = null, $onlyWebshop = null, $onlyActive = null, $syncMarkerLimit = null, $productNumbers = [], $includeAllArticlesOfSelectedProducts = false, $attempts = 0)
  {
    try {
      $result = $this->client->getProducts($this->parser->convertGetProductsRequest($productNumbers, $articleNumbers, $includeAllArticlesOfSelectedProducts, $groupNumbers, $pluNumbers, $changedSinceTimestamp, $changedSinceBranchNumber, $syncMarker, $onlyWebshop, $onlyActive, $syncMarkerLimit));
      return $this->parser->parseProducts($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getProducts($articleNumbers, $groupNumbers, $pluNumbers, $changedSinceTimestamp, $changedSinceBranchNumber, $syncMarker, $onlyWebshop, $onlyActive, $syncMarkerLimit, $productNumbers, $includeAllArticlesOfSelectedProducts, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getProducts()

  //----------------------------------------------------------------------------

  public function getRelations($relationNumbers=array(), $syncMarker=null, $categoryId=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getRelations($this->parser->convertGetRelationsRequest($relationNumbers, $syncMarker, $categoryId, $syncMarkerLimit));
      return $this->parser->parseRelations($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getRelations($relationNumbers, $syncMarker, $categoryId, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getRelations()

  //----------------------------------------------------------------------------
  
  public function getImages($imageIds=array(), $includeImageData=true, $includeThumbData=true, $attempts=0)
  {
    try {
      $result = $this->client->getImages($this->parser->convertGetImagesRequest($imageIds, $includeImageData, $includeThumbData));
      return $this->parser->parseImages($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getImages($imageIds, $includeImageData, $includeThumbData, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getImages()

  //----------------------------------------------------------------------------

  public function findEmployee($employee, $attempts=0)
  {
    try {
      $result = $this->client->findEmployee($this->parser->convertEmployee($employee));
      return $this->parser->parseFindEmployeeResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->findEmployee($employee, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END findEmployee()

  //----------------------------------------------------------------------------

  public function getEmployee($employeeNumber, $attempts=0)
  {
    try {      
      $result = $this->client->getEmployee($this->parser->convertGeneric('employeeNumber', $employeeNumber));
      return $this->parser->parseEmployee($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getEmployee($employeeNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getEmployees()

  //----------------------------------------------------------------------------

  public function getEmployees($employeeNumbers=array(), $syncMarker=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getEmployees($this->parser->convertGetEmployeesRequest($employeeNumbers, $syncMarker, $syncMarkerLimit));
      return $this->parser->parseEmployees($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getEmployees($employeeNumbers, $syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getEmployees()

  //----------------------------------------------------------------------------

  public function createEmployee($employee)
  {
    try {
      $result = $this->client->createEmployee($this->parser->convertEmployee($employee));
      return $this->parser->parseCreateEmployeeResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createEmployee()

  //----------------------------------------------------------------------------

  public function updateEmployee($employee)
  {
    try {
      $result = $this->client->updateEmployee($this->parser->convertEmployee($employee));
      return $this->parser->parseUpdateEmployeeResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateEmployee()

  //----------------------------------------------------------------------------

  public function createProduct($product)
  {
    try {
      $add_default_fields = $this->isServiceVersionLowerThan('4.0.0');
      $result = $this->client->createProduct($this->parser->convertProduct($product, $add_default_fields));
      return $this->parser->parseCreateProductResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createProduct()

  //----------------------------------------------------------------------------

  public function updateProduct($product)
  {
    try {
      $add_default_fields = $this->isServiceVersionLowerThan('4.0.0');
      $result = $this->client->updateProduct($this->parser->convertProduct($product, $add_default_fields));
      return $this->parser->parseUpdateProductResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateProduct()

  //----------------------------------------------------------------------------

  public function getArticleGroups($groupNumbers=array(), $syncMarker=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getArticleGroups($this->parser->convertGetArticleGroupsRequest($groupNumbers, $syncMarker, $syncMarkerLimit));
      return $this->parser->parseArticleGroups($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getArticleGroups($groupNumbers, $syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getArticleGroups()

  //----------------------------------------------------------------------------

  public function getArticleGroupChanges($groupNumbers=null, $syncMarker=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getArticleGroupChanges($this->parser->convertGetArticleGroupChangesRequest($groupNumbers, $syncMarker, $syncMarkerLimit));
      return $this->parser->parseChangedArticleGroups($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getArticleGroupChanges($groupNumbers, $syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getArticleGroupChanges()

  //----------------------------------------------------------------------------

  public function saveArticleGroups($articleGroupList = array())
  {
    try {
      $result = $this->client->saveArticleGroups($this->parser->convertSaveArticleGroupsRequest($articleGroupList));
      return $this->parser->parseSaveArticleGroupsResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getArticleGroups()

  //----------------------------------------------------------------------------

  public function getStock($branchNumber, $articleNumbers=array(), $stockId=null, $attempts=0)
  {
    try {
      $result = $this->client->getStock($this->parser->convertGetStockRequest($branchNumber, $articleNumbers, $stockId));
      return $this->parser->parseStock($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getStock($branchNumber, $articleNumbers, $stockId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getStock()

  //----------------------------------------------------------------------------

  public function getStockHistory($branchNumber, $articleNumbers=array(), $sinceStockId=null, $fromFinancialDateTime=null, $throughFinancialDateTime=null, $attempts=0)
  {
    try {
      $result = $this->client->getStockHistory($this->parser->convertGetStockHistoryRequest($branchNumber, $articleNumbers, $sinceStockId, $fromFinancialDateTime, $throughFinancialDateTime));
      return $this->parser->parseStockHistory($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getStockHistory($branchNumber, $articleNumbers, $sinceStockId, $fromFinancialDateTime, $throughFinancialDateTime, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getStockHistory()

  //----------------------------------------------------------------------------

  public function updateStock($branchNumber, $articleNumber, $amountChanged)
  {
    try {
      $result = $this->client->updateStock($this->parser->convertUpdateStockRequest($branchNumber, $articleNumber, $amountChanged));
      return $this->parser->parseUpdateStockResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateStock()

  //----------------------------------------------------------------------------

  public function setStock($branchNumber, $articleNumber, $amount)
  {
    try {
      $result = $this->client->setStock($this->parser->convertSetStockRequest($branchNumber, $articleNumber, $amount));
      return $this->parser->parseSetStockResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END setStock()

  //----------------------------------------------------------------------------

  public function getShifts($fromFinancialDate, $throughFinancialDate, $branchNumbers=array(), $employeeNumbers=array(), $attempts=0)
  {
    try {
      $result = $this->client->getShifts($this->parser->convertGetShiftsRequest($fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers));
      return $this->parser->parseShifts($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getShifts($fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getShifts()

  //----------------------------------------------------------------------------

  public function findOrder($extOrderId, $attempts=0)
  {
    try {
      $result = $this->client->findOrder($this->parser->convertExtOrderId($extOrderId));
      return $this->parser->parseOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->findOrder($extOrderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END findOrder()

  //----------------------------------------------------------------------------

  public function payOrder($orderId, $prepay, $paymentList)
  {
    try {
      $result = $this->client->payOrder($this->parser->convertPayOrderRequest($orderId, $prepay, $paymentList));
      return $this->parser->parsePayOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END payOrder()

  //----------------------------------------------------------------------------

  public function payTableOrder($terminal, $order, $paymentList, $keepTableName = null, $releaseTable = null)
  {
    try {
      $result = $this->client->payTableOrderV2($this->parser->convertPayTableOrderRequest($terminal, $order, $paymentList, $keepTableName, $releaseTable));
      return $this->parser->parsePayTableOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END payTableOrder()

  //----------------------------------------------------------------------------

  public function prepayTableOrder($terminal, $order, $paymentList, $prepayAmount, $releaseTable = null)
  {
    try {
      $result = $this->client->prepayTableOrderV2($this->parser->convertPrepayTableOrderRequest($terminal, $order, $paymentList, $prepayAmount, $releaseTable));
      return $this->parser->parsePrepayTableOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END prepayTableOrder()

  //----------------------------------------------------------------------------

  public function payInvoice($invoiceId, $paymentList)
  {
    try {
      $result = $this->client->payInvoice($this->parser->convertPayInvoiceRequest($invoiceId, $paymentList));
      return $this->parser->parsePayInvoiceResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END payInvoice()

  //----------------------------------------------------------------------------

  public function deliverOrder($orderId)
  {
    try {
      $result = $this->client->deliverOrder($this->parser->convertDeliverOrderRequest($orderId));
      return $this->parser->parseDeliverOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END deliverOrder()

  //----------------------------------------------------------------------------

  public function deliverOrderV2($orderDelivery, $attempts=0)
  {
    try {
      $result = $this->client->deliverOrderV2($this->parser->convertDeliverOrderV2Request($orderDelivery));
      return $this->parser->parseDeliverOrderV2Result($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->deliverOrderV2($orderDelivery, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END deliverOrderV2()

  //----------------------------------------------------------------------------

  public function getProposals($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $supplierRelationNumbers=null, $syncMarkerLimit=null, $activityId=null, $attempts=0)
  {
    try {
      $result = $this->client->getProposals($this->parser->convertGetProposalsRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $activityId));
      return $this->parser->parseGetProposalsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getProposals($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $syncMarkerLimit, $activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getProposals()

  //----------------------------------------------------------------------------

  public function getProposal($proposalId, $attempts=0)
  {
    try {
      $result = $this->client->getProposal($this->parser->convertProposalId($proposalId));
      return $this->parser->parseProposalResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getProposal($proposalId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getProposal()

  //----------------------------------------------------------------------------

  public function getOrder($orderId, $attempts=0)
  {
    try {
      $result = $this->client->getOrder($this->parser->convertOrderId($orderId));
      return $this->parser->parseOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getOrder($orderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getOrder()

  //----------------------------------------------------------------------------

  public function getPackingSlips($syncMarker, $syncMarkerLimit=null, $fromFinancialDate=null, $throughFinancialDate=null, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $supplierRelationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $activityId=null, $attempts=0)
  {
    try {
      $result = $this->client->getPackingSlips($this->parser->convertGetPackingSlipsRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $supplierRelationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $activityId));
      return $this->parser->parseGetPackingSlipsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPackingSlips($syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPackingSlips()

  //----------------------------------------------------------------------------

  public function getPackingSlipsByOrder($orderId, $attempts=0)
  {
    try {
      $result = $this->client->getPackingSlipsByOrder($this->parser->convertGetPackingSlipsByOrderRequest($orderId));
      return $this->parser->parseGetPackingSlipsByOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPackingSlipsByOrder($orderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPackingSlipsByOrder()

  //----------------------------------------------------------------------------

  public function getOrders($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $syncMarkerLimit=null, $activityId=null, $attempts=0)
  {
    try {
      $result = $this->client->getOrders($this->parser->convertGetOrdersRequest($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, null, $activityId));
      return $this->parser->parseGetOrdersResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getOrders($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getOrders()

  //----------------------------------------------------------------------------

  public function getOrderChanges($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $syncMarkerLimit=null, $orderTypeList=null, $activityId=null, $attempts=0)
  {
    try {
      $result = $this->client->getOrderChanges($this->parser->convertGetOrdersRequest($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $orderTypeList, $activityId));
      return $this->parser->parseGetOrderChangesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getOrderChanges($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $orderTypeList, $activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getOrderChanges()

  //----------------------------------------------------------------------------

  public function getOrderCategories($attempts=0)
  {
    try {
      $result = $this->client->getOrderCategories();
      return $this->parser->parseOrderCategories($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getOrderCategories($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getOrderCategories()

  //----------------------------------------------------------------------------

  public function getInterbranchOrders($syncMarker, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getInterbranchOrders($this->parser->convertGetInterbranchOrdersRequest($syncMarker, $syncMarkerLimit));
      return $this->parser->parseGetInterbranchOrdersResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getInterbranchOrders($syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getInterbranchOrders()

  //----------------------------------------------------------------------------

  public function getInterbranchShipments($syncMarker, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getInterbranchShipments($this->parser->convertGetInterbranchShipmentsRequest($syncMarker, $syncMarkerLimit));
      return $this->parser->parseGetInterbranchShipmentsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getInterbranchShipments($syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function getInterbranchDeliveries($syncMarker, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getInterbranchDeliveries($this->parser->convertGetInterbranchDeliveriesRequest($syncMarker, $syncMarkerLimit));
      return $this->parser->parseGetInterbranchDeliveriesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getInterbranchDeliveries($syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function createInterbranchOrder($orderRequest, $branchNumber, $workplaceNumber, $attempts=0)
  {
    try {
      $result = $this->client->createInterbranchOrder($this->parser->convertCreateInterbranchOrderRequest($orderRequest, $branchNumber, $workplaceNumber));
      return $this->parser->parseCreateInterbranchOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createInterbranchOrder($orderRequest, $branchNumber, $workplaceNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function createInterbranchShipment($shipmentRequest, $branchNumber, $workplaceNumber, $attempts=0)
  {
    try {
      $result = $this->client->createInterbranchShipment($this->parser->convertCreateInterbranchShipmentRequest($shipmentRequest, $branchNumber, $workplaceNumber));
      return $this->parser->parseCreateInterbranchShipmentResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createInterbranchShipment($shipmentRequest, $branchNumber, $workplaceNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function createInterbranchDelivery($shipmentRequest, $branchNumber, $workplaceNumber, $attempts=0)
  {
    try {
      $result = $this->client->createInterbranchDelivery($this->parser->convertCreateInterbranchDeliveryRequest($shipmentRequest, $branchNumber, $workplaceNumber));
      return $this->parser->parseCreateInterbranchDeliveryResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createInterbranchDelivery($shipmentRequest, $branchNumber, $workplaceNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function claimInterbranchOrder($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber, $attempts=0)
  {
    try {
      $result = $this->client->claimInterbranchOrder($this->parser->convertClaimInterbranchOrderRequest($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber));
      return $this->parser->parseClaimInterbranchOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->claimInterbranchOrder($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function releaseInterbranchOrder($interbranchOrderNumber, $attempts=0)
  {
    try {
      $result = $this->client->releaseInterbranchOrder($this->parser->convertReleaseInterbranchOrderRequest($interbranchOrderNumber));
      return $this->parser->parseReleaseInterbranchOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->releaseInterbranchOrder($interbranchOrderNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function shipInterbranchOrder($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber, $attempts=0)
  {
    try {
      $result = $this->client->shipInterbranchOrder($this->parser->convertShipInterbranchOrderRequest($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber));
      return $this->parser->parseShipInterbranchOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->shipInterbranchOrder($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function deliverInterbranchShipment($interbranchShipmentNumber, $branchNumber, $workplaceNumber, $attempts=0)
  {
    try {
      $result = $this->client->deliverInterbranchShipment($this->parser->convertDeliverInterbranchShipmentRequest($interbranchShipmentNumber, $branchNumber, $workplaceNumber));
      return $this->parser->parseDeliverInterbranchShipmentResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->deliverInterbranchShipment($interbranchOrderNumber, $branchNumber, $workplaceNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function getReceipts($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $supplierRelationNumbers=null, $syncMarkerLimit=null, $includeOrderReferences=null, $activityId=null, $attempts=0)
  {
    try {
      $result = $this->client->getReceipts($this->parser->convertGetReceiptsRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $includeOrderReferences, $activityId));
      return $this->parser->parseGetReceiptsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getReceipts($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $syncMarkerLimit, $includeOrderReferences, $activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getReceipts()

  //----------------------------------------------------------------------------

  public function getReceiptsByOrder($orderId, $attempts=0)
  {
    try {
      $result = $this->client->getReceiptsByOrder($this->parser->convertOrderId($orderId));
      return $this->parser->parseReceiptsByOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getReceiptsByOrder($orderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getReceiptsByOrder()

  //----------------------------------------------------------------------------

  public function getReceiptsByCashCount($cashCountId, $attempts=0)
  {
    try {
      $result = $this->client->getReceiptsByCashCount($this->parser->convertGetReceiptsByCashCountRequest($cashCountId));
      return $this->parser->parseReceiptsByCashCountResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getReceiptsByCashCount($orderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getReceiptsByCashCount()

  //----------------------------------------------------------------------------

  public function getInvoices($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $supplierRelationNumbers=null, $finalizeInvoices=null, $syncMarkerLimit=null, $activityId=null, $attempts=0)
  {
    try {
      $result = $this->client->getInvoices($this->parser->convertGetInvoicesRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $finalizeInvoices, $activityId));
      return $this->parser->parseGetInvoicesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getInvoices($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $finalizeInvoices, $syncMarkerLimit, $activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getInvoices()

  //----------------------------------------------------------------------------

  public function getInvoice($invoiceId, $attempts=0)
  {
    try {
      $result = $this->client->getInvoice($this->parser->convertInvoiceId($invoiceId));
      return $this->parser->parseInvoiceResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getInvoice($invoiceId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getInvoice()

  //----------------------------------------------------------------------------

  public function creditInvoice($invoiceId, $attempts=0)
  {
    try {
      $result = $this->client->creditInvoice($this->parser->convertInvoiceId($invoiceId));
      return $this->parser->parseCreditInvoiceResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->creditInvoice($invoiceId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END creditInvoice()

  //----------------------------------------------------------------------------

  public function findInvoice($extInvoiceId, $attempts=0)
  {
    try {
      $result = $this->client->findInvoice($this->parser->convertExtInvoiceId($extInvoiceId));
      return $this->parser->parseInvoiceResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->findInvoice($extInvoiceId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END findInvoice()

  //----------------------------------------------------------------------------

  public function getJournals($fromFinancialDate, $throughFinancialDate, $branchNumbers, $journalFilterList=array(), $reference=null, $attempts=0)
  {
    try {
      $result = $this->client->getJournals($this->parser->convertGetJournalsRequest($fromFinancialDate, $throughFinancialDate, $branchNumbers, $journalFilterList, $reference));
      return $this->parser->parseGetJournalsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getJournals($fromFinancialDate, $throughFinancialDate, $branchNumbers, $journalFilterList, $reference, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getJournals()

  //----------------------------------------------------------------------------

  public function getFinancialJournal($fromFinancialDate, $throughFinancialDate, $reference=null, $attempts=0)
  {
    try {
      $result = $this->client->getFinancialJournal($this->parser->convertGetFinancialJournalRequest($fromFinancialDate, $throughFinancialDate, $reference));
      return $this->parser->parseGetFinancialJournalResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getFinancialJournal($fromFinancialDate, $throughFinancialDate, $reference, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getFinancialJournal()

  //----------------------------------------------------------------------------

  public function getFinancialJournalByCashCount($cashCountId, $reference=null, $attempts=0)
  {
    try {
      $result = $this->client->getFinancialJournalByCashCount($this->parser->convertGetFinancialJournalByCashCountRequest($cashCountId, $reference));
      return $this->parser->parseGetFinancialJournalResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getFinancialJournalByCashCount($cashCountId, $reference, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getFinancialJournalByCashCount()

  //----------------------------------------------------------------------------

  public function getCashCountList($fromFinancialDate, $throughFinancialDate, $sinceCashCount=null, $attempts=0)
  {
    try {
      $result = $this->client->getCashCountList($this->parser->convertGetCashCountListRequest($fromFinancialDate, $throughFinancialDate, $sinceCashCount));
      return $this->parser->parseGetCashCountListResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCashCountList($fromFinancialDate, $throughFinancialDate, $sinceCashCount, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getCashCountList()

  //----------------------------------------------------------------------------

  public function getCashDrawerBalancingList($fromFinancialDate, $throughFinancialDate, $syncMarker=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getCashDrawerBalancingList($this->parser->convertGetCashDrawerBalancingListRequest($fromFinancialDate, $throughFinancialDate, $syncMarker, $syncMarkerLimit));
      return $this->parser->parseGetCashDrawerBalancingListResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getCashDrawerBalancingList($fromFinancialDate, $throughFinancialDate, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getCashDrawerBalancingList()

  //----------------------------------------------------------------------------

  public function getTurnoverGroups($attempts=0)
  {
    try {
      $result = $this->client->getTurnoverGroups();
      return $this->parser->parseGetTurnoverGroupsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTurnoverGroups($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTurnoverGroups()

  //----------------------------------------------------------------------------

  public function updateTurnoverGroups($turnoverGroups, $attempts=0)
  {
    try {
      $result = $this->client->updateTurnoverGroups($this->parser->convertUpdateTurnoverGroupsRequest($turnoverGroups));
      return $this->parser->parseUpdateTurnoverGroupsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->updateTurnoverGroups($turnoverGroups, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateTurnoverGroups()

  //----------------------------------------------------------------------------

  public function getAllergens($attempts=0)
  {
    try {
      $result = $this->client->getAllergens();
      return $this->parser->parseGetAllergensResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getAllergens($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getAllergens()

  //----------------------------------------------------------------------------

  public function getWebhookConsumers($attempts=0)
  {
    try {
      $result = $this->client->getWebhookConsumers();
      return $this->parser->parseGetWebhookConsumersResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getWebhookConsumers($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getWebhookConsumers()

  //----------------------------------------------------------------------------

  public function getTicketCounterSales($syncMarker, $syncMarkerLimit=null, $attempts=0)
  {
    try {      
      $result = $this->client->getTicketCounterSales($this->parser->convertGetTicketCounterSalesRequest($syncMarker, $syncMarkerLimit));
      return $this->parser->parseGetTicketCounterSalesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTicketCounterSales($syncMarker, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTicketCounterSales()

  //----------------------------------------------------------------------------

  public function getConfiguration($branchNumber=null, $workplaceNumber=null, $group=null, $subgroup=null, $key=null, $attempts=0)
  {
    try {      
      $result = $this->client->getConfiguration($this->parser->convertGetConfigurationRequest($branchNumber, $workplaceNumber, $group, $subgroup, $key));
      return $this->parser->parseGetConfigurationResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getConfiguration($branchNumber, $workplaceNumber, $group, $subgroup, $key, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getConfiguration()

  //----------------------------------------------------------------------------

  public function updateConfiguration($configuration, $attempts=0)
  {
    try {      
      $result = $this->client->updateConfiguration($this->parser->convertUpdateConfigurationRequest($configuration));
      return $this->parser->parseUpdateConfigurationResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->updateConfiguration($configuration, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateConfiguration()

  //----------------------------------------------------------------------------

  public function getPurchaseOrders($syncMarker, $fromOrderDate, $throughOrderDate, $fromDeliveryDate=null, $throughDeliveryDate=null, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getPurchaseOrders($this->parser->convertGetPurchaseOrdersRequest($syncMarker, $fromOrderDate, $throughOrderDate, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit));
      return $this->parser->parseGetPurchaseOrdersResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPurchaseOrders($syncMarker, $fromFinancialDate, $throughFinancialDate, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPurchaseOrders()

  //----------------------------------------------------------------------------

  public function getPurchaseOrdersV2($syncMarker, $fromOrderDate, $throughOrderDate, $fromDeliveryDate=null, $throughDeliveryDate=null, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getPurchaseOrdersV2($this->parser->convertGetPurchaseOrdersRequest($syncMarker, $fromOrderDate, $throughOrderDate, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit));
      return $this->parser->parseGetPurchaseOrdersResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPurchaseOrdersV2($syncMarker, $fromFinancialDate, $throughFinancialDate, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPurchaseOrders()

  //----------------------------------------------------------------------------

  public function getPurchaseDeliveries($syncMarker, $fromDeliveryDate=null, $throughDeliveryDate=null, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getPurchaseDeliveries($this->parser->convertGetPurchaseDeliveriesRequest($syncMarker, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit));
      return $this->parser->parseGetPurchaseDeliveriesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPurchaseDeliveries($syncMarker, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPurchaseDeliveries()

  //----------------------------------------------------------------------------

  public function getPurchaseDeliveriesV2($syncMarker, $fromDeliveryDate=null, $throughDeliveryDate=null, $branchNumbers=null, $employeeNumbers=null, $relationNumbers=null, $articleNumbers=null, $articleTurnoverGroups=null, $articlePluNumbers=null, $articleBarcodes=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getPurchaseDeliveriesV2($this->parser->convertGetPurchaseDeliveriesRequest($syncMarker, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit));
      return $this->parser->parseGetPurchaseDeliveriesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getPurchaseDeliveriesV2($syncMarker, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getPurchaseDeliveriesV2()

  //----------------------------------------------------------------------------

  public function getBranches($attempts=0)
  {
    try {
      $result = $this->client->getBranches();
      return $this->parser->parseGetBranchesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getBranches($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getBranches()

  //----------------------------------------------------------------------------

  public function createOrder($order)
  {
    try {
      $result = $this->client->createOrder($this->parser->convertOrder($order));
      return $this->parser->parseCreateOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createOrder()

  //----------------------------------------------------------------------------

  public function createOrderV2($order, $applySalesAndActions=null, $applySalesPrices=null, $applyPriceGroups=null)
  {
    try {
      $result = $this->client->createOrderV2($this->parser->convertCreateOrderV2Request($order, $applySalesAndActions, $applySalesPrices, $applyPriceGroups));
      return $this->parser->parseCreateOrderV2Result($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

  public function updateOrder($order)
  {
    try {
      if ( ! isset($order['orderId'])) {
        throw new MplusQAPIException('No orderId set.');
      }
      $result = $this->client->updateOrder($this->parser->convertOrder($order));
      return $this->parser->parseUpdateOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateOrder()

  //----------------------------------------------------------------------------

  public function saveOrder($order)
  {
    try {
      $result = $this->client->saveOrder($this->parser->convertOrder($order));
      return $this->parser->parseSaveOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END saveOrder()

  //----------------------------------------------------------------------------

  public function queueBranchOrder($order, $attempts=0)
  {
    try {
      if (false !== ($result = $this->client->queueBranchOrder($this->parser->convertOrder($order)))) {
      return $this->parser->parseQueueBranchOrderResult($result);
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->queueBranchOrder($order, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END queueBranchOrder()

  //----------------------------------------------------------------------------
  private function RetryCall($func)
  {
    $attempts = 0;
    while ($attempts < 3) {
      try {
        return $func();
      } catch (SoapFault $e) {
        $msg = $e->getMessage();
        if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
          sleep(1);
          ++$attempts;
        } else {
          throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
        }
      } catch (Exception $e) {
        throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
      }
    }
  }

  public function queueBranchOrderPayment($orderId, $paymentList)
  {
    $request = $this->parser->convertQueueBranchOrderPaymentOrderRequest($orderId, $paymentList);
    return $this->RetryCall( 
      function() use ($request) {
        $result = $this->client->queueBranchOrderPayment($request);
        if (false !== $result) {
          return $this->parser->parseQueueBranchOrderPaymentResult($result);
        }
      }
    );
  }
  //----------------------------------------------------------------------------

  public function cancelOrder($orderId, $attempts=0)
  {
    try {
      if (false !== ($result = $this->client->cancelOrder($this->parser->convertOrderId($orderId)))) {
      return $this->parser->parseCancelOrderResult($result);
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->cancelOrder($orderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END cancelOrder()

  //----------------------------------------------------------------------------

  public function cancelProposal($proposalId, $attempts=0)
  {
    try {
      if (false !== ($result = $this->client->cancelProposal($this->parser->convertProposalId($proposalId)))) {
        return $this->parser->parseCancelProposalResult($result);
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->cancelProposal($proposalId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END cancelProposal()

  //----------------------------------------------------------------------------

  public function createOrderFromProposal($proposalId, $attempts=0)
  {
    try {
      if (false !== ($result = $this->client->createOrderFromProposal($this->parser->convertProposalId($proposalId)))) {
        return $this->parser->parseCreateOrderFromProposalResult($result);
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createOrderFromProposal($proposalId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createOrderFromProposal()

  //----------------------------------------------------------------------------

  public function createInvoiceFromProposal($proposalId, $attempts=0)
  {
    try {
      if (false !== ($result = $this->client->createInvoiceFromProposal($this->parser->convertProposalId($proposalId)))) {
        return $this->parser->parseCreateInvoiceFromProposalResult($result);
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createInvoiceFromProposal($proposalId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createInvoiceFromProposal()

  //----------------------------------------------------------------------------

  public function saveProposal($proposal, $attempts=0)
  {
    try {
      if (false !== ($result = $this->client->saveProposal($this->parser->convertProposal($proposal)))) {
        return $this->parser->parseSaveProposalResult($result);
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->saveProposal($proposal, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END saveProposal()

  //----------------------------------------------------------------------------

  public function saveInvoice($invoice, $attempts=0)
  {
    try {
      if (false !== ($result = @$this->client->saveInvoice($this->parser->convertInvoice($invoice)))) {
        return $this->parser->parseSaveInvoiceResult($result);
      } else {
        throw new MplusQAPIException('Error while saving invoice: '.json_encode($invoice));
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->saveInvoice($invoice, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END saveInvoice()

  //----------------------------------------------------------------------------

  public function savePurchaseOrder($purchaseOrder, $attempts=0)
  {
    try {
      if (false !== ($result = @$this->client->savePurchaseOrder($this->parser->convertPurchaseOrder($purchaseOrder)))) {
        return $this->parser->parseSavePurchaseOrderResult($result);
      } else {
        throw new MplusQAPIException('Error while saving purchase order: '.json_encode($purchaseOrder));
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->savePurchaseOrder($purchaseOrder, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END savePurchaseOrder()

  //----------------------------------------------------------------------------

  public function savePurchaseOrderV2($purchaseOrder, $attempts=0)
  {
    try {
      // i($this->parser->convertPurchaseOrderV2($purchaseOrder));
      if (false !== ($result = @$this->client->savePurchaseOrderV2($this->parser->convertPurchaseOrderV2($purchaseOrder)))) {
        return $this->parser->parseSavePurchaseOrderResult($result);
      } else {
        throw new MplusQAPIException('Error while saving purchase order: '.json_encode($purchaseOrder));
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->savePurchaseOrderV2($purchaseOrder, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END savePurchaseOrder()

  //----------------------------------------------------------------------------

  public function savePurchaseDelivery($purchaseDelivery, $attempts=0)
  {
    try {
      if (false !== ($result = @$this->client->savePurchaseDelivery($this->parser->convertPurchaseDelivery($purchaseDelivery)))) {
        return $this->parser->parseSavePurchaseDeliveryResult($result);
      } else {
        throw new MplusQAPIException('Error while saving invoice: '.json_encode($invoice));
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->savePurchaseDelivery($purchaseDelivery, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END savePurchaseDelivery()

  //----------------------------------------------------------------------------

  public function savePurchaseDeliveryV2($purchaseDelivery, $attempts=0)
  {
    try {
      if (false !== ($result = @$this->client->savePurchaseDeliveryV2($this->parser->convertPurchaseDeliveryV2($purchaseDelivery)))) {
        return $this->parser->parseSavePurchaseDeliveryResult($result);
      } else {
        throw new MplusQAPIException('Error while saving invoice: '.json_encode($invoice));
      }
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->savePurchaseDeliveryV2($purchaseDelivery, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END savePurchaseDeliveryV2()

  //----------------------------------------------------------------------------

  public function findRelation($relation, $attempts=0)
  {
    try {
      $result = $this->client->findRelation($this->parser->convertRelation($relation));
      return $this->parser->parseFindRelationResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->findRelation($relation, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END findRelation()

  public function createRelation($relation)
  {
    try {
      $result = $this->client->createRelation($this->parser->convertRelation($relation));
      return $this->parser->parseCreateRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createRelation()

  public function updateRelation($relation)
  {
    try {
      $result = $this->client->updateRelation($this->parser->convertRelation($relation));
      return $this->parser->parseUpdateRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateRelation()

  public function getRelation($relationNumber, $attempts=0)
  {
    try {
      $result = $this->client->getRelation($this->parser->convertGeneric('relationNumber', $relationNumber));
      return $this->parser->parseGetRelationResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->findRelation($relation, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getRelation()

  //----------------------------------------------------------------------------

  public function adjustPoints($relationNumber, $pointsAdjustment)
  {
    try {
      $result = $this->client->adjustPoints($this->parser->convertAdjustPointsRequest($relationNumber, $pointsAdjustment));
      return $this->parser->parseAdjustPointsResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END adjustPoints()

  //----------------------------------------------------------------------------
  
  public function getRelationPoints($relationNumbers=array(), $syncMarker=null, $syncMarkerLimit=null, $attempts=0)
  {
    try {
      $result = $this->client->getRelationPoints(
              $this->parser->convertGetRelationPointsRequest($relationNumbers, $syncMarker, $syncMarkerLimit));
      return $this->parser->parseRelationPoints($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getRelationPoints($relationNumbers, $syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }     
  }

  //----------------------------------------------------------------------------

  public function registerTerminal($terminal, $forceRegistration)
  {
    try {
      $result = $this->client->registerTerminal($this->parser->convertRegisterTerminalRequest($terminal, $forceRegistration));
      return $this->parser->parseRegisterTerminalResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException('SoapFault occurred: '.$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END registerTerminal()

  //----------------------------------------------------------------------------

  public function getWordAliases($locale='nl_NL', $attempts=0)
  {
    try {
      $result = $this->client->getWordAliases($this->parser->convertGetWordAliasesRequest($locale));
      return $this->parser->parseWordAliases($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getWordAliases($locale, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getWordAliases()

  //----------------------------------------------------------------------------

  public function getTableOrder($terminal, $branchNumber, $tableNumber, $attempts=0)
  {
    try {
      $result = $this->client->getTableOrder($this->parser->convertGetTableOrderRequest($terminal, $branchNumber, $tableNumber));
      return $this->parser->parseGetTableOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTableOrder($terminal, $branchNumber, $tableNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTableOrder()

  //----------------------------------------------------------------------------

  public function getTableOrderV2($terminal, $tableNumber, $claimTable=null, $attempts=0)
  {
    try {
      $result = $this->client->getTableOrderV2($this->parser->convertGetTableOrderV2Request($terminal, $terminal['branchNumber'], $tableNumber, $claimTable));
      return $this->parser->parseGetTableOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTableOrderV2($terminal, $branchNumber, $tableNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTableOrderV2()

  //----------------------------------------------------------------------------

  public function findTableOrder($terminal, $extOrderId, $attempts=0)
  {
    try {
      $result = $this->client->findTableOrder($this->parser->convertFindTableOrderRequest($terminal, $extOrderId));
      return $this->parser->parseGetTableOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->findTableOrder($terminal, $extOrderId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END findTableOrder()

  //----------------------------------------------------------------------------

  public function getTableOrderCourseList($terminal, $branchNumber, $tableNumber, $attempts=0)
  {
    try {
      $result = $this->client->getTableOrderCourseList($this->parser->convertGetTableOrderCourseListRequest($terminal, $branchNumber, $tableNumber));
      return $this->parser->parseGetTableOrderCourseListResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getTableOrderCourseList($terminal, $branchNumber, $tableNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTableOrderCourseList()

  //----------------------------------------------------------------------------

  public function saveTableOrder($terminal, $order, $attempts=0)
  {
    try {
      $result = $this->client->saveTableOrder($this->parser->convertSaveTableOrder($terminal, $order));
      return $this->parser->parseSaveTableOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->saveTableOrder($terminal, $order, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getTableOrder()

  //----------------------------------------------------------------------------

  public function moveTableOrder($terminal, $order, $tableNumber, $attempts=0)
  {
    try {
      $result = $this->client->moveTableOrder($this->parser->convertMoveTableOrderRequest($terminal, $order, $tableNumber));
      return $this->parser->parseMoveTableOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->moveTableOrder($terminal, $order, $tableNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END cancelTableOrder()

  //----------------------------------------------------------------------------

  public function cancelTableOrder($terminal, $branchNumber, $tableNumber, $attempts=0)
  {
    try {
      $result = $this->client->cancelTableOrder($this->parser->convertGetTableOrderRequest($terminal, $branchNumber, $tableNumber));
      return $this->parser->parseCancelOrderResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->cancelTableOrder($terminal, $branchNumber, $tableNumber, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END cancelTableOrder()

  //----------------------------------------------------------------------------

  public function sendMessage($branchNumber, $terminalNumber, $text, $sender=null, $messageType=null, $attempts=0)
  {
    try {
      $forceBranchTerminalNumber = $this->isApiVersionLowerThan('1.0.0');
      $result = $this->client->sendMessage($this->parser->convertSendMessageRequest($branchNumber, $terminalNumber, $text, $sender, $messageType, $forceBranchTerminalNumber));
      return $this->parser->parseSendMessageResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->sendMessage($branchNumber, $terminalNumber, $text, $sender, $messageType, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END sendMessage()

  //----------------------------------------------------------------------------

  public function encryptString($plainString, $encryptionKey, $attempts=0)
  {
    try {
      $result = $this->client->encryptString($this->parser->convertEncryptStringRequest($plainString, $encryptionKey));
      return $this->parser->parseEncryptStringResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->encryptString($plainString, $encryptionKey, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END encryptString()

  //----------------------------------------------------------------------------

  public function getActivities($syncMarker, $syncMarkerLimit, $attempts=0)
  {
    try {
      $result = $this->client->getActivities($this->parser->convertGetActivitiesRequest($syncMarker, $syncMarkerLimit));
      return $this->parser->parseGetActivitiesResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getActivities($syncMarker, $syncMarkerLimit, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getActivities()

  //----------------------------------------------------------------------------

  public function createActivity($createActivity, $attempts=0)
  {
    try {
      $result = $this->client->createActivity($this->parser->convertCreateActivityRequest($createActivity));
      return $this->parser->parseCreateActivityResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->createActivity($createActivity, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END createActivity()

  //----------------------------------------------------------------------------

  public function updateActivity($updateActivity, $attempts=0)
  {
    try {
      $result = $this->client->updateActivity($this->parser->convertUpdateActivityRequest($updateActivity));
      return $this->parser->parseUpdateActivityResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->updateActivity($updateActivity, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END updateActivity()

  //----------------------------------------------------------------------------

  public function deleteActivity($activityId, $attempts=0)
  {
    try {
      $result = $this->client->deleteActivity($this->parser->convertDeleteActivityRequest($activityId));
      return $this->parser->parseDeleteActivityResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->deleteActivity($activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END deleteActivity()

  //----------------------------------------------------------------------------

  public function verifyCredentials($request, $attempts=0)
  {
    try {
      $result = $this->client->verifyCredentials($this->parser->convertVerifyCredentialsRequest($request));
      return $this->parser->parseVerifyCredentialsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->verifyCredentials($activityId, $attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  }

  //----------------------------------------------------------------------------

public function report($arguments, $attempts = 0)
{
    try {
        if (!is_array($arguments) || !array_key_exists('method', $arguments) || empty($arguments['method'])) {
            throw new Exception("No method defined for group call : " . __FUNCTION__);
        }
        $method = __FUNCTION__ . $arguments['method'];
        unset($arguments['method']);
        $parameters = $this->parser->convertReportRequest($method, $arguments);
        $result = call_user_func(array($this->client, $method), $parameters);
        return $this->parser->parseReportResult($method, $result);
    } catch (SoapFault $e) {
        $msg = $e->getMessage();
        if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
            sleep(1);
            return $this->report($arguments, $attempts + 1);
        } else {
            throw new MplusQAPIException('SoapFault occurred: ' . $msg, 0, $e);
        }
    } catch (Exception $e) {
        throw new MplusQAPIException('Exception occurred: ' . $e->getMessage(), 0, $e);
    }
} // END report()


  //----------------------------------------------------------------------------

public function getBranchGroups($attempts = 0)
{
    try {
        $result = $this->client->getBranchGroups();
        return $this->parser->parseGetBranchGroupsResult($result);
    } catch (SoapFault $e) {
        $msg = $e->getMessage();
        if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
            sleep(1);
            return $this->getBranchGroups($attempts + 1);
        } else {
            throw new MplusQAPIException('SoapFault occurred: ' . $msg, 0, $e);
        }
    } catch (Exception $e) {
        throw new MplusQAPIException('Exception occurred: ' . $e->getMessage(), 0, $e);
    }
} // END getBranchGroups()

//----------------------------------------------------------------------------
  public function getSalePromotions($branchNumbers = [], $attempts=0)
  {
    try {
      $result = $this->client->getSalePromotions($this->parser->convertGetSalePromotionsRequest($branchNumbers));
      return $this->parser->parseGetSalePromotionsResult($result);
    } catch (SoapFault $e) {
      $msg = $e->getMessage();
      if (false !== stripos($msg, 'Could not connect to host') and $attempts < 3) {
        sleep(1);
        return $this->getSalePromotions($attempts+1);
      } else {
        throw new MplusQAPIException('SoapFault occurred: '.$msg, 0, $e);
      }
    } catch (Exception $e) {
      throw new MplusQAPIException('Exception occurred: '.$e->getMessage(), 0, $e);
    }
  } // END getSalePromotions()

}

//==============================================================================

class MplusQAPIDataParser
{

  var $lastErrorMessage = null;

  var $convertToTimestamps = false;

  //----------------------------------------------------------------------------

  public function setConvertToTimestamps($convertToTimestamps)
  {
    $this->convertToTimestamps = $convertToTimestamps;
  } // END setConvertToTimestamps()

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

  public function parseDatabaseVersion($soapDatabaseVersion)
  {
    $databaseVersion = false;
    if (isset($soapDatabaseVersion->majorNumber)) {
      $databaseVersion = objectToArray($soapDatabaseVersion);
    }
    else if (isset($soapDatabaseVersion['majorNumber'])) {
      $databaseVersion = $soapDatabaseVersion;
    }
    return $databaseVersion;
  } // END parseDatabaseVersion()

  //----------------------------------------------------------------------------

  public function parseTerminalSettings($soapTerminalSettings)
  {
    return objectToArray($soapTerminalSettings);
  } // END parseTerminalSettings()

  //----------------------------------------------------------------------------

  public function parseMaxTableNumber($soapMaxTableNumber)
  {
    $maxTableNumber = false;
    if (isset($soapMaxTableNumber->maxTableNumber)) {
      $maxTableNumber = $soapMaxTableNumber->maxTableNumber;
    }
    return $maxTableNumber;
  } // END parseMaxTableNumber()

  //----------------------------------------------------------------------------

  public function parseCurrentSyncMarkers($soapCurrentSyncMarkers)
  {
    $currentSyncMarkers = false;
    if (isset($soapCurrentSyncMarkers->articleSyncMarker)) {
      $currentSyncMarkers = objectToArray($soapCurrentSyncMarkers);
    }
    else if (isset($soapCurrentSyncMarkers['articleSyncMarker'])) {
      $currentSyncMarkers = $soapCurrentSyncMarkers;
    }
    return $currentSyncMarkers;
  } // END parseCurrentSyncMarkers()

  //----------------------------------------------------------------------------

  public function parseLicenseInformation($soapLicenseInformation)
  {
    $licenseInformation = false;
    if (isset($soapLicenseInformation->obscuredLicenseKey)) {
      $licenseInformation = objectToArray($soapLicenseInformation);
    }
    else if (isset($soapLicenseInformation['obscuredLicenseKey'])) {
      $licenseInformation = $soapLicenseInformation;
    }
    return $licenseInformation;
  } // END parseLicenseInformation()

  //----------------------------------------------------------------------------

  public function parseCustomFieldLists($soapCustomFieldLists)
  {
    $customFieldLists = false;
    if (isset($soapCustomFieldLists->articleCustomFieldList)) {
      if ( ! is_array($customFieldLists)) { $customFieldLists = array(); }
      $soapArticleCustomFieldList = $soapCustomFieldLists->articleCustomFieldList;
      if (isset($soapArticleCustomFieldList->customField)) {
        $soapArticleCustomFieldList = $soapArticleCustomFieldList->customField;
        $articleCustomFieldList = objectToArray($soapArticleCustomFieldList);
        $customFieldLists['articleCustomFieldList'] = $articleCustomFieldList;
      }
    }
    if (isset($soapCustomFieldLists->employeeCustomFieldList)) {
      if ( ! is_array($customFieldLists)) { $customFieldLists = array(); }
      $soapEmployeeCustomFieldList = $soapCustomFieldLists->employeeCustomFieldList;
      if (isset($soapEmployeeCustomFieldList->customField)) {
        $soapEmployeeCustomFieldList = $soapEmployeeCustomFieldList->customField;
        $employeeCustomFieldList = objectToArray($soapEmployeeCustomFieldList);
        $customFieldLists['employeeCustomFieldList'] = $employeeCustomFieldList;
      }
    }
    if (isset($soapCustomFieldLists->relationCustomFieldList)) {
      if ( ! is_array($customFieldLists)) { $customFieldLists = array(); }
      $soapRelationCustomFieldList = $soapCustomFieldLists->relationCustomFieldList;
      if (isset($soapRelationCustomFieldList->customField)) {
        $soapRelationCustomFieldList = $soapRelationCustomFieldList->customField;
        $relationCustomFieldList = objectToArray($soapRelationCustomFieldList);
        $customFieldLists['relationCustomFieldList'] = $relationCustomFieldList;
      }
    }
    return $customFieldLists;
  } // END parseCustomFieldLists()

  //----------------------------------------------------------------------------

  public function parseRelationArticleDiscounts($soapRelationArticleDiscounts)
  {
    $relationArticleDiscounts = false;
    if (isset($soapRelationArticleDiscounts->relationArticleDiscountList)) {
      $relationArticleDiscounts = array();
      $soapRelationArticleDiscounts = $soapRelationArticleDiscounts->relationArticleDiscountList;
      if (isset($soapRelationArticleDiscounts->relationArticleDiscount)) {
        $soapRelationArticleDiscounts = $soapRelationArticleDiscounts->relationArticleDiscount;
        $relationArticleDiscounts = objectToArray($soapRelationArticleDiscounts);
      }
    }
    return $relationArticleDiscounts;
  } // END parseRelationArticleDiscounts()

  //----------------------------------------------------------------------------

  public function parseCardCategories($soapCardCategories)
  {
    $cardCategories = false;
    if (isset($soapCardCategories->articleCardCategoryList)) {
      if ( ! is_array($cardCategories)) { $cardCategories = array(); }
      $soapArticleCardCategories = $soapCardCategories->articleCardCategoryList;
      if (isset($soapArticleCardCategories->cardCategory)) {
        $soapArticleCardCategories = $soapArticleCardCategories->cardCategory;
        $articleCardCategories = objectToArray($soapArticleCardCategories);
        $cardCategories['articleCardCategories'] = $articleCardCategories;
      }
    }
    if (isset($soapCardCategories->employeeCardCategoryList)) {
      if ( ! is_array($cardCategories)) { $cardCategories = array(); }
      $soapEmployeeCardCategories = $soapCardCategories->employeeCardCategoryList;
      if (isset($soapEmployeeCardCategories->cardCategory)) {
        $soapEmployeeCardCategories = $soapEmployeeCardCategories->cardCategory;
        $employeeCardCategories = objectToArray($soapEmployeeCardCategories);
        $cardCategories['employeeCardCategories'] = $employeeCardCategories;
      }
    }
    if (isset($soapCardCategories->relationCardCategoryList)) {
      if ( ! is_array($cardCategories)) { $cardCategories = array(); }
      $soapRelationCardCategories = $soapCardCategories->relationCardCategoryList;
      if (isset($soapRelationCardCategories->cardCategory)) {
        $soapRelationCardCategories = $soapRelationCardCategories->cardCategory;
        $relationCardCategories = objectToArray($soapRelationCardCategories);
        $cardCategories['relationCardCategories'] = $relationCardCategories;
      }
    }
    return $cardCategories;
  } // END parseCardCategories()

  //----------------------------------------------------------------------------

  public function parseArticleCardLayout($soapArticleCardLayout)
  {
    if (isset($soapArticleCardLayout->cardLayoutFieldList) and isset($soapArticleCardLayout->cardLayoutFieldList->cardLayoutField)) {
      return objectToArray($soapArticleCardLayout->cardLayoutFieldList->cardLayoutField);
    }
    return array();
  } // END parseArticleCardLayout();

  //----------------------------------------------------------------------------

  public function parseTerminalList($soapTerminalList) {
    if (isset($soapTerminalList->return)) {
      $soapTerminalList = $soapTerminalList->return;
    }
    $terminals = array();
    foreach ($soapTerminalList as $soapTerminal) {
      $terminal = objectToArray($soapTerminal);
      /*switch ($terminal['terminalStatus']) {
        case 'TERMINAL-STATUS-AVAILABLE':
          $terminal['terminalStatus'] = TERMINAL_STATUS_AVAILABLE;
          break;
        case 'TERMINAL-STATUS-REGISTERED':
          $terminal['terminalStatus'] = TERMINAL_STATUS_REGISTERED;
          break;
        default:
          $terminal['terminalStatus'] = TERMINAL_STATUS_UNKNOWN;
          break;
      }*/
      $terminals[] = $terminal;
    }
    return $terminals;
  } // END parseTerminalList()

  //----------------------------------------------------------------------------

  public function parseActiveEmployeeList($soapActiveEmployeeList) 
  {
    if (isset($soapActiveEmployeeList->return)) {
      $soapActiveEmployeeList = $soapActiveEmployeeList->return;
    }
    $active_employees = array();
    foreach ($soapActiveEmployeeList as $soapActiveEmployee) {
      $active_employees[] = objectToArray($soapActiveEmployee);
    }
    return $active_employees;
  } // END parseActiveEmployeeList()

  //----------------------------------------------------------------------------

  public function parseTableList($soapTableList) 
  {
    if (isset($soapTableList->table)) {
      $soapTableList = $soapTableList->table;
    }
    $table_list = array();
    foreach ($soapTableList as $soapTable) {
      $table_list[] = objectToArray($soapTable);
    }
    return $table_list;
  } // END parseTableList()

  //----------------------------------------------------------------------------

  public function parseTableListV2($soapTableListV2) 
  {
    if (isset($soapTableListV2->wholeTable)) {
      $soapTableListV2 = $soapTableListV2->wholeTable;
    }
    $table_list = array();
    foreach ($soapTableListV2 as $soapTableV2) {
      $table_list[] = objectToArray($soapTableV2);
    }
    return $table_list;
  } // END parseTableListV2()

  //----------------------------------------------------------------------------

  public function parseAvailablePaymentMethods($soapAvailablePaymentMethods) 
  {
    if (isset($soapAvailablePaymentMethods->paymentMethodList->paymentMethod)) {
      $soapAvailablePaymentMethods = $soapAvailablePaymentMethods->paymentMethodList->paymentMethod;
    }
    $available_payment_methods = array();
    foreach ($soapAvailablePaymentMethods as $soapAvailablePaymentMethod) {
      $available_payment_methods[] = objectToArray($soapAvailablePaymentMethod);
    }
    return $available_payment_methods;
  } // END parseAvailablePaymentMethods()

  //----------------------------------------------------------------------------

  public function parseCourseList($soapCourseList) 
  {
    if (isset($soapCourseList->course)) {
      $soapCourseList = $soapCourseList->course;
    }
    $course_list = array();
    foreach ($soapCourseList as $soapCourse) {
      $course_list[] = objectToArray($soapCourse);
    }
    return $course_list;
  } // END parseCourseList()

  //----------------------------------------------------------------------------

  public function parseButtonLayout($soapButtonLayout)
  {
    if (isset($soapButtonLayout->return)) {
      $soapButtonLayout = $soapButtonLayout->return;
      $buttonLayout = objectToArray($soapButtonLayout);
      return $buttonLayout;
    } else {
      return false;
    }
  } // END parseButtonLayout()

  //----------------------------------------------------------------------------

  public function parseArticlesInLayout($soapArticlesInLayout)
  {
    if (isset($soapArticlesInLayout->return)) {
      $soapArticlesInLayout = $soapArticlesInLayout->return;
      $articlesInLayout = objectToArray($soapArticlesInLayout);
      foreach ($articlesInLayout as $idx => $article) {
        if (isset($article['allergenList']['allergen'])) {
          $article['allergenList'] = $article['allergenList']['allergen'];
          $articlesInLayout[$idx] = $article;
        }
        if (isset($article['preparationMethods']['preparationMethod'])) {
          $article['preparationMethods'] = $article['preparationMethods']['preparationMethod'];
          $articlesInLayout[$idx] = $article;
        }
        foreach ($article['preparationMethods'] as $idx2 => $preparationMethod) {
          if (isset($preparationMethod['allergenList']['allergen'])) {
            $preparationMethod['allergenList'] = $preparationMethod['allergenList']['allergen'];
            $articlesInLayout[$idx]['preparationMethods'][$idx2] = $preparationMethod;
          }
        }
        if (isset($article['componentArticles']['componentArticle'])) {
          $article['componentArticles'] = $article['componentArticles']['componentArticle'];
          $articlesInLayout[$idx] = $article;
        }
        foreach ($article['componentArticles'] as $idx2 => $componentArticle) {
          if (isset($componentArticle['allergenList']['allergen'])) {
            $componentArticle['allergenList'] = $componentArticle['allergenList']['allergen'];
            $articlesInLayout[$idx]['componentArticles'][$idx2] = $componentArticle;
          }
        }
      }
      return $articlesInLayout;
    } else {
      return false;
    }
  } // END parseArticlesInLayout()

  //----------------------------------------------------------------------------

  public function parseVatGroupList($soapVatGroupList) 
  {
    if (isset($soapVatGroupList->vatGroup)) {
      $soapVatGroupList = $soapVatGroupList->vatGroup;
    }
    $vatGroups = array();
    foreach ($soapVatGroupList as $soapVatGroup) {
      $vatGroup = objectToArray($soapVatGroup);
      $vatGroups[] = $vatGroup;
    }
    return $vatGroups;
  } // END parseVatGroupList()

  //----------------------------------------------------------------------------

  public function parsePriceGroupList($soapPriceGroupList) 
  {
    if (isset($soapPriceGroupList->priceGroup)) {
      $soapPriceGroupList = $soapPriceGroupList->priceGroup;
    }
    $priceGroups = array();
    foreach ($soapPriceGroupList as $soapPriceGroup) {
      $priceGroup = objectToArray($soapPriceGroup);
      $priceGroups[] = $priceGroup;
    }
    return $priceGroups;
  } // END parsePriceGroupList()

  //----------------------------------------------------------------------------

  public function parseSalesPriceList($soapSalesPriceList) 
  {
    if (isset($soapSalesPriceList->salesPrice)) {
      $soapSalesPriceList = $soapSalesPriceList->salesPrice;
    }
    $salesPrices = array();
    foreach ($soapSalesPriceList as $soapSalesPrice) {
      $salesPrice = objectToArray($soapSalesPrice);
      $salesPrices[] = $salesPrice;
    }
    return $salesPrices;
  } // END parseSalesPriceList()

  //----------------------------------------------------------------------------

  public function parseProducts($soapProducts) 
  {
    if (isset($soapProducts->productList->product)) {
      $soapProducts = $soapProducts->productList->product;
    }
    elseif (isset($soapProducts->productList)) {
      $soapProducts = array();
    }
    else {
      $soapProducts = null;
    }
    if ( ! is_null($soapProducts)) {
      if ( ! is_array($soapProducts)) {
        $soapProducts = array($soapProducts);
      }
      $products = array();
      foreach ($soapProducts as $soapProduct) {
        $product = objectToArray($soapProduct);
        if (array_key_exists('groupNumbers', $product)) {
          if ( ! is_array($product['groupNumbers'])) {
            if ( ! empty($product['groupNumbers'])) {
              $product['groupNumbers'] = array($product['groupNumbers']);
            }
            else {
              $product['groupNumbers'] = array();
            }
          }
        }
        else {
          $product['groupNumbers'] = array();
        }
        if (array_key_exists('sortOrderGroupList', $product)) {
          if (array_key_exists('sortOrderGroup', $product['sortOrderGroupList'])) {
            $product['sortOrderGroupList'] = $product['sortOrderGroupList']['sortOrderGroup'];
          } else {
            $product['sortOrderGroupList'] = array();
          }
        } else {
          $product['sortOrderGroupList'] = array();
        }
        if (isset($product['articleList']['article']) and ! isset($product['articleList']['article']['articleNumber'])) {
          $product['articleList'] = $product['articleList']['article'];
        }
        if (isset($product['articleList'])) {
          foreach ($product['articleList'] as $idx => $article) {
            $orig_article = $article;
            $article['imageList'] = $this->parseImageList(isset($article['imageList'])?$article['imageList']:array());
            if (isset($article['allergenList']['allergen'])) {
              $article['allergenList'] = $article['allergenList']['allergen'];
            }
            if (isset($article['preparationMethodList']['preparationMethod'])) {
              $article['preparationMethodList'] = $article['preparationMethodList']['preparationMethod'];
            }
            if (isset($article['preparationMethodList'])) {
              foreach ($article['preparationMethodList'] as $idx2 => $preparationMethod) {
                if (isset($preparationMethod['allergenList']['allergen'])) {
                  $preparationMethod['allergenList'] = $preparationMethod['allergenList']['allergen'];
                  $article['preparationMethodList'][$idx2] = $preparationMethod;
                }
              }
            }
            if (isset($article['customFieldList']['customField'])) {
              $article['customFieldList'] = $article['customFieldList']['customField'];
            }
            if (isset($article['exchangeRateBuyPrice']) and isset($article['exchangeRateBuyPriceDecimalPlaces'])) {
              $article['exchangeRateBuyPrice'] = from_quantity_and_decimal_places($article['exchangeRateBuyPrice'], $article['exchangeRateBuyPriceDecimalPlaces']);
              unset($article['exchangeRateBuyPriceDecimalPlaces']);
            }
            if (isset($article['exchangeRateSellPrice']) and isset($article['exchangeRateSellPriceDecimalPlaces'])) {
              $article['exchangeRateSellPrice'] = from_quantity_and_decimal_places($article['exchangeRateSellPrice'], $article['exchangeRateSellPriceDecimalPlaces']);
              unset($article['exchangeRateSellPriceDecimalPlaces']);
            }
            $product['articleList'][$idx] = $article;
          }
        }
        else {
          $product['articleList'] = array();
        }
        $products[] = $product;
      }
      return $products;
    }
    return false;
  } // END parseProducts()

  //----------------------------------------------------------------------------

  public function parseRelations($soapRelations)
  {
    if (isset($soapRelations->relationList->relation)) {
      $soapRelations = $soapRelations->relationList->relation;
    }
    elseif (isset($soapRelations->relationList)) {
      $soapRelations = array();
    }
    else {
      $soapRelations = null;
    }
    if ( ! is_null($soapRelations)) {
      if ( ! is_array($soapRelations)) {
        $soapRelations = array($soapRelations);
      }
      $relations = array();
      foreach ($soapRelations as $soapRelation) {
        $relation = objectToArray($soapRelation);
        if (isset($relation['changeTimestamp'])) {
          $relation['changeTimestamp'] = $this->parseMplusDateTime($relation['changeTimestamp']);
        }
        if (isset($relation['createTimestamp'])) {
          $relation['createTimestamp'] = $this->parseMplusDateTime($relation['createTimestamp']);
        }
        if (isset($relation['imageList'])) {
          $relation['imageList'] = $this->parseImageList(isset($relation['imageList'])?$relation['imageList']:array());
        } else {
          $relation['imageList'] = array();
        }
        $relations[] = $relation;
      }
      return $relations;
    }
    return false;
  } // END parseRelations()

  //----------------------------------------------------------------------------

  public function parseImages($soapImages)
  {
    $imageList = $this->parseImageList(isset($soapImages->imageList)?objectToArray($soapImages->imageList):array());
    return $imageList;
  } // END parseImages()

  //----------------------------------------------------------------------------

  public function parseImageList($imageList)
  {
    if (isset($imageList['image']) and ! isset($imageList['image']['imageName'])) {
      $imageList = $imageList['image'];
    }
    if ( ! is_array($imageList)) {
      if ( ! empty($imageList)) {
        $imageList = array($imageList);
      } else {
        $imageList = array();
      }
    }
    foreach ($imageList as $img_idx => $img) {
      // $img_data = base64_decode($img['data']);
      // header('Content-type:image/jpg');
      // header('Content-length:'.strlen($img_data));
      // exit($img_data);
      if (isset($img['createdTimestamp'])) {
        $img['createdTimestamp'] = $this->parseMplusDateTime($img['createdTimestamp']);
      }
      if (isset($img['changedTimestamp'])) {
        $img['changedTimestamp'] = $this->parseMplusDateTime($img['changedTimestamp']);
      }
      if (empty($img['imageData'])) {
        $img['imageData'] = null;
      }
      if (empty($img['thumbData'])) {
        $img['thumbData'] = null;
      }
      $imageList[$img_idx] = $img;
    }
    return $imageList;
  } // END parseImageList()

  //----------------------------------------------------------------------------

  public function parseEmployee($soapEmployee) 
  {
    if (isset($soapEmployee->result) and $soapEmployee->result == 'GET-EMPLOYEE-RESULT-OK') {
      if (isset($soapEmployee->employee)) {
        $soapEmployee = $soapEmployee->employee;
        $employee = objectToArray($soapEmployee);
        if (isset($employee['changeTimestamp'])) {
          $employee['changeTimestamp'] = $this->parseMplusDateTime($employee['changeTimestamp']);
        }
        if (isset($employee['createTimestamp'])) {
          $employee['createTimestamp'] = $this->parseMplusDateTime($employee['createTimestamp']);
        }
        return $employee;
      }
    }
    return false;
  } // END parseEmployee()

  //----------------------------------------------------------------------------

  public function parseEmployees($soapEmployees) 
  {
    if (isset($soapEmployees->employeeList)) {
      $soapEmployees = $soapEmployees->employeeList;
      $employees = array();
      if (isset($soapEmployees->employee)) {
        $soapEmployees = $soapEmployees->employee;
        $employees = objectToArray($soapEmployees);
        foreach ($employees as $idx => $employee) {
          if (isset($employee['changeTimestamp'])) {
            $employee['changeTimestamp'] = $this->parseMplusDateTime($employee['changeTimestamp']);
          }
          if (isset($employee['createTimestamp'])) {
            $employee['createTimestamp'] = $this->parseMplusDateTime($employee['createTimestamp']);
          }
          $employees[$idx] = $employee;
        }
      }
      return $employees;
    }
    return false;
  } // END parseEmployees()

  //----------------------------------------------------------------------------

  public function parseArticleGroups($soapArticleGroups) {
    if (isset($soapArticleGroups->articleGroupList->articleGroups)) {
      $soapArticleGroups = $soapArticleGroups->articleGroupList->articleGroups;
    } elseif (isset($soapArticleGroups->articleGroupList)) {
      $soapArticleGroups = $soapArticleGroups->articleGroupList;
    } elseif (isset($soapArticleGroups->articleGroups)) {
      $soapArticleGroups = $soapArticleGroups->articleGroups;
    } else {
      $soapArticleGroups = null;
    }
    if ( ! is_null($soapArticleGroups)) {
      if ( ! is_array($soapArticleGroups)) {
        $soapArticleGroups = array($soapArticleGroups);
      }
      $articleGroups = array();
      foreach ($soapArticleGroups as $soapArticleGroup) {
        $articleGroup = objectToArray($soapArticleGroup);
        if (isset($articleGroup['subGroupList'])) {
          $articleGroup['subGroupList'] = $this->parseArticleSubGroups($articleGroup['subGroupList']);
        }
          $articleGroups[] = $articleGroup;
        }
      return $articleGroups;
    }
    return false;
  } // END parseArticleGroups()

  //----------------------------------------------------------------------------

  public function parseChangedArticleGroups($soapChangedArticleGroups) {
    if (isset($soapChangedArticleGroups->changedArticleGroupList->changedArticleGroups)) {
      $soapChangedArticleGroups = $soapChangedArticleGroups->changedArticleGroupList->changedArticleGroups;
    } elseif (isset($soapChangedArticleGroups->changedArticleGroupList)) {
      $soapChangedArticleGroups = $soapChangedArticleGroups->changedArticleGroupList;
    } elseif (isset($soapChangedArticleGroups->changedArticleGroups)) {
      $soapChangedArticleGroups = $soapChangedArticleGroups->changedArticleGroups;
    } else {
      $soapChangedArticleGroups = null;
    }
    if ( ! is_null($soapChangedArticleGroups)) {
      if ( ! is_array($soapChangedArticleGroups)) {
        $soapChangedArticleGroups = array($soapChangedArticleGroups);
      }
      $changedArticleGroups = array();
      foreach ($soapChangedArticleGroups as $soapChangedArticleGroup) {
        $changedArticleGroup = objectToArray($soapChangedArticleGroup);
        if ( ! empty($changedArticleGroup)) {
          $changedArticleGroups[] = $changedArticleGroup;
        }
      }
      return $changedArticleGroups;
    }
    return false;
  } // END parseChangedArticleGroups()

  //----------------------------------------------------------------------------

  public function parseArticleSubGroups($articleSubGroups) {
    if (isset($articleSubGroups['articleGroups']['groupNumber'])) {
      $articleSubGroups = array($articleSubGroups['articleGroups']);
    }
    elseif (isset($articleSubGroups['articleGroups'])) {
      $articleSubGroups = $articleSubGroups['articleGroups'];
      foreach ($articleSubGroups as $idx => $articleSubGroup) {
        if (isset($articleSubGroup['subGroupList']) and ! is_null($articleSubGroup['subGroupList']) and ! empty($articleSubGroup['subGroupList'])) {
          $articleSubGroup['subGroupList'] = $this->parseArticleSubGroups($articleSubGroup['subGroupList']);
          $articleSubGroups[$idx] = $articleSubGroup;
        }
      }
    }
    return $articleSubGroups;
  } // END parseArticleSubGroups()

  //----------------------------------------------------------------------------

  public function parseStock($soapStock) {
    if (isset($soapStock->articleStocks)) {
      $soapArticleStocks = $soapStock->articleStocks;
      if ( ! is_array($soapArticleStocks)) {
        $soapArticleStocks = array($soapArticleStocks);
      }
      $articleStocks = array();
      foreach ($soapArticleStocks as $soapArticleStock) {
        $articleStock = objectToArray($soapArticleStock);
        $articleStocks[] = $articleStock;
      }
      return $articleStocks;
    }
    return false;
  } // END parseStock()

  //----------------------------------------------------------------------------

  public function parseStockHistory($soapStockHistory) {
    if (isset($soapStockHistory->articleStockHistory)) {
      $soapArticleStockHistory = $soapStockHistory->articleStockHistory;
      if ( ! is_array($soapArticleStockHistory)) {
        $soapArticleStockHistory = array($soapArticleStockHistory);
      }
      $articleStockHistory = array();
      foreach ($soapArticleStockHistory as $history) {
        $history = objectToArray($history);
        if (isset($history['timestamp'])) {
          $history['timestamp'] = $this->parseMplusDateTime($history['timestamp']);
        }
        $articleStockHistory[] = $history;
      }
      return $articleStockHistory;
    }
    return false;
  } // END parseStockHistory()

  //----------------------------------------------------------------------------

  public function parseShifts($soapShifts) {
    if (isset($soapShifts->shiftList)) {
      $soapShifts = $soapShifts->shiftList;
      $shifts = array();
      if (isset($soapShifts->shift)) {
        $shifts = objectToArray($soapShifts->shift);
        foreach ($shifts as $key => $shift) {
          if (isset($shift['financialDate'])) {
            $shift['financialDate'] = $this->parseMplusDate($shift['financialDate']);
          }
          if (isset($shift['startTimestamp'])) {
            $shift['startTimestamp'] = $this->parseMplusDateTime($shift['startTimestamp']);
          }
          if (isset($shift['endTimestamp'])) {
            $shift['endTimestamp'] = $this->parseMplusDateTime($shift['endTimestamp']);
          }
          $shifts[$key] = $shift;
        }
      }
      return $shifts;
    }
    return false;
  } // END parseShifts()

  //----------------------------------------------------------------------------

  public function parseOrderResult($soapOrderResult)
  {
    if (isset($soapOrderResult->result)) {
      if ($soapOrderResult->result == 'GET-ORDER-RESULT-OK') {
        if (isset($soapOrderResult->order)) {
          $soapOrder = $soapOrderResult->order;
          return $this->parseOrder($soapOrder);
        }
      } else {
        throw new MplusQAPIException($soapOrderResult->result);
      }
    } else {
      throw new MplusQAPIException('No valid order result');
    }
  }

  //----------------------------------------------------------------------------

  public function parseOrder($soapOrder)
  {
    $order = objectToArray($soapOrder);
    if (isset($order['lineList'])) {
      if (isset($order['lineList']['line'])) {
        $order['lineList'] = $order['lineList']['line'];
      }
    }
    if (isset($order['invoiceIds'])) {
      if (isset($order['invoiceIds']['id'])) {
        $order['invoiceIds'] = $order['invoiceIds']['id'];
      }
    }
    return $order;
  }

  //----------------------------------------------------------------------------

  public function parseProposalResult($soapProposalResult)
  {
    if (isset($soapProposalResult->result) and $soapProposalResult->result == 'GET-PROPOSAL-RESULT-OK') {
      if (isset($soapProposalResult->proposal)) {
        $soapProposal = $soapProposalResult->proposal;
        $proposal = objectToArray($soapProposal);
        if (isset($proposal['lineList'])) {
          if (isset($proposal['lineList']['line'])) {
            $proposal['lineList'] = $proposal['lineList']['line'];
          }
        }
        if (isset($proposal['invoiceIds'])) {
          if (isset($proposal['invoiceIds']['id'])) {
            $proposal['invoiceIds'] = $proposal['invoiceIds']['id'];
          }
        }
        return $proposal;
      }
    }
    return false;
  } // END parseProposalResult()

  //----------------------------------------------------------------------------

  public function parseOrderCategories($soapOrderCategories)
  {
    if (isset($soapOrderCategories->orderCategory)) {
      $soapOrderCategories = $soapOrderCategories->orderCategory;
      $orderCategories = objectToArray($soapOrderCategories);
      return $orderCategories;
    } else {
      return array();
    }
  } // END parseOrderCategories()
  
  //----------------------------------------------------------------------------

  public function parsePayInvoiceResult($soapPayInvoiceResult) {
    if (isset($soapPayInvoiceResult->result) and $soapPayInvoiceResult->result == 'PAY-INVOICE-RESULT-OK') {
      return true;
    }
    return false;
  } // END parsePayInvoiceResult()

  //----------------------------------------------------------------------------

  public function parsePayOrderResult($soapPayOrderResult) {
    if (isset($soapPayOrderResult->result) and $soapPayOrderResult->result == 'PAY-ORDER-RESULT-OK') {
      if (isset($soapPayOrderResult->invoiceId)) {
        return $soapPayOrderResult->invoiceId;
      } else {
        return true;
      }
    }
    return false;
  } // END parsePayOrderResult()

  //----------------------------------------------------------------------------

  public function parsePayTableOrderResult($soapPayTableOrderResult) {
    if (isset($soapPayTableOrderResult->result) and $soapPayTableOrderResult->result == 'PAY-ORDER-RESULT-OK') {
      if (isset($soapPayTableOrderResult->receiptId)) {
        return $soapPayTableOrderResult->receiptId;
      } else {
        return true;
      }
    }
    return false;
  } // END parsePayTableOrderResult()

  //----------------------------------------------------------------------------

  public function parsePrepayTableOrderResult($soapPrepayTableOrderResult) {
    if (isset($soapPrepayTableOrderResult->result) and $soapPrepayTableOrderResult->result == 'PAY-ORDER-RESULT-OK') {
      if (isset($soapPrepayTableOrderResult->receiptId)) {
        return $soapPrepayTableOrderResult->receiptId;
      } else {
        return true;
      }
    }
    return false;
  } // END parsePrepayTableOrderResult()

  //----------------------------------------------------------------------------

  public function parseDeliverOrderResult($soapDeliverOrderResult) {
    if (isset($soapDeliverOrderResult->result)) {
      if ($soapDeliverOrderResult->result == 'DELIVER-ORDER-RESULT-OK') {
        if (isset($soapDeliverOrderResult->packingSlipId)) {
          return $soapDeliverOrderResult->packingSlipId;
      } else {
        return true;
      }
      } else {
        if (isset($soapDeliverOrderResult->errorMessage) and ! empty($soapDeliverOrderResult->errorMessage)) {
          throw new MplusQAPIException($soapDeliverOrderResult->result.' - '.$soapDeliverOrderResult->errorMessage);
        } else {
          throw new MplusQAPIException($soapDeliverOrderResult->result);
        }
      }
    } elseif (isset($soapDeliverOrderResult->errorMessage) and ! empty($soapDeliverOrderResult->errorMessage)) {
      throw new MplusQAPIException($soapDeliverOrderResult->errorMessage);
    }
    return false;
  } // END parseDeliverOrderResult()

  //----------------------------------------------------------------------------

  public function parseDeliverOrderV2Result($soapDeliverOrderV2Result) {
    if (isset($soapDeliverOrderV2Result->result)) {
      if ($soapDeliverOrderV2Result->result == 'DELIVER-ORDER-V2-RESULT-OK') {
        if (isset($soapDeliverOrderV2Result->packingSlipId)) {
          return $soapDeliverOrderV2Result->packingSlipId;
      } else {
        return true;
      }
      } else {
        if (isset($soapDeliverOrderV2Result->errorMessage) and ! empty($soapDeliverOrderV2Result->errorMessage)) {
          throw new MplusQAPIException($soapDeliverOrderV2Result->result.' - '.$soapDeliverOrderV2Result->errorMessage);
        } else {
          throw new MplusQAPIException($soapDeliverOrderV2Result->result);
        }
      }
    } elseif (isset($soapDeliverOrderV2Result->errorMessage) and ! empty($soapDeliverOrderV2Result->errorMessage)) {
      throw new MplusQAPIException($soapDeliverOrderV2Result->errorMessage);
    }
    return false;
  } // END parseDeliverOrderV2Result()

  //----------------------------------------------------------------------------

  public function parseInvoiceResult($soapInvoiceResult) {
    if (isset($soapInvoiceResult->result) and $soapInvoiceResult->result == 'GET-INVOICE-RESULT-OK') {
      if (isset($soapInvoiceResult->invoice)) {
        $soapInvoice = $soapInvoiceResult->invoice;
        $invoice = objectToArray($soapInvoice);
        if (isset($invoice['lineList'])) {
          if (isset($invoice['lineList']['line'])) {
            $invoice['lineList'] = $invoice['lineList']['line'];
          }
        }
        if (isset($invoice['paymentList'])) {
          if (isset($invoice['paymentList']['payment'])) {
            $invoice['paymentList'] = $invoice['paymentList']['payment'];
          }
        }
        return $invoice;
      }
    }
    return false;
  } // END parseInvoiceResult()

  //----------------------------------------------------------------------------

  public function parseCreditInvoiceResult($soapCreditInvoiceResult) {
    if (isset($soapCreditInvoiceResult->result) and $soapCreditInvoiceResult->result == 'CANCEL-INVOICE-RESULT-OK') {
      return true;
    }
    return false;
  } // END parseCreditInvoiceResult()

  //----------------------------------------------------------------------------

  public function parseGetReceiptsResult($soapReceiptsResult) {
    $receipts = array();
    if (isset($soapReceiptsResult->receiptList->receipt)) {
      $soapReceipts = $soapReceiptsResult->receiptList->receipt;
      $receipts = objectToArray($soapReceipts);
      foreach ($receipts as $key => $receipt) {
        if (isset($receipt['lineList']['line'])) {
          $receipt['lineList'] = $receipt['lineList']['line'];
        }
        foreach ($receipt['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $receipt['lineList'][$line_key] = $line;
        }
        if (isset($receipt['paymentList']['payment'])) {
          $receipt['paymentList'] = $receipt['paymentList']['payment'];
        }
        $receipts[$key] = $receipt;
      }
    }
    return $receipts;
  } // END parseGetReceiptsResult()

  //----------------------------------------------------------------------------

  public function parseReceiptsByOrderResult($soapReceiptsByOrderResult) {
    if (isset($soapReceiptsByOrderResult->result) and $soapReceiptsByOrderResult->result == 'GET-RECEIPTS-BY-ORDER-RESULT-OK') {
      $receipts = array();
      if (isset($soapReceiptsByOrderResult->receiptList->receipt)) {
        $soapReceipts = $soapReceiptsByOrderResult->receiptList->receipt;
        $receipts = objectToArray($soapReceipts);
        foreach ($receipts as $key => $receipt) {
          if (isset($receipt['lineList']['line'])) {
            $receipt['lineList'] = $receipt['lineList']['line'];
          } else {
            $receipt['lineList'] = array();
          }
          foreach ($receipt['lineList'] as $line_key => $line) {
            if (isset($line['preparationList']['line'])) {
              $line['preparationList'] = $line['preparationList']['line'];
            }
            $receipt['lineList'][$line_key] = $line;
          }
          if (isset($receipt['paymentList']['payment'])) {
            $receipt['paymentList'] = $receipt['paymentList']['payment'];
          }
          $receipts[$key] = $receipt;
        }
      }
      return $receipts;
    }
    return false;
  } // END parseReceiptsByOrderResult()

  //----------------------------------------------------------------------------

  public function parseReceiptsByCashCountResult($soapReceiptsByCashCountResult) {
    if (isset($soapReceiptsByCashCountResult->result) and $soapReceiptsByCashCountResult->result == 'GET-RECEIPTS-BY-CASH-COUNT-RESULT-OK') {
      $receipts = array();
      if (isset($soapReceiptsByCashCountResult->receiptList->receipt)) {
        $soapReceipts = $soapReceiptsByCashCountResult->receiptList->receipt;
        $receipts = objectToArray($soapReceipts);
        foreach ($receipts as $key => $receipt) {
          if (isset($receipt['lineList']['line'])) {
            $receipt['lineList'] = $receipt['lineList']['line'];
          } else {
            $receipt['lineList'] = array();
          }
          foreach ($receipt['lineList'] as $line_key => $line) {
            if (isset($line['preparationList']['line'])) {
              $line['preparationList'] = $line['preparationList']['line'];
            }
            $receipt['lineList'][$line_key] = $line;
          }
          if (isset($receipt['paymentList']['payment'])) {
            $receipt['paymentList'] = $receipt['paymentList']['payment'];
          }
          $receipts[$key] = $receipt;
        }
      }
      return $receipts;
    }
    return false;
  } // END parseReceiptsByCashCountResult()

  //----------------------------------------------------------------------------

  public function parseGetProposalsResult($soapProposalsResult) {
    $proposals = array();
    if (isset($soapProposalsResult->proposalList->proposal)) {
      $soapProposals = $soapProposalsResult->proposalList->proposal;
      $proposals = objectToArray($soapProposals);
      foreach ($proposals as $key => $proposal) {
        if (isset($proposal['lineList']['line'])) {
          $proposal['lineList'] = $proposal['lineList']['line'];
        } else {
          $proposal['lineList'] = array();
        }
        foreach ($proposal['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $proposal['lineList'][$line_key] = $line;
        }
        $proposals[$key] = $proposal;
      }
    }
    return $proposals;
  } // END parseGetProposalsResult()

  //----------------------------------------------------------------------------

  public function parseGetInvoicesResult($soapInvoicesResult) {
    $invoices = array();
    if (isset($soapInvoicesResult->invoiceList->invoice)) {
      $soapInvoices = $soapInvoicesResult->invoiceList->invoice;
      $invoices = objectToArray($soapInvoices);
      foreach ($invoices as $key => $invoice) {
        if (isset($invoice['lineList']['line'])) {
          $invoice['lineList'] = $invoice['lineList']['line'];
        } else {
          $invoice['lineList'] = array();
        }
        foreach ($invoice['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $invoice['lineList'][$line_key] = $line;
        }
        if (isset($invoice['paymentList']['payment'])) {
          $invoice['paymentList'] = $invoice['paymentList']['payment'];
        }
        $invoices[$key] = $invoice;
      }
    }
    return $invoices;
  } // END parseGetInvoicesResult()

  //----------------------------------------------------------------------------

  public function parseGetOrdersResult($soapOrdersResult)
  {
    $orders = array();
    if (isset($soapOrdersResult->orderList->order)) {
      $soapOrders = $soapOrdersResult->orderList->order;
      $orders = objectToArray($soapOrders);
      foreach ($orders as $key => $order) {
        if (isset($order['lineList']['line'])) {
          $order['lineList'] = $order['lineList']['line'];
        } else {
          $order['lineList'] = array();
        }
        foreach ($order['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $order['lineList'][$line_key] = $line;
        }
        if (isset($order['paymentList']['payment'])) {
          $order['paymentList'] = $order['paymentList']['payment'];
        }
        $orders[$key] = $order;
      }
    }
    return $orders;
  } // END parseGetOrdersResult()

  //----------------------------------------------------------------------------

  public function parseGetInterbranchOrdersResult($soapInterbranchOrdersResult)
  {
    $interbranch_orders = array();
    if (isset($soapInterbranchOrdersResult->interbranchOrderList->interbranchOrder)) {
      $soapInterbranchOrders = $soapInterbranchOrdersResult->interbranchOrderList->interbranchOrder;
      $interbranch_orders = objectToArray($soapInterbranchOrders);
      foreach ($interbranch_orders as $key => $interbranch_order) {
        /*if (isset($interbranch_order['lineList']['line'])) {
          $interbranch_order['lineList'] = $interbranch_order['lineList']['line'];
        } else {
          $interbranch_order['lineList'] = array();
        }
        foreach ($interbranch_order['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $interbranch_order['lineList'][$line_key] = $line;
        }*/
        $interbranch_orders[$key] = $interbranch_order;
      }
    }
    return $interbranch_orders;
  } // END parseGetInterbranchOrdersResult()

  //----------------------------------------------------------------------------

  public function parseGetInterbranchShipmentsResult($in)
  {
    $interbranch_shipments = array();
    if (isset($in->interbranchShipmentList->interbranchShipment)) {
      $soapInterbranchShipments = $in->interbranchShipmentList->interbranchShipment;
      $interbranch_shipments = objectToArray($soapInterbranchShipments);
      foreach ($interbranch_shipments as $key => $interbranch_shipment) {
        $interbranch_shipments[$key] = $interbranch_shipment;
      }
    }
    return $interbranch_shipments;
  }

  //----------------------------------------------------------------------------

  public function parseGetInterbranchDeliveriesResult($in)
  {
    $interbranch_deliveries = array();
    if (isset($in->interbranchDeliveryList->interbranchDelivery)) {
      $soapInterbranchDeliveries = $in->interbranchDeliveryList->interbranchDelivery;
      $interbranch_deliveries = objectToArray($soapInterbranchDeliveries);
      foreach ($interbranch_deliveries as $key => $interbranch_delivery) {
        $interbranch_deliveries[$key] = $interbranch_delivery;
      }
    }
    return $interbranch_deliveries;
  }

  //----------------------------------------------------------------------------

  public function parseCreateInterbranchOrderResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchOrder)) {
      $result['interbranchOrder'] = $in->interbranchOrder;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseCreateInterbranchShipmentResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchShipment)) {
      $result['interbranchShipment'] = $in->interbranchShipment;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseCreateInterbranchDeliveryResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchDelivery)) {
      $result['interbranchDelivery'] = $in->interbranchDelivery;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseClaimInterbranchOrderResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchOrder)) {
      $result['interbranchOrder'] = $in->interbranchOrder;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseReleaseInterbranchOrderResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchOrder)) {
      $result['interbranchOrder'] = $in->interbranchOrder;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseShipInterbranchOrderResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchShipment)) {
      $result['interbranchShipment'] = $in->interbranchShipment;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseDeliverInterbranchShipmentResult($in)
  {
    $result = array(
        'result' => $in->result,
    );
    if (isset($in->interbranchDelivery)) {
      $result['interbranchDelivery'] = $in->interbranchDelivery;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseGetPackingSlipsResult($soapPackingSlipsResult)
  {
    $packing_slips = array();
    if (isset($soapPackingSlipsResult->packingSlipList->packingSlip)) {
      $soapPackingSlips = $soapPackingSlipsResult->packingSlipList->packingSlip;
      $packing_slips = objectToArray($soapPackingSlips);
      foreach ($packing_slips as $key => $packing_slip) {
        if (isset($packing_slip['lineList']['line'])) {
          $packing_slip['lineList'] = $packing_slip['lineList']['line'];
        } else {
          $packing_slip['lineList'] = array();
        }
        foreach ($packing_slip['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $packing_slip['lineList'][$line_key] = $line;
        }
        $packing_slips[$key] = $packing_slip;
      }
    }
    return $packing_slips;
  } // END parseGetPackingSlipsResult()

  //----------------------------------------------------------------------------

  public function parseGetPackingSlipsByOrderResult($soapPackingSlipsByOrdersResult)
  {
    $packing_slips = array();
    if (isset($soapPackingSlipsByOrdersResult->packingSlipList->packingSlip)) {
      $soapPackingSlips = $soapPackingSlipsByOrdersResult->packingSlipList->packingSlip;
      $packing_slips = objectToArray($soapPackingSlips);
      foreach ($packing_slips as $key => $packing_slip) {
        if (isset($packing_slip['lineList']['line'])) {
          $packing_slip['lineList'] = $packing_slip['lineList']['line'];
        } else {
          $packing_slip['lineList'] = array();
        }
        foreach ($packing_slip['lineList'] as $line_key => $line) {
          if (isset($line['preparationList']['line'])) {
            $line['preparationList'] = $line['preparationList']['line'];
          }
          $packing_slip['lineList'][$line_key] = $line;
        }
        $packing_slips[$key] = $packing_slip;
      }
    }
    return $packing_slips;
  } // END parseGetPackingSlipsByOrderResult()

  //----------------------------------------------------------------------------

  public function parseGetOrderChangesResult($soapOrderChangesResult)
  {
    $order_changes = array();
    if (isset($soapOrderChangesResult->orderChangeList->orderChange)) {
      $soapOrderChanges = $soapOrderChangesResult->orderChangeList->orderChange;
      $order_changes = objectToArray($soapOrderChanges);
      foreach ($order_changes as $key => $order_change) {
        if (isset($order_change['lineChangeList']['lineChange'])) {
          $order_change['lineChangeList'] = $order_change['lineChangeList']['lineChange'];
        } else {
          $order_change['lineChangeList'] = array();
        }
        foreach ($order_change['lineChangeList'] as $line_change_key => $line_change) {
          $order_change['lineChangeList'][$line_change_key] = $line_change;
        }
        $order_changes[$key] = $order_change;
      }
    }
    return $order_changes;
  } // END parseGetOrderChangesResult()

  //----------------------------------------------------------------------------

  public function parseGetJournalsResult($soapJournalsResult)
  {
    $journals = array();
    if (isset($soapJournalsResult->journalList->journal)) {
      $soapJournals = $soapJournalsResult->journalList->journal;
      $journals = objectToArray($soapJournals);
      foreach ($journals as $key => $journal) {
        if (isset($journal['journalFilterList']['journalFilter'])) {
          $journal['journalFilterList'] = $journal['journalFilterList']['journalFilter'];
        }
        if (isset($journal['turnoverGroupList']['turnoverGroup'])) {
          $journal['turnoverGroupList'] = $journal['turnoverGroupList']['turnoverGroup'];
        }
        if (isset($journal['paymentList']['payment'])) {
          $journal['paymentList'] = $journal['paymentList']['payment'];
        }
        if (isset($journal['vatGroupList']['vatGroup'])) {
          $journal['vatGroupList'] = $journal['vatGroupList']['vatGroup'];
        }
        $journals[$key] = $journal;
      }
    }
    return $journals;
  } // END parseGetJournalsResult()

  //----------------------------------------------------------------------------

  public function parseGetFinancialJournalResult($soapFinancialJournalResult)
  {
    $financialJournal = array();
    if (isset($soapFinancialJournalResult->financialGroupList->financialGroup)) {
      $soapFinancialGroups = $soapFinancialJournalResult->financialGroupList->financialGroup;
      $financialGroups = objectToArray($soapFinancialGroups);
      $financialJournal['financialGroups'] = $financialGroups;
      foreach ($financialJournal['financialGroups'] as $idx => $financialGroup) {
        if (isset($financialGroup['vatGroupList']['vatGroup'])) {
          $financialGroup['vatGroupList'] = $financialGroup['vatGroupList']['vatGroup'];
          $financialJournal['financialGroups'][$idx] = $financialGroup;
        }
      }
    }
    return $financialJournal;
  } // END parseGetFinancialJournalResult()

  //----------------------------------------------------------------------------

  public function parseGetCashCountListResult($soapCashCountListResult)
  {
    $cashCountList = array();
    if (isset($soapCashCountListResult->cashCountList->cashCount)) {
      $soapCashCountList = $soapCashCountListResult->cashCountList->cashCount;
      $cashCountList = objectToArray($soapCashCountList);
      foreach ($cashCountList as $idx => $cashCount) {
        if (isset($cashCount['cashCountLineList'])) {
          if (isset($cashCount['cashCountLineList']['cashCountLine'])) {
            $cashCount['cashCountLineList'] = $cashCount['cashCountLineList']['cashCountLine'];
          }
        }
        $cashCountList[$idx] = $cashCount;
      }
    }
    return $cashCountList;
  } // END parseGetCashCountListResult()

  //----------------------------------------------------------------------------

  public function parseGetCashDrawerBalancingListResult($soapCashDrawerBalancingListResult)
  {
    $cashDrawerBalancingList = array();
    if (isset($soapCashDrawerBalancingListResult->cashDrawerBalancingList->cashDrawerBalancing)) {
      $soapCashDrawerBalancingList = $soapCashDrawerBalancingListResult->cashDrawerBalancingList->cashDrawerBalancing;
      $cashDrawerBalancingList = objectToArray($soapCashDrawerBalancingList);
      /*foreach ($cashCountList as $idx => $cashCount) {
        if (isset($cashCount['cashCountLineList'])) {
          if (isset($cashCount['cashCountLineList']['cashCountLine'])) {
            $cashCount['cashCountLineList'] = $cashCount['cashCountLineList']['cashCountLine'];
          }
        }
        $cashCountList[$idx] = $cashCount;
      }*/
    }
    return $cashDrawerBalancingList;
  } // END parseGetCashDrawerBalancingListResult()

  //----------------------------------------------------------------------------

  public function parseWordAliases($soapWordAliases)
  {
    $word_aliases = array();
    if (isset($soapWordAliases->wordAliasList->wordAlias)) {
      $soapWordAliases = $soapWordAliases->wordAliasList->wordAlias;
      foreach ($soapWordAliases as $soapWordAlias) {
        $word_aliases[] = objectToArray($soapWordAlias);
      }
    }
    return $word_aliases;
  } // END parseWordAliases()

  //----------------------------------------------------------------------------

  public function parseGetTurnoverGroupsResult($soapGetTurnoverGroupsResult) {
    $turnoverGroups = array();
    if (isset($soapGetTurnoverGroupsResult->turnoverGroupList->turnoverGroup)) {
      $soapTurnoverGroups = $soapGetTurnoverGroupsResult->turnoverGroupList->turnoverGroup;
      $turnoverGroups = objectToArray($soapTurnoverGroups);
      foreach ($turnoverGroups as $idx => $turnoverGroup) {
        if (isset($turnoverGroup['branchAccountNumberList'])) {
          if (isset($turnoverGroup['branchAccountNumberList']['branchAccountNumber'])) {
            $turnoverGroups[$idx]['branchAccountNumberList'] = $turnoverGroup['branchAccountNumberList']['branchAccountNumber'];
          }
        }
      } // endforeach ($turnoverGroups)
    }
    return $turnoverGroups;
  } // END parseGetTurnoverGroupsResult()

  //----------------------------------------------------------------------------

  public function parseUpdateTurnoverGroupsResult($soapUpdateTurnoverGroupsResult) {
    if (isset($soapUpdateTurnoverGroupsResult->result) and $soapUpdateTurnoverGroupsResult->result == 'UPDATE-TURNOVER-GROUPS-RESULT-OK') {
      return true;
    }
    return false;
  } // END parseUpdateTurnoverGroupsResult()

  //----------------------------------------------------------------------------

  public function parseGetDeliveryMethodsResult($soap)
  {
    $deliveryMethods = [];
    if (isset($soap->deliveryMethodList->deliveryMethod)) {
      $soap = $soap->deliveryMethodList->deliveryMethod;
      $deliveryMethods = objectToArray($soap);
    }
    return $deliveryMethods;
  }

  //----------------------------------------------------------------------------

  public function parseGetDeliveryMethodsV2Result($soap) 
  {
    return $this->parseGetDeliveryMethodsResult($soap);
  }  

  //----------------------------------------------------------------------------

  public function parseCreateDeliveryMethodResult($in) 
  {
    $result = ['result'=>$in->result];
    if (isset($in->deliveryMethod)) {
      $result['deliveryMethod'] = $in->deliveryMethod;
    }
    if (isset($in->errorMessage)) {
      $result['errorMessage '] = $in->errorMessage;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseUpdateDeliveryMethodResult($in) 
  {
    $result = ['result'=>$in->result];
    if (isset($in->deliveryMethod)) {
      $result['deliveryMethod'] = $in->deliveryMethod;
    }
    if (isset($in->errorMessage)) {
      $result['errorMessage '] = $in->errorMessage;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseGetPaymentMethodsResult($soapGetPaymentMethodsResult) {
    $paymentMethods = array();
    if (isset($soapGetPaymentMethodsResult->paymentMethodList->paymentMethod)) {
      $soapPaymentMethods = $soapGetPaymentMethodsResult->paymentMethodList->paymentMethod;
      $paymentMethods = objectToArray($soapPaymentMethods);
    }
    return $paymentMethods;
  } // END parseGetPaymentMethodsResult()

  //----------------------------------------------------------------------------

  public function parseGetRetailSpaceRentalResult($soapGetRetailSpaceRentalResult) {
    if (isset($soapGetRetailSpaceRentalResult->result) and $soapGetRetailSpaceRentalResult->result == 'GET-RETAIL-SPACE-RENTAL-RESULT-OK') {
      if (isset($soapGetRetailSpaceRentalResult->retailSpaceRental)) {
        return objectToArray($soapGetRetailSpaceRentalResult->retailSpaceRental);
      }
    }
    return false;
  } // END parseGetRetailSpaceRentalResult()

  //----------------------------------------------------------------------------

  public function parseGetRetailSpaceRentalsResult($soapGetRetailSpaceRentalResult) {
    $retailSpaceRentals = array();
    if (isset($soapGetRetailSpaceRentalResult->retailSpaceRentalList->retailSpaceRental)) {
      $soapRetailSpaceRentals = $soapGetRetailSpaceRentalResult->retailSpaceRentalList->retailSpaceRental;
      $retailSpaceRentals = objectToArray($soapRetailSpaceRentals);
    }
    return $retailSpaceRentals;
  } // END parseGetRetailSpaceRentalsResult()

  //----------------------------------------------------------------------------

  public function parseExchangeRateHistoryList($soapExchangeRateHistory)
  {
    $exchangeRateHistory = objectToArray($soapExchangeRateHistory);
    foreach ($exchangeRateHistory as $idx => $erh) {
      $erh['timestamp'] = $this->parseMplusDateTime($erh['timestamp']);
      if (isset($erh['buyPriceOld'])) {
        $erh['buyPriceOld'] = from_quantity_and_decimal_places($erh['buyPriceOld'], isset($erh['buyPriceDecimalPlacesOld'])?$erh['buyPriceDecimalPlacesOld']:0);
        unset($erh['buyPriceDecimalPlacesOld']);
      }
      if (isset($erh['buyPriceNew'])) {
        $erh['buyPriceNew'] = from_quantity_and_decimal_places($erh['buyPriceNew'], isset($erh['buyPriceDecimalPlacesNew'])?$erh['buyPriceDecimalPlacesNew']:0);
        unset($erh['buyPriceDecimalPlacesNew']);
      }
      if (isset($erh['sellPriceOld'])) {
        $erh['sellPriceOld'] = from_quantity_and_decimal_places($erh['sellPriceOld'], isset($erh['sellPriceDecimalPlacesOld'])?$erh['sellPriceDecimalPlacesOld']:0);
        unset($erh['sellPriceDecimalPlacesOld']);
      }
      if (isset($erh['sellPriceNew'])) {
        $erh['sellPriceNew'] = from_quantity_and_decimal_places($erh['sellPriceNew'], isset($erh['sellPriceDecimalPlacesNew'])?$erh['sellPriceDecimalPlacesNew']:0);
        unset($erh['sellPriceDecimalPlacesNew']);
      }
      $exchangeRateHistory[$idx] = $erh;
    }
    return $exchangeRateHistory;
  } // END parseExchangeRateHistoryList()

  //----------------------------------------------------------------------------

  public function parseGetExchangeRateHistoryResult($soapGetExchangeRateHistoryResult) {
    $exchangeRateHistory = array();
    if (isset($soapGetExchangeRateHistoryResult->exchangeRateHistoryList->exchangeRateHistory)) {
      $exchangeRateHistory = $this->parseExchangeRateHistoryList($soapGetExchangeRateHistoryResult->exchangeRateHistoryList->exchangeRateHistory);
    }
    return $exchangeRateHistory;
  } // END parseGetExchangeRateHistoryResult()

  //----------------------------------------------------------------------------

  public function parseUpdateExchangeRateResult($soapUpdateExchangeRateResult) {
    $exchangeRateHistory = array();
    if (isset($soapUpdateExchangeRateResult->exchangeRateHistoryList->exchangeRateHistory)) {
      $exchangeRateHistory = $this->parseExchangeRateHistoryList($soapUpdateExchangeRateResult->exchangeRateHistoryList->exchangeRateHistory);
    }
    return $exchangeRateHistory;
  } // END parseUpdateExchangeRateResult()

  //----------------------------------------------------------------------------

  public function parseGetAllergensResult($soapGetAllergensResult) {
    $allergens = array();
    if (isset($soapGetAllergensResult->allergens->allergen)) {
      $soapAllergens = $soapGetAllergensResult->allergens->allergen;
      $allergens = objectToArray($soapAllergens);
    }
    return $allergens;
  } // END parseGetAllergensResult()

  //----------------------------------------------------------------------------

  public function parseGetWebhookConsumersResult($soapGetWebhookConsumersResult) {
    $webhookConsumers = array();
    if (isset($soapGetWebhookConsumersResult->webhookConsumerList->webhookConsumer)) {
      $soapWebhookConsumers = $soapGetWebhookConsumersResult->webhookConsumerList->webhookConsumer;
      $webhookConsumers = objectToArray($soapWebhookConsumers);
    }
    return $webhookConsumers;
  } // END parseGetWebhookConsumersResult()

  //----------------------------------------------------------------------------

  public function parseGetTicketCounterSalesResult($soapGetTicketCounterSalesResult) {
    $ticketCounterSales = array();
    if (isset($soapGetTicketCounterSalesResult->ticketCounterSaleList->ticketCounterSale)) {
      $soapTicketCounterSales = $soapGetTicketCounterSalesResult->ticketCounterSaleList->ticketCounterSale;
      $ticketCounterSales = objectToArray($soapTicketCounterSales);
    }
    return $ticketCounterSales;
  } // END parseGetTicketCounterSalesResult()

  //----------------------------------------------------------------------------

  public function parseGetConfigurationResult($soapGetConfigurationResult) {
    $configuration = array();
    if (isset($soapGetConfigurationResult->configurationList->configuration)) {
      $soapConfiguration = $soapGetConfigurationResult->configurationList->configuration;
      $configuration = objectToArray($soapConfiguration);
    }
    return $configuration;
  } // END parseGetConfigurationResult()

  //----------------------------------------------------------------------------

  public function parseUpdateConfigurationResult($soapUpdateConfigurationResult) {
    if (isset($soapUpdateConfigurationResult->result) and $soapUpdateConfigurationResult->result == 'UPDATE-CONFIGURATION-RESULT-OK') {
      return true;
    }
    return false;
  } // END parseUpdateConfigurationResult()

  //----------------------------------------------------------------------------

  public function parseGetPurchaseOrdersResult($soapGetPurchaseOrdersResult) {
    $purchaseOrders = array();
    if (isset($soapGetPurchaseOrdersResult->purchaseOrderList->purchaseOrder)) {
      $soapPurchaseOrders = $soapGetPurchaseOrdersResult->purchaseOrderList->purchaseOrder;
      $purchaseOrders = objectToArray($soapPurchaseOrders);
    }
    return $purchaseOrders;
  } // END parseGetPurchaseOrdersResult()

  //----------------------------------------------------------------------------

  public function parseGetPurchaseDeliveriesResult($soapGetPurchaseDeliveriesResult) {
    $purchaseDeliveries = array();
    if (isset($soapGetPurchaseDeliveriesResult->purchaseDeliveryList->purchaseDelivery)) {
      $soapPurchaseDeliveries = $soapGetPurchaseDeliveriesResult->purchaseDeliveryList->purchaseDelivery;
      $purchaseDeliveries = objectToArray($soapPurchaseDeliveries);
    }
    return $purchaseDeliveries;
  } // END parseGetPurchaseDeliveriesResult()

  //----------------------------------------------------------------------------

  public function parseGetBranchesResult($soapGetBranchesResult) {
    $branches = array();
    if (isset($soapGetBranchesResult->branches->branch)) {
      $soapBranches = $soapGetBranchesResult->branches->branch;
      $branches = objectToArray($soapBranches);
      foreach ($branches as $idx => $branch) {
        if (isset($branch['workplaces'])) {
          if (isset($branch['workplaces']['workplace'])) {
            $branches[$idx]['workplaces'] = $branch['workplaces']['workplace'];
          }
        }
      }
    }
    return $branches;
  } // END parseGetBranchesResult()
  
  //----------------------------------------------------------------------------

  public function parseGetBranchGroupsResult($soapGetBranchGroupsResult) {
    $branchGroups = array();
    if (isset($soapGetBranchGroupsResult->branchGroupsList->branchGroups)) {
      $soapBranchGroups = $soapGetBranchGroupsResult->branchGroupsList->branchGroups;
      return objectToArray($soapBranchGroups);
    }
    return $branchGroups;
  } // END parseGetBranchGroupsResult()

  //----------------------------------------------------------------------------

  public function parseGetTableOrderResult($soapGetTableOrderResult) {
    if (isset($soapGetTableOrderResult->result)) {
      if ($soapGetTableOrderResult->result == 'GET-TABLE-ORDER-RESULT-OK') {
        if (isset($soapGetTableOrderResult->order)) {
          $soapOrder = $soapGetTableOrderResult->order;
          $order = objectToArray($soapOrder);
          if (isset($order['financialDate'])) {
            $order['financialDate'] = $this->parseMplusDate($order['financialDate']);
          }
          if (isset($order['entryTimestamp'])) {
            $order['entryTimestamp'] = $this->parseMplusDateTime($order['entryTimestamp']);
          }
          if (isset($order['lineList']['line'])) {
            $order['lineList'] = $order['lineList']['line'];
          }
          foreach ($order['lineList'] as $idx => $line) {
            if (isset($line['preparationList']['line'])) {
              $line['preparationList'] = $line['preparationList']['line'];
            }
            $order['lineList'][$idx] = $line;
          }
          return $order;
        }
      } else
      if ($soapGetTableOrderResult->result == 'GET-TABLE-ORDER-RESULT-ALREADY-USED') {
        if (isset($soapGetTableOrderResult->order)) {
          $soapOrder = $soapGetTableOrderResult->order;
          $order = array(
            'orderId' => $soapOrder->orderId,
            );
          return $order;
        }
      }
    }
    return false;
  } // END parseGetTableOrderResult()

  //----------------------------------------------------------------------------

  public function parseGetTableOrderCourseListResult($soapTableOrderCourseList) {    
    if (isset($soapTableOrderCourseList->result)) {
      if ($soapTableOrderCourseList->result == 'GET-TABLE-ORDER-COURSE-LIST-OK') {
        $course_list = array();
        if (isset($soapTableOrderCourseList->courseList->course)) {
          $soapTableOrderCourseList = $soapTableOrderCourseList->courseList->course;
          foreach ($soapTableOrderCourseList as $soapTableOrderCourse) {
            $course_list[] = objectToArray($soapTableOrderCourse);
          }
        }
        return $course_list;
      }
    }
    return false;
  } // END parseGetTableOrderCourseListResult()

  //----------------------------------------------------------------------------

  public function parseSaveTableOrderResult($soapSaveTableOrderResult) {
    if (isset($soapSaveTableOrderResult->result)) {
      if ($soapSaveTableOrderResult->result == 'SAVE-TABLE-ORDER-RESULT-OK') {
        return true;
      }
      elseif ($soapSaveTableOrderResult->result == 'SAVE-TABLE-ORDER-RESULT-ORDER-HAS-CHANGED') {
        if (isset($soapSaveTableOrderResult->errorMessage) and ! empty($soapSaveTableOrderResult->errorMessage)) {
          throw new MplusQAPIException($soapSaveTableOrderResult->errorMessage);
        }
        else {
          throw new MplusQAPIException('Table order has been changed from another terminal.');
        }
      } else {
        if (isset($soapSaveTableOrderResult->errorMessage) and ! empty($soapSaveTableOrderResult->errorMessage)) {
          throw new MplusQAPIException($soapSaveTableOrderResult->errorMessage);
        }
        else {
          throw new MplusQAPIException('Unknown error.');
        }
      }
    }
    throw new MplusQAPIException('Unknown error.');
  } // END parseSaveTableOrderResult()

  //----------------------------------------------------------------------------

  public function parseFindEmployeeResult($soapFindEmployeeResult) {
    if (isset($soapFindEmployeeResult->result) and $soapFindEmployeeResult->result == 'FIND-EMPLOYEE-RESULT-OK') {
      if (isset($soapFindEmployeeResult->employee)) {
        return objectToArray($soapFindEmployeeResult->employee);
      }
    }
    return false;
  } // END parseFindEmployeeResult()

  //----------------------------------------------------------------------------

  public function parseFindRelationResult($soapFindRelationResult) {
    if (isset($soapFindRelationResult->result) and $soapFindRelationResult->result == 'FIND-RELATION-RESULT-OK') {
      if (isset($soapFindRelationResult->relation)) {
        return objectToArray($soapFindRelationResult->relation);
      }
    }
    return false;
  } // END parseFindRelationResult()

  //----------------------------------------------------------------------------

  public function parseGetRelationResult($soapGetRelationResult)
  {
    if (isset($soapGetRelationResult->result) and $soapGetRelationResult->result == 'GET-RELATION-RESULT-OK') {
      if (isset($soapGetRelationResult->relation)) {
        $array = objectToArray($soapGetRelationResult->relation);
        if (isset($array['changeTimestamp'])) {
          $array['changeTimestamp'] = $this->parseMplusDateTime($array['changeTimestamp']);
        }
        if (isset($array['createTimestamp'])) {
          $array['createTimestamp'] = $this->parseMplusDateTime($array['createTimestamp']);
        }
        return $array;
      }
    }
    return false;
  } // END parseGetRelationResult()

  //----------------------------------------------------------------------------

  public function parseAdjustPointsResult($soapAdjustPointsResult)
  {
    if (isset($soapAdjustPointsResult->result) and $soapAdjustPointsResult->result == 'ADJUST-POINTS-RESULT-OK') {
      if (isset($soapAdjustPointsResult->relation)) {
        return objectToArray($soapAdjustPointsResult->relation);
      } else {
        return true;
      }
    }
    return false;
  } // END parseAdjustPointsResult()

  //----------------------------------------------------------------------------
  
  public function parseRelationPoints($soapRelationPoints)
  {
    if (isset($soapRelationPoints->relationPointsLst)) {
        return objectToArray($soapRelationPoints->relationPointsLst);
    }
    else {
        return array();
    }
  }  
  
  //----------------------------------------------------------------------------

  public function parseRegisterTerminalResult($soapRegisterTerminalResult)
  {
    if (isset($soapRegisterTerminalResult->result)) {
      if ($soapRegisterTerminalResult->result == 'REGISTER-TERMINAL-RESULT-OK') {
        return true;
      }
      else if ($soapRegisterTerminalResult->result == 'REGISTER-TERMINAL-RESULT-REGISTERED') {
        if (isset($soapRegisterTerminalResult->errorMessage)) {
          throw new MplusQAPIException($soapRegisterTerminalResult->errorMessage);
        }
        else {
          throw new MplusQAPIException('Requested terminal already registered.');
        }
      }
      else if ($soapRegisterTerminalResult->result == 'REGISTER-TERMINAL-RESULT-UNKNOWN') {
        if (isset($soapRegisterTerminalResult->errorMessage)) {
          throw new MplusQAPIException($soapRegisterTerminalResult->errorMessage);
        }
        else {
          throw new MplusQAPIException('Requested terminal unknown.');
        }
      }
      else {
        if (isset($soapRegisterTerminalResult->errorMessage)) {
          throw new MplusQAPIException($soapRegisterTerminalResult->errorMessage);
        }
        else {
          throw new MplusQAPIException('Unknown error.');
        }
      }
    }
  } // END parseRegisterTerminalResult()

  //----------------------------------------------------------------------------

  public function parseUpdateOrderResult($soapUpdateOrderResult) {
    if (isset($soapUpdateOrderResult->result) and $soapUpdateOrderResult->result == 'UPDATE-ORDER-RESULT-OK') {
      return true;
    } else if (isset($soapUpdateOrderResult->result) and $soapUpdateOrderResult->result == 'UPDATE-ORDER-RESULT-FAILED' and $soapUpdateOrderResult->errorMessage == 'Order not saved because there were no changes in the order.') {
      return true;
    } else if (isset($soapUpdateOrderResult->result) and $soapUpdateOrderResult->result == 'UPDATE-ORDER-RESULT-NO-CHANGES') {
      return true;
    } else {
      if ( ! empty($soapUpdateOrderResult->errorMessage)) {
        $this->lastErrorMessage = $soapUpdateOrderResult->errorMessage;
      }
      return false;
    }
  } // END parseUpdateOrderResult()

  //----------------------------------------------------------------------------

  public function parseCreateOrderResult($soapCreateOrderResult) 
  {
    if (isset($soapCreateOrderResult->result) and $soapCreateOrderResult->result == 'CREATE-ORDER-RESULT-OK') {
      if (isset($soapCreateOrderResult->info)) {
        return objectToArray($soapCreateOrderResult->info);
      } else {
        return true;
      }
    } else {
      return false;
    }
  }

  //----------------------------------------------------------------------------

  public function parseCreateOrderV2Result($soapCreateOrderV2Result) 
  {
    if (isset($soapCreateOrderV2Result->result) and $soapCreateOrderV2Result->result == 'CREATE-ORDER-RESULT-OK') {
      if (isset($soapCreateOrderV2Result->order)) {
        return $this->parseOrder($soapCreateOrderV2Result->order);
      } else {
        return true;
      }
    } else {
      return false;
    }
  }

  //----------------------------------------------------------------------------

  public function parseSaveOrderResult($soapSaveOrderResult)
  {
    if (isset($soapSaveOrderResult->result) and $soapSaveOrderResult->result == 'SAVE-ORDER-RESULT-OK') {
      if (isset($soapSaveOrderResult->info)) {
        return objectToArray($soapSaveOrderResult->info);
      } else {
        return true;
      }
    } else if (isset($soapSaveOrderResult->result) and $soapSaveOrderResult->result == 'SAVE-ORDER-RESULT-FAILED' and $soapSaveOrderResult->errorMessage == 'Order not saved because there were no changes in the order.') {
      return true;
    } else if (isset($soapSaveOrderResult->result) and $soapSaveOrderResult->result == 'SAVE-ORDER-RESULT-NO-CHANGES') {
      return true;
    } else if (isset($soapSaveOrderResult->result) and $soapSaveOrderResult->result == 'SAVE-ORDER-RESULT-ORDER-HAS-CHANGED') {
      $this->lastErrorMessage = 'Order has changed. Please send up-to-date version versionNumber and changeCounter.';
    }
    if (!empty($soapSaveOrderResult->errorMessage)) {
      $this->lastErrorMessage = $soapSaveOrderResult->errorMessage;
    }
    return false;
  } // END parseSaveOrderResult()

  //----------------------------------------------------------------------------

  public function parseQueueBranchOrderResult($soapQueueBranchOrderResult)
  {
    if (isset($soapQueueBranchOrderResult->result) and $soapQueueBranchOrderResult->result == 'QUEUE-BRANCH-ORDER-RESULT-OK') {
      if (isset($soapQueueBranchOrderResult->info)) {
        return objectToArray($soapQueueBranchOrderResult->info);
      } else {
        return true;
      }
    } else {
      if ( ! empty($soapQueueBranchOrderResult->errorMessage)) {
        $this->lastErrorMessage = $soapQueueBranchOrderResult->errorMessage;
      }
      return false;
    }
  } // END parseQueueBranchOrderResult()

  //----------------------------------------------------------------------------

  public function parseMoveTableOrderResult($soapMoveTableOrderResult)
  {
    if (isset($soapMoveTableOrderResult->result) and $soapMoveTableOrderResult->result == 'MOVE-TABLE-ORDER-RESULT-OK') {
      return true;
    } else {
      if (isset($soapMoveTableOrderResult->errorMessage)) {
        return $soapMoveTableOrderResult->errorMessage;
      } else {
        return false;
      }
    }
  } // END parseMoveTableOrderResult()

  //----------------------------------------------------------------------------

  public function parseCancelOrderResult($soapCancelOrderResult)
  {
    if (isset($soapCancelOrderResult->result) and $soapCancelOrderResult->result == 'CANCEL-ORDER-RESULT-OK') {
      return true;
    } else {
      if (isset($soapCancelOrderResult->message)) {
        return $soapCancelOrderResult->message;
      } else {
        return false;
      }
    }
  } // END parseCancelOrderResult()

  //----------------------------------------------------------------------------

  public function parseCreateOrderFromProposalResult($soapCreateOrderFromProposalResult)
  {
    if (isset($soapCreateOrderFromProposalResult->result) and $soapCreateOrderFromProposalResult->result == 'CREATE-ORDER-FROM-PROPOSAL-RESULT-OK') {
      return true;
    } else {
      if (isset($soapCreateOrderFromProposalResult->errorMessage)) {
        return $soapCreateOrderFromProposalResult->errorMessage;
      } else {
        return false;
      }
    }
  } // END parseCreateOrderFromProposalResult()

  //----------------------------------------------------------------------------

  public function parseCreateInvoiceFromProposalResult($soapCreateInvoiceFromProposalResult)
  {
    if (isset($soapCreateInvoiceFromProposalResult->result) and $soapCreateInvoiceFromProposalResult->result == 'CREATE-INVOICE-FROM-PROPOSAL-RESULT-OK') {
      return true;
    } else {
      if (isset($soapCreateInvoiceFromProposalResult->errorMessage)) {
        return $soapCreateInvoiceFromProposalResult->errorMessage;
      } else {
        return false;
      }
    }
  } // END parseCreateInvoiceFromProposalResult()

  //----------------------------------------------------------------------------

  public function parseCancelProposalResult($soapCancelProposalResult)
  {
    if (isset($soapCancelProposalResult->result) and $soapCancelProposalResult->result == 'CANCEL-PROPOSAL-RESULT-OK') {
      return true;
    } else {
      if (isset($soapCancelProposalResult->message)) {
        return $soapCancelProposalResult->message;
      } else {
        return false;
      }
    }
  } // END parseCancelProposalResult()

  //----------------------------------------------------------------------------

  public function parseQueueBranchOrderPaymentResult($soapResult)
  {
    if ($soapResult->result == 'QUEUE-BRANCH-ORDER-PAYMENT-RESULT-OK') {
      return true;
    }
    return $soapResult->errorMessage;
  }

  //----------------------------------------------------------------------------
  public function parseUpdateStockResult($soapUpdateStockResult)
  {
    if (isset($soapUpdateStockResult->result) and $soapUpdateStockResult->result == 'UPDATE-STOCK-RESULT-OK') {
      if (isset($soapUpdateStockResult->stockId) and ! empty($soapUpdateStockResult->stockId)) {
        return $soapUpdateStockResult->stockId;
      } else {
        return true;
      }
    } elseif (isset($soapUpdateStockResult->result) and $soapUpdateStockResult->result == 'UPDATE-STOCK-RESULT-NO-STOCK-ARTICLE') {
      $this->lastErrorMessage = $soapUpdateStockResult->result;
      return false;
    } else {
      return false;
    }
  } // END parseUpdateStockResult()

  //----------------------------------------------------------------------------

  public function parseSetStockResult($soapSetStockResult)
  {
    if (isset($soapSetStockResult->result) and $soapSetStockResult->result == 'SET-STOCK-RESULT-OK') {
      if (isset($soapSetStockResult->stockId) and ! empty($soapSetStockResult->stockId)) {
        return $soapSetStockResult->stockId;
      } else {
        return true;
      }
    } elseif (isset($soapSetStockResult->result) and $soapSetStockResult->result == 'SET-STOCK-RESULT-NO-STOCK-ARTICLE') {
      $this->lastErrorMessage = $soapSetStockResult->result;
      return false;
    } else {
      return false;
    }
  } // END parseSetStockResult()

  //----------------------------------------------------------------------------

  public function parseSaveInvoiceResult($soapSaveInvoiceResult)
  {
    if (isset($soapSaveInvoiceResult->result) and $soapSaveInvoiceResult->result == 'SAVE-INVOICE-RESULT-OK') {
      if (isset($soapSaveInvoiceResult->info)) {
        return objectToArray($soapSaveInvoiceResult->info);
      } else {
        return true;
      }
    } else {
      if ( ! empty($soapSaveInvoiceResult->errorMessage)) {
        $this->lastErrorMessage = $soapSaveInvoiceResult->errorMessage;
      }
      return false;
    }
  } // END parseSaveInvoiceResult()

  //----------------------------------------------------------------------------

  public function parseSaveProposalResult($soapSaveProposalResult)
  {
    if (isset($soapSaveProposalResult->result) and $soapSaveProposalResult->result == 'SAVE-PROPOSAL-RESULT-OK') {
      if (isset($soapSaveProposalResult->info)) {
        return objectToArray($soapSaveProposalResult->info);
      } else {
        return true;
      }
    } else {
      if ( ! empty($soapSaveProposalResult->errorMessage)) {
        $this->lastErrorMessage = $soapSaveProposalResult->errorMessage;
      }
      return false;
    }
  } // END parseSaveProposalResult()

  //----------------------------------------------------------------------------

  public function parseSavePurchaseOrderResult($soapSavePurchaseOrderResult)
  {
    if (isset($soapSavePurchaseOrderResult->result) and $soapSavePurchaseOrderResult->result == 'SAVE-PURCHASE-ORDER-RESULT-OK') {
      if (isset($soapSavePurchaseOrderResult->info)) {
        return objectToArray($soapSavePurchaseOrderResult->info);
      } else {
        return true;
      }
    } else {
      if ( ! empty($soapSavePurchaseOrderResult->errorMessage)) {
        $this->lastErrorMessage = $soapSavePurchaseOrderResult->errorMessage;
      }
      return false;
    }
  } // END parseSavePurchaseOrderResult()

  //----------------------------------------------------------------------------

  public function parseSavePurchaseDeliveryResult($soapSavePurchaseDeliveryResult)
  {
    if (isset($soapSavePurchaseDeliveryResult->result) and $soapSavePurchaseDeliveryResult->result == 'SAVE-PURCHASE-DELIVERY-RESULT-OK') {
      if (isset($soapSavePurchaseDeliveryResult->info)) {
        return objectToArray($soapSavePurchaseDeliveryResult->info);
      } else {
        return true;
      }
    } else {
      if ( ! empty($soapSavePurchaseDeliveryResult->errorMessage)) {
        $this->lastErrorMessage = $soapSavePurchaseDeliveryResult->errorMessage;
      }
      return false;
    }
  } // END parseSavePurchaseDeliveryResult()

  //----------------------------------------------------------------------------

  public function parseSendMessageResult($soapSendMessageResult) {
    if (isset($soapSendMessageResult->response)) {
      if (is_bool($soapSendMessageResult->response)) {
        return $soapSendMessageResult->response;
      } else {
        return strtolower($soapSendMessageResult->response) == 'true';
      }
    }
    return false;
  } // END parseSendMessageResult()

//----------------------------------------------------------------------------

  public function parseEncryptStringResult($soapEncryptStringResult) {
    if (isset($soapEncryptStringResult->encryptedString)) {
      return $soapEncryptStringResult->encryptedString;
    }
    return false;
  } // END parseEncryptStringResult()

  //----------------------------------------------------------------------------

  public function parseCreateRelationResult($soapCreateRelationResult) {
    $result = false;
    if (isset($soapCreateRelationResult->result) and $soapCreateRelationResult->result == 'CREATE-RELATION-RESULT-OK') {
      $result = true;
      if (isset($soapCreateRelationResult->relationNumber) or isset($soapCreateRelationResult->changeTimestamp) or isset($soapCreateRelationResult->syncMarker)) {
        $result = array();
      }
      if (isset($soapCreateRelationResult->relationNumber)) {
        $result['relationNumber'] = $soapCreateRelationResult->relationNumber;
      }
      if (isset($soapCreateRelationResult->changeTimestamp)) {
        $result['changeTimestamp'] = $this->parseMplusDateTime(objectToArray($soapCreateRelationResult->changeTimestamp));
      }
      if (isset($soapCreateRelationResult->syncMarker)) {
        $result['syncMarker'] = $soapCreateRelationResult->syncMarker;
      }
    }
    return $result;
  } // END parseCreateRelationResult()

  //----------------------------------------------------------------------------

  public function parseUpdateRelationResult($soapUpdateRelationResult) {
    $result = false;
    if (isset($soapUpdateRelationResult->result) and $soapUpdateRelationResult->result == 'UPDATE-RELATION-RESULT-OK') {
      $result = true;
      if (isset($soapUpdateRelationResult->changeTimestamp) or isset($soapUpdateRelationResult->syncMarker)) {
        $result = array();
      }
      if (isset($soapUpdateRelationResult->changeTimestamp)) {
        $result['changeTimestamp'] = $this->parseMplusDateTime(objectToArray($soapUpdateRelationResult->changeTimestamp));
      }
      if (isset($soapUpdateRelationResult->syncMarker)) {
        $result['syncMarker'] = $soapUpdateRelationResult->syncMarker;
      }
      return $result;
    }
    return $result;
  } // END parseUpdateRelationResult()

  //----------------------------------------------------------------------------

  public function parseCreateEmployeeResult($soapCreateEmployeeResult) {
    $result = false;
    if (isset($soapCreateEmployeeResult->result) and $soapCreateEmployeeResult->result == 'CREATE-EMPLOYEE-RESULT-OK') {
      $result = true;
      if (isset($soapCreateEmployeeResult->employeeNumber) or isset($soapCreateEmployeeResult->changeTimestamp) or isset($soapCreateEmployeeResult->syncMarker)) {
        $result = array();
      }
      if (isset($soapCreateEmployeeResult->employeeNumber)) {
        $result['employeeNumber'] = $soapCreateEmployeeResult->employeeNumber;
      }
      if (isset($soapCreateEmployeeResult->changeTimestamp)) {
        $result['changeTimestamp'] = $this->parseMplusDateTime(objectToArray($soapCreateEmployeeResult->changeTimestamp));
      }
      if (isset($soapCreateEmployeeResult->syncMarker)) {
        $result['syncMarker'] = $soapCreateEmployeeResult->syncMarker;
      }
    }
    return $result;
  } // END parseCreateEmployeeResult()

  //----------------------------------------------------------------------------

  public function parseUpdateEmployeeResult($soapUpdateEmployeeResult) {
    $result = false;
    if (isset($soapUpdateEmployeeResult->result) and $soapUpdateEmployeeResult->result == 'UPDATE-EMPLOYEE-RESULT-OK') {
      $result = true;
      if (isset($soapUpdateEmployeeResult->changeTimestamp) or isset($soapUpdateEmployeeResult->syncMarker)) {
        $result = array();
      }
      if (isset($soapUpdateEmployeeResult->changeTimestamp)) {
        $result['changeTimestamp'] = $this->parseMplusDateTime(objectToArray($soapUpdateEmployeeResult->changeTimestamp));
      }
      if (isset($soapUpdateEmployeeResult->syncMarker)) {
        $result['syncMarker'] = $soapUpdateEmployeeResult->syncMarker;
      }
      return $result;
    }
    return $result;
  } // END parseUpdateEmployeeResult()

  //----------------------------------------------------------------------------

  public function parseCreateProductResult($soapCreateProductResult) {
    $result = false;
    if (isset($soapCreateProductResult->result) and $soapCreateProductResult->result == 'CREATE-PRODUCT-RESULT-OK') {
      $result = true;
      if (isset($soapCreateProductResult->productNumber) or isset($soapCreateProductResult->articleNumbers) or isset($soapUpdateProductResult->changeTimestamp) or isset($soapUpdateProductResult->syncMarker)) {
        $result = array();
      }
      if (isset($soapCreateProductResult->productNumber)) {
        $result['productNumber'] = $soapCreateProductResult->productNumber;
      }
      if (isset($soapCreateProductResult->articleNumbers)) {
        $result['articleNumbers'] = objectToArray($soapCreateProductResult->articleNumbers);
      }
      if (isset($soapCreateProductResult->changeTimestamp)) {
        $result['changeTimestamp'] = $this->parseMplusDateTime(objectToArray($soapCreateProductResult->changeTimestamp));
      }
      if (isset($soapCreateProductResult->syncMarker)) {
        $result['syncMarker'] = $soapCreateProductResult->syncMarker;
      }
      return $result;
    }
    return $result;
  } // END parseCreateProductResult()

  //----------------------------------------------------------------------------

  public function parseUpdateProductResult($soapUpdateProductResult) {
    $result = false;
    if (isset($soapUpdateProductResult->result) and $soapUpdateProductResult->result == 'UPDATE-PRODUCT-RESULT-OK') {
      $result = true;
      if (isset($soapUpdateProductResult->existingArticleNumbers) or isset($soapUpdateProductResult->newArticleNumbers) or isset($soapUpdateProductResult->changeTimestamp) or isset($soapUpdateProductResult->syncMarker)) {
        $result = array();
      }
      if (isset($soapUpdateProductResult->newArticleNumbers)) {
        $result['newArticleNumbers'] = objectToArray($soapUpdateProductResult->newArticleNumbers);
      }
      if (isset($soapUpdateProductResult->existingArticleNumbers)) {
        $result['existingArticleNumbers'] = objectToArray($soapUpdateProductResult->existingArticleNumbers);
      }
      if (isset($soapUpdateProductResult->changeTimestamp)) {
        $result['changeTimestamp'] = $this->parseMplusDateTime(objectToArray($soapUpdateProductResult->changeTimestamp));
      }
      if (isset($soapUpdateProductResult->syncMarker)) {
        $result['syncMarker'] = $soapUpdateProductResult->syncMarker;
      }
    }
    return $result;
  } // END parseUpdateProductResult()

  //----------------------------------------------------------------------------

  public function parseGetActivitiesResult($soapGetActivitiesResult) 
  {
    $activities = array();
    if (isset($soapGetActivitiesResult->activityList->activity)) {
      foreach ($soapGetActivitiesResult->activityList->activity as $soapActivity) {
        $activities[] = objectToArray($soapActivity);
      }
    }
    return $activities;
  } // END parseGetActivitiesResult()

  //----------------------------------------------------------------------------

  public function parseCreateActivityResult($in) 
  {
    $result = ['result'=>$in->result];
    if (isset($in->activity)) {
      $result['activity'] = $in->activity;
    }
    if (isset($in->errorMessage)) {
      $result['errorMessage '] = $in->errorMessage;
    }
    return $result;
  }

  //----------------------------------------------------------------------------

  public function parseUpdateActivityResult($in) 
  {
    $result = ['result'=>$in->result];
    if (isset($in->activity)) {
      $result['activity'] = $in->activity;
    }
    if (isset($in->errorMessage)) {
      $result['errorMessage '] = $in->errorMessage;
    }
    return $result;
  } // END parseUpdateActivityResult()

  //----------------------------------------------------------------------------

  public function parseDeleteActivityResult($in) 
  {
    $result = ['result'=>$in->result];
    if (isset($in->errorMessage)) {
      $result['errorMessage '] = $in->errorMessage;
    }
    return $result;
  } // END parseDeleteActivityResult()

  //----------------------------------------------------------------------------

  public function parseVerifyCredentialsResult($in)
  {
    if (isset($in->verified)) {
      $parsed = ['verified'=>$in->verified];
      if (isset($in->employee)) {
        $parsed['employee'] = objectToArray($in->employee);
      }
      return $parsed;
    }
    return false;
  }

  //----------------------------------------------------------------------------
  public function parseReportResult($method, $soapReportResult)
  {
      $data = null;
      switch ($method) {
          case "reportTurnover":
          case "reportTurnoverByBranch":
          case "reportTurnoverByEmployee":
          case "reportTurnoverByTurnoverGroup":
          case "reportTurnoverByArticle":
          case "reportTurnoverByActivity":
              $data = array();
              if (isset($soapReportResult->turnoverList->turnover)) {
                  foreach ($soapReportResult->turnoverList->turnover as $soapTurnover) {
                      $data[] = $soapTurnover;
                  }
              }
              break;
          case "reportHoursByEmployee":
              $data = array();
              if (isset($soapReportResult->hoursList->hours)) {
                  foreach ($soapReportResult->hoursList->hours as $soapHours) {
                      $data[] = $soapHours;
                  }
              }
              break;
          case "reportPaymentMethods":
              $data = array();
              if (isset($soapReportResult->paymentMethodsList->paymentMethods)) {
                  foreach ($soapReportResult->paymentMethodsList->paymentMethods as $soapPaymentMethods) {
                      $data[] = $soapPaymentMethods;
                  }
              }
              break;
          case "reportTables":
              $data = array();
              if (isset($soapReportResult->tablesList->tables)) {
                  foreach ($soapReportResult->tablesList->tables as $soapTable) {
                      $data[] = $soapTable;
                  }
              }
              break;
          case "reportCancellations":
                $data = array();
                if (isset($soapReportResult->cancellationsList->cancellations)) {
                    foreach ($soapReportResult->cancellationsList->cancellations as $soapCancellations) {
                        $data[] = $soapCancellations;
                    }
                }
                break;
      }
      return $data;
  } // END parseReportResult()

  //----------------------------------------------------------------------------

  public function parseGetSalePromotionsResult($soapGetSalePromotionsResult) {
    $salePromotions = array();
    if (isset($soapGetSalePromotionsResult->salePromotionsList->salePromotions)) {
      $soapSalePromotions = $soapGetSalePromotionsResult->salePromotionsList->salePromotions;
      $salePromotions = objectToArray($soapSalePromotions);
      foreach ($salePromotions as $idx => $salePromotion) {
        if (isset($salePromotion['salePromotionLineList']['salePromotionLineList'])) {
          if (isset($salePromotion['salePromotionLineList']['salePromotionLineList'])) {
            $salePromotions[$idx]['salePromotionLineList'] = $salePromotion['salePromotionLineList']['salePromotionLineList'];
            foreach($salePromotions[$idx]['salePromotionLineList'] as $idy=>$salePromotionLine) {
                if(isset($salePromotionLine['salePromotionLineDiscountList']['salePromotionLineDiscountList'])) {
                    $salePromotions[$idx]['salePromotionLineList'][$idy]['salePromotionLineDiscountList'] = $salePromotions[$idx]['salePromotionLineList'][$idy]['salePromotionLineDiscountList']['salePromotionLineDiscountList'];
                }          
            }
          }
        }
      }
    }
    return $salePromotions;
  } // END parseGetSalePromotionsResult()
  

  //----------------------------------------------------------------------------

  public function convertGetDeliveryMethodsV2Request($request)
  {
    $object = arrayToObject(['request'=>$request]);
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertCreateDeliveryMethodRequest($createDeliveryMethod)
  {
    $object = arrayToObject(array('request'=>array('deliveryMethod'=>$createDeliveryMethod)));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertUpdateDeliveryMethodRequest($updateDeliveryMethod)
  {
    $object = arrayToObject(array('request'=>array('deliveryMethod'=>$updateDeliveryMethod)));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertGetPaymentMethodsV2Request($accountNumber)
  {
    $array = array('request'=>array());
    if ( ! is_null($accountNumber) and strlen($accountNumber) > 0) {
      $array['request']['accountNumber'] = $accountNumber;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetPaymentMethodsV2Request()

  //----------------------------------------------------------------------------

  public function convertGetRetailSpaceRentalRequest($retailSpaceRentalNumber=null, $retailSpaceRentalBarcode=null)
  {
    $array = array('request'=>array());
    if ( ! is_null($retailSpaceRentalNumber) and strlen($retailSpaceRentalNumber) > 0) {
      $array['request']['retailSpaceRentalNumber'] = $retailSpaceRentalNumber;
    }
    if ( ! is_null($retailSpaceRentalBarcode) and strlen($retailSpaceRentalBarcode) > 0) {
      $array['request']['retailSpaceRentalBarcode'] = $retailSpaceRentalBarcode;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetRetailSpaceRentalRequest()

  //----------------------------------------------------------------------------

  public function convertGetRelationArticleDiscountsRequest($relationNumbers, $articleNumbers)
  {
    $array = array('request'=>array());
    if (is_array($relationNumbers) and ! empty($relationNumbers)) {
      $array['request']['relationNumbers'] = $relationNumbers;
    }
    if (is_array($articleNumbers) and ! empty($articleNumbers)) {
      $array['request']['articleNumbers'] = $articleNumbers;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetRelationArticleDiscountsRequest()

  //----------------------------------------------------------------------------

  public function convertGetProductsRequest($productNumbers, $articleNumbers, $includeAllArticlesOfSelectedProducts, $groupNumbers, $pluNumbers, $changedSinceTimestamp, $changedSinceBranchNumber, $syncMarker, $onlyWebshop, $onlyActive, $syncMarkerLimit)
  {
    if (!is_array($productNumbers)) {
      if (is_null($productNumbers)) {
        $productNumbers = [];
      } else {
        $productNumbers = [$productNumbers];
      }
    }
    if ( ! is_array($articleNumbers)) {
      if (is_null($articleNumbers)) {
        $articleNumbers = array();
      } else {
        $articleNumbers = array($articleNumbers);
      }
    }
    if ( ! is_array($groupNumbers)) {
      if (is_null($groupNumbers)) {
        $groupNumbers = array();
        } else {
        $groupNumbers = array($groupNumbers);
      }
    }
    if ( ! is_array($pluNumbers)) {
      if (is_null($pluNumbers)) {
        $pluNumbers = array();
        } else {
        $pluNumbers = array($pluNumbers);
      }
    }
    $array = array('request'=>array(
      'productNumbers'=>empty($productNumbers) ? null : array_values($productNumbers),
      'articleNumbers'=>empty($articleNumbers) ? null : array_values($articleNumbers),
      'includeAllArticlesOfSelectedProducts'=>empty($includeAllArticlesOfSelectedProducts) ? false : (bool)$includeAllArticlesOfSelectedProducts,
      'groupNumbers'=>empty($groupNumbers) ? null : array_values($groupNumbers),
      'pluNumbers'=>empty($pluNumbers) ? null : $this->convertPluNumbers($pluNumbers),
      ));
    if ( ! is_null($changedSinceTimestamp) and ! is_null($changedSinceBranchNumber)) {
      $array['request']['changedSinceTimestamp'] = $this->convertMplusDateTime($changedSinceTimestamp, 'changedSinceTimestamp');
      $array['request']['changedSinceBranchNumber'] = (int)$changedSinceBranchNumber;
    }
    if ( ! is_null($syncMarker)) {
      $array['request']['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) && $syncMarkerLimit > 0) {
        $array['request']['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    if ( ! is_null($onlyWebshop) and is_bool($onlyWebshop)) {
      $array['request']['onlyWebshop'] = $onlyWebshop;
    }
    if ( ! is_null($onlyActive) and is_bool($onlyActive)) {
      $array['request']['onlyActive'] = $onlyActive;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetProductsRequest()

  //----------------------------------------------------------------------------

  public function convertGetRelationsRequest($relationNumbers, $syncMarker, $categoryId, $syncMarkerLimit)
  {
    if ( ! is_array($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    $array = array('request'=>array(
      'relationNumbers'=>empty($relationNumbers) ? null : array_values($relationNumbers),
      ));
    if ( ! is_null($categoryId)) {
      $array['request']['categoryId'] = (int)$categoryId;
    }
    if ( ! is_null($syncMarker)) {
      $array['request']['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['request']['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetRelationsRequest()

  //----------------------------------------------------------------------------

  public function convertGetImagesRequest($imageIds, $includeImageData, $includeThumbData)
  {
    if ( ! is_array($imageIds)) {
      $imageIds = array($imageIds);
    }
    $array = array('request'=>array(
      'imageIds'=>array_values($imageIds),
      'includeImageData'=>$includeImageData,
      'includeThumbData'=>$includeThumbData,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertGetImagesRequest()

  //----------------------------------------------------------------------------

  public function convertGetEmployeesRequest($employeeNumbers, $syncMarker, $syncMarkerLimit)
  {
    if ( ! is_array($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    $array = array('request'=>array(
      'employeeNumbers'=>empty($employeeNumbers)?null:array_values($employeeNumbers),
      ));
    if ( ! is_null($syncMarker)) {
      $array['request']['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['request']['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetEmployeesRequest()

  //----------------------------------------------------------------------------

  public function convertGetShiftsRequest($fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers)
  {
    if ( ! isset($fromFinancialDate) or is_null($fromFinancialDate) or empty($fromFinancialDate)) {
      $fromFinancialDate = time();
    }
    $fromFinancialDate = $this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    if ( ! isset($throughFinancialDate) or is_null($throughFinancialDate) or empty($throughFinancialDate)) {
      $throughFinancialDate = time();
    }
    $throughFinancialDate = $this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    $object = arrayToObject(array('request'=>array(
      'fromFinancialDate'=>$fromFinancialDate,
      'throughFinancialDate'=>$throughFinancialDate,
      'branchNumbers'=>empty($branchNumbers)?null:array_values($branchNumbers),
      'employeeNumbers'=>empty($employeeNumbers)?null:array_values($employeeNumbers),
      )));
    return $object;
  } // END convertGetShiftsRequest()

  //----------------------------------------------------------------------------

  public function convertGetReceiptsRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $includeOrderReferences, $activityId)
  {
    $fromFinancialDate = is_null($fromFinancialDate)?null:$this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate)?null:$this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }
    
    $request = array();
    if ( ! is_null($syncMarker)) {
      $request['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $request['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $request['fromFinancialDate'] = $fromFinancialDate;
    $request['throughFinancialDate'] = $throughFinancialDate;
    $request['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $request['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $request['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $request['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $request['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $request['articlePluNumbers'] = empty($articlePluNumbers)?null:$this->convertPluNumbers(array_values($articlePluNumbers));
    $request['articleBarcodes'] = empty($articleBarcodes)?null:$this->convertBarcodes(array_values($articleBarcodes));

    if ( ! is_null($supplierRelationNumbers)) {
      if ( ! is_array($supplierRelationNumbers)) {
        $supplierRelationNumbers = array($supplierRelationNumbers);
      }
      $request['supplierRelationNumbers'] = array_values($supplierRelationNumbers);
    }

    if (!is_null($activityId)) {
      $request['activityId'] = $activityId;
    }

    if (!is_null($includeOrderReferences)) {
      if (is_bool($includeOrderReferences)) {
        $request['includeOrderReferences'] = $includeOrderReferences;
      } else {
        throw new MplusQAPIException('Supplied value for `includeOrderReferences` is not a valid boolean.');
      }
    }

    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetReceiptsRequest()

  //----------------------------------------------------------------------------

  public function convertGetReceiptsByCashCountRequest($cashCountId) {
    $object = arrayToObject(array('request'=>array('cashCountId'=>$cashCountId)));
    return $object;
  } // END convertGetReceiptsByCashCountRequest()

  //----------------------------------------------------------------------------

  public function convertGetTicketCounterSalesRequest($syncMarker, $syncMarkerLimit)
  {
    $request = array();
    if ( ! is_null($syncMarker)) {
      $request['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $request['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetTicketCounterSalesRequest()

  //----------------------------------------------------------------------------

  public function convertGetConfigurationRequest($branchNumber, $workplaceNumber, $group, $subgroup, $key)
  {
    $request = array();
    if ( ! is_null($branchNumber)) {
      $request['branchNumber'] = (int)$branchNumber;
    }
    if ( ! is_null($workplaceNumber)) {
      $request['workplaceNumber'] = (int)$workplaceNumber;
    }
    if ( ! is_null($group)) {
      $request['group'] = $group;
    }
    if ( ! is_null($subgroup)) {
      $request['subgroup'] = $subgroup;
    }
    if ( ! is_null($key)) {
      $request['key'] = $key;
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetConfigurationRequest()

  //----------------------------------------------------------------------------

  public function convertUpdateConfigurationRequest($configuration)
  {
    $request = array();
    $request['configuration'] = $configuration;
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertUpdateConfigurationRequest()

  //----------------------------------------------------------------------------

  public function convertGetProposalsRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers)
  {
    $fromFinancialDate = is_null($fromFinancialDate)?null:$this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate)?null:$this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }

    $request = array();
    if ( ! is_null($syncMarker)) {
      $request['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $request['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $request['fromFinancialDate'] = $fromFinancialDate;
    $request['throughFinancialDate'] = $throughFinancialDate;
    $request['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $request['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $request['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $request['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $request['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $request['articlePluNumbers'] = empty($articlePluNumbers)?null:$this->convertPluNumbers(array_values($articlePluNumbers));
    $request['articleBarcodes'] = empty($articleBarcodes)?null:$this->convertBarcodes(array_values($articleBarcodes));

    if ( ! is_null($supplierRelationNumbers)) {
      if ( ! is_array($supplierRelationNumbers)) {
        $supplierRelationNumbers = array($supplierRelationNumbers);
      }
      $request['supplierRelationNumbers'] = array_values($supplierRelationNumbers);
    }

    if (!is_null($activityId)) {
      $request['activityId'] = $activityId;
    }
    
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetProposalsRequest()

  //----------------------------------------------------------------------------

  public function convertGetInvoicesRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $supplierRelationNumbers, $finalizeInvoices, $activityId)
  {
    $fromFinancialDate = is_null($fromFinancialDate)?null:$this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate)?null:$this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }

    $request = array();
    if ( ! is_null($syncMarker)) {
      $request['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $request['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $request['fromFinancialDate'] = $fromFinancialDate;
    $request['throughFinancialDate'] = $throughFinancialDate;
    $request['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $request['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $request['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $request['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $request['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $request['articlePluNumbers'] = empty($articlePluNumbers)?null:$this->convertPluNumbers(array_values($articlePluNumbers));
    $request['articleBarcodes'] = empty($articleBarcodes)?null:$this->convertBarcodes(array_values($articleBarcodes));

    if ( ! is_null($supplierRelationNumbers)) {
      if ( ! is_array($supplierRelationNumbers)) {
        $supplierRelationNumbers = array($supplierRelationNumbers);
      }
      $request['supplierRelationNumbers'] = array_values($supplierRelationNumbers);
    }

    if (!is_null($activityId)) {
      $request['activityId'] = $activityId;
    }

    if ( ! is_null($finalizeInvoices) and is_bool($finalizeInvoices)) {
      $request['finalizeInvoices'] = $finalizeInvoices;
    }
    
    $object = arrayToObject(array('request'=>$request));
    // print_r($object);exit;
    return $object;
  } // END convertGetInvoicesRequest()

  //----------------------------------------------------------------------------

  public function convertGetPackingSlipsRequest($syncMarker, $syncMarkerLimit, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $supplierRelationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $activityId)
  {
    $fromFinancialDate = is_null($fromFinancialDate)?null:$this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate)?null:$this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($supplierRelationNumbers) and ! is_null($supplierRelationNumbers)) {
      $supplierRelationNumbers = array($supplierRelationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }

    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    if ( ! is_null($fromFinancialDate)) {
      $array['fromFinancialDate'] = $fromFinancialDate;
    }
    if ( ! is_null($throughFinancialDate)) {
      $array['throughFinancialDate'] = $throughFinancialDate;
    }
    $array['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $array['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $array['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $array['supplierRelationNumbers'] = empty($supplierRelationNumbers)?null:array_values($supplierRelationNumbers);
    $array['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $array['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $array['articlePluNumbers'] = empty($articlePluNumbers)?null:array_values($articlePluNumbers);
    $array['articleBarcodes'] = empty($articleBarcodes)?null:array_values($articleBarcodes);
    if (!is_null($activityId)) {
      $array['activityId'] = $activityId;
    }
    $object = arrayToObject(array('request'=>$array));
    return $object;
  } // END convertGetPackingSlipsRequest()

  //----------------------------------------------------------------------------

  public function convertGetOrdersRequest($syncMarker, $fromFinancialDate, $throughFinancialDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit, $orderTypeList=null, $activityId=null)
  {
    $fromFinancialDate = is_null($fromFinancialDate)?null:$this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate)?null:$this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }

    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    if ( ! is_null($fromFinancialDate)) {
      $array['fromFinancialDate'] = $fromFinancialDate;
    }
    if ( ! is_null($throughFinancialDate)) {
      $array['throughFinancialDate'] = $throughFinancialDate;
    }
    $array['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $array['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $array['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $array['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $array['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $array['articlePluNumbers'] = empty($articlePluNumbers)?null:array_values($articlePluNumbers);
    $array['articleBarcodes'] = empty($articleBarcodes)?null:array_values($articleBarcodes);
    if ( ! is_null($orderTypeList)) {
      if ( ! is_array($orderTypeList) and ! is_null($orderTypeList)) {
        $orderTypeList = array($orderTypeList);
      }
      if ( ! array_key_exists('orderType', $orderTypeList)) {
        $orderTypeList = array('orderType'=>$orderTypeList);
      }
      $array['orderTypeList'] = $orderTypeList;
    }
    if (!is_null($activityId)) {
      $array['activityId'] = $activityId;
    }
    $object = arrayToObject(array('request'=>$array));
    return $object;
  } // END convertGetOrdersRequest()

  //----------------------------------------------------------------------------

  public function convertGetInterbranchOrdersRequest($syncMarker, $syncMarkerLimit)
  {
    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject(array('request'=>$array));
    return $object;
  } // END convertGetInterbranchOrdersRequest()

  //----------------------------------------------------------------------------

  public function convertGetInterbranchShipmentsRequest($syncMarker, $syncMarkerLimit)
  {
    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertGetInterbranchDeliveriesRequest($syncMarker, $syncMarkerLimit)
  {
    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertCreateInterbranchOrderRequest($orderRequest, $branchNumber, $workplaceNumber)
  {
    $array = array(
      'interbranchOrderRequest'=>$orderRequest,
      'branchNumber'=>(int)$branchNumber,
      'workplaceNumber'=>(int)$workplaceNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertCreateInterbranchShipmentRequest($shipmentRequest, $branchNumber, $workplaceNumber)
  {
    $array = array(
        'interbranchShipmentRequest'=>$shipmentRequest,
        'branchNumber'=>(int)$branchNumber,
        'workplaceNumber'=>(int)$workplaceNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertCreateInterbranchDeliveryRequest($deliveryRequest, $branchNumber, $workplaceNumber)
  {
    $array = array(
        'interbranchDeliveryRequest'=>$deliveryRequest,
        'branchNumber'=>(int)$branchNumber,
        'workplaceNumber'=>(int)$workplaceNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertClaimInterbranchOrderRequest($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber)
  {
    $array = array(
        'interbranchOrderNumber'=>$interbranchOrderNumber,
        'branchNumber'=>(int)$branchNumber,
        'workplaceNumber'=>(int)$workplaceNumber,
        'employeeNumber'=>(int)$employeeNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertReleaseInterbranchOrderRequest($interbranchOrderNumber)
  {
    $array = array(
        'interbranchOrderNumber'=>$interbranchOrderNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertShipInterbranchOrderRequest($interbranchOrderNumber, $branchNumber, $workplaceNumber, $employeeNumber)
  {
    $array = array(
        'interbranchOrderNumber'=>$interbranchOrderNumber,
        'branchNumber'=>(int)$branchNumber,
        'workplaceNumber'=>(int)$workplaceNumber,
        'employeeNumber'=>(int)$employeeNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertDeliverInterbranchShipmentRequest($interbranchShipmentNumber, $branchNumber, $workplaceNumber)
  {
    $array = array(
        'interbranchShipmentNumber'=>$interbranchShipmentNumber,
        'branchNumber'=>(int)$branchNumber,
        'workplaceNumber'=>(int)$workplaceNumber,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertGetPackingSlipsByOrderRequest($orderId)
  {
    $array = array(
      'orderId'=>$orderId,
    );
    $object = arrayToObject(array('request'=>$array));
    return $object;
  } // END convertGetPackingSlipsByOrderRequest()

  //----------------------------------------------------------------------------

  public function convertGetPurchaseOrdersRequest($syncMarker, $fromOrderDate, $throughOrderDate, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit)
  {
    $fromOrderDate = is_null($fromOrderDate)?null:$this->convertMplusDate($fromOrderDate, 'fromOrderDate');
    $throughOrderDate = is_null($throughOrderDate)?null:$this->convertMplusDate($throughOrderDate, 'throughOrderDate');
    $fromDeliveryDate = is_null($fromDeliveryDate)?null:$this->convertMplusDate($fromDeliveryDate, 'fromDeliveryDate');
    $throughDeliveryDate = is_null($throughDeliveryDate)?null:$this->convertMplusDate($throughDeliveryDate, 'throughDeliveryDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }

    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    if ( ! is_null($fromOrderDate)) {
      $array['fromOrderDate'] = $fromOrderDate;
    }
    if ( ! is_null($throughOrderDate)) {
      $array['throughOrderDate'] = $throughOrderDate;
    }
    if ( ! is_null($fromDeliveryDate)) {
      $array['fromDeliveryDate'] = $fromDeliveryDate;
    }
    if ( ! is_null($throughDeliveryDate)) {
      $array['throughDeliveryDate'] = $throughDeliveryDate;
    }
    $array['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $array['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $array['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $array['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $array['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $array['articlePluNumbers'] = empty($articlePluNumbers)?null:array_values($articlePluNumbers);
    $array['articleBarcodes'] = empty($articleBarcodes)?null:array_values($articleBarcodes);
    $object = arrayToObject(array('request'=>$array));
    return $object;
  } // END convertGetPurchaseOrdersRequest()

  //----------------------------------------------------------------------------

  public function convertGetPurchaseDeliveriesRequest($syncMarker, $fromDeliveryDate, $throughDeliveryDate, $branchNumbers, $employeeNumbers, $relationNumbers, $articleNumbers, $articleTurnoverGroups, $articlePluNumbers, $articleBarcodes, $syncMarkerLimit)
  {
    $fromDeliveryDate = is_null($fromDeliveryDate)?null:$this->convertMplusDate($fromDeliveryDate, 'fromDeliveryDate');
    $throughDeliveryDate = is_null($throughDeliveryDate)?null:$this->convertMplusDate($throughDeliveryDate, 'throughDeliveryDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers) and ! is_null($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    if ( ! is_array($relationNumbers) and ! is_null($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    if ( ! is_array($articleNumbers) and ! is_null($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($articleTurnoverGroups) and ! is_null($articleTurnoverGroups)) {
      $articleTurnoverGroups = array($articleTurnoverGroups);
    }
    if ( ! is_array($articlePluNumbers) and ! is_null($articlePluNumbers)) {
      $articlePluNumbers = array($articlePluNumbers);
    }
    if ( ! is_array($articleBarcodes) and ! is_null($articleBarcodes)) {
      $articleBarcodes = array($articleBarcodes);
    }

    $array = array();
    if ( ! is_null($syncMarker)) {
      $array['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    if ( ! is_null($fromDeliveryDate)) {
      $array['fromDeliveryDate'] = $fromDeliveryDate;
    }
    if ( ! is_null($throughDeliveryDate)) {
      $array['throughDeliveryDate'] = $throughDeliveryDate;
    }
    $array['branchNumbers'] = empty($branchNumbers)?null:array_values($branchNumbers);
    $array['employeeNumbers'] = empty($employeeNumbers)?null:array_values($employeeNumbers);
    $array['relationNumbers'] = empty($relationNumbers)?null:array_values($relationNumbers);
    $array['articleNumbers'] = empty($articleNumbers)?null:array_values($articleNumbers);
    $array['articleTurnoverGroups'] = empty($articleTurnoverGroups)?null:array_values($articleTurnoverGroups);
    $array['articlePluNumbers'] = empty($articlePluNumbers)?null:array_values($articlePluNumbers);
    $array['articleBarcodes'] = empty($articleBarcodes)?null:array_values($articleBarcodes);
    $object = arrayToObject(array('request'=>$array));
    return $object;
  } // END convertGetPurchaseDeliveriesRequest()

  //----------------------------------------------------------------------------

  public function convertGetJournalsRequest($fromFinancialDate, $throughFinancialDate, $branchNumbers, $journalFilterList, $reference)
  {
    $fromFinancialDate = $this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = $this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    if ( ! is_array($branchNumbers) and ! is_null($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($journalFilterList) and ! is_null($journalFilterList)) {
      $journalFilterList = array($journalFilterList);
    }
    if ( ! array_key_exists('journalFilter', $journalFilterList)) {
      $journalFilterList = array('journalFilter'=>$journalFilterList);
    }
    $request = array(
      'fromFinancialDate'=>$fromFinancialDate,
      'throughFinancialDate'=>$throughFinancialDate,
      'journalFilterList'=>empty($journalFilterList)?null:$journalFilterList,
      );
    if ( ! is_null($branchNumbers) and is_array($branchNumbers) and ! empty($branchNumbers)) {
      $request['branchNumbers'] = array_values($branchNumbers);
    }
    if ( ! is_null($reference) and ! empty($reference)) {
      $request['reference'] = substr($reference, 0, 50);
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetJournalsRequest()

  //----------------------------------------------------------------------------

  public function convertGetFinancialJournalRequest($fromFinancialDate, $throughFinancialDate, $reference=null)
  {
    $fromFinancialDate = $this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = $this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    $request = array(
      'fromFinancialDate'=>$fromFinancialDate,
      'throughFinancialDate'=>$throughFinancialDate,
      );
    if ( ! is_null($reference) and ! empty($reference)) {
      $request['reference'] = $reference;
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetFinancialJournalRequest()

  //----------------------------------------------------------------------------

  public function convertGetFinancialJournalByCashCountRequest($cashCountId, $reference=null)
  {
    $request = array('cashCountId'=>$cashCountId);
    if ( ! is_null($reference) and ! empty($reference)) {
      $request['reference'] = $reference;
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetFinancialJournalByCashCountRequest()

  //----------------------------------------------------------------------------

  public function convertGetCashCountListRequest($fromFinancialDate, $throughFinancialDate, $sinceCashCount)
  {
    $fromFinancialDate = is_null($fromFinancialDate) ? null : $this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate) ? null : $this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    $sinceCashCount = is_null($sinceCashCount) ? null : $this->convertWorkplaceYearNumber($sinceCashCount);
    $object = arrayToObject(array('request'=>array(
      'fromFinancialDate'=>$fromFinancialDate,
      'throughFinancialDate'=>$throughFinancialDate,
      'sinceCashCount'=>$sinceCashCount,
      )));
    return $object;
  } // END convertGetCashCountListRequest()

  //----------------------------------------------------------------------------

  public function convertGetCashDrawerBalancingListRequest($fromFinancialDate, $throughFinancialDate, $syncMarker, $syncMarkerLimit)
  {
    $syncMarker = is_null($syncMarker) ? null : (int)$syncMarker;
    $syncMarkerLimit = is_null($syncMarkerLimit) ? null : (int)$syncMarkerLimit;
    $fromFinancialDate = is_null($fromFinancialDate) ? null : $this->convertMplusDate($fromFinancialDate, 'fromFinancialDate');
    $throughFinancialDate = is_null($throughFinancialDate) ? null : $this->convertMplusDate($throughFinancialDate, 'throughFinancialDate');
    $object = arrayToObject(array('request'=>array(
      'fromFinancialDate'=>$fromFinancialDate,
      'throughFinancialDate'=>$throughFinancialDate,
      'syncMarker'=>$syncMarker,
      'syncMarkerLimit'=>$syncMarkerLimit,
      )));
    return $object;
  } // END convertGetCashDrawerBalancingListRequest()

  //----------------------------------------------------------------------------

  public function convertUpdateTurnoverGroupsRequest($turnoverGroups)
  {
    $array = array('request'=>array(
      'turnoverGroupList'=>array(
        'turnoverGroup'=>$turnoverGroups,
        ),
      ));
    $object = arraytoObject($array);
    return $object;
  } // END convertUpdateTurnoverGroupsRequest()

  //----------------------------------------------------------------------------

  public function convertPayInvoiceRequest($invoiceId, $paymentList)
  {
    $array = array('request'=>array(
      'invoiceId'=>$invoiceId,
      'paymentList'=>$this->convertPaymentList($paymentList),
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertPayInvoiceRequest()

  //----------------------------------------------------------------------------

  public function convertPayOrderRequest($orderId, $prepay, $paymentList)
  {
    $array = array('request'=>array(
      'orderId'=>$orderId,
      'prepay'=>$prepay,
      'paymentList'=>$this->convertPaymentList($paymentList),
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertPayOrderRequest()

  //----------------------------------------------------------------------------

  public function convertPayTableOrderRequest($terminal, $order, $paymentList, $keepTableName, $releaseTable)
  {
    $order = $this->convertOrder($order, $terminal);
    $terminal = $this->convertTerminal($terminal);
    $array = array(
      'terminal'=>$terminal->terminal,
      'request'=>array(
        'order'=>$order->order,
        'paymentList'=>$this->convertPaymentList($paymentList),
        'keepTableName'=>$keepTableName,
        'releaseTable'=>$releaseTable,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertPayTableOrderRequest()

  //----------------------------------------------------------------------------

  public function convertPrepayTableOrderRequest($terminal, $order, $paymentList, $prepayAmount, $releaseTable)
  {
    $order = $this->convertOrder($order, $terminal);
    $terminal = $this->convertTerminal($terminal);
    $array = array(
      'terminal'=>$terminal->terminal,
      'request'=>array(
        'order'=>$order->order,
        'paymentList'=>$this->convertPaymentList($paymentList),
        'prepayAmount'=>$prepayAmount,
        'releaseTable'=>$releaseTable,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertPrepayTableOrderRequest()

  //----------------------------------------------------------------------------

  public function convertDeliverOrderRequest($orderId)
  {
    $array = array('request'=>array(
      'orderId'=>$orderId,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertDeliverOrderRequest()

  //----------------------------------------------------------------------------

  public function convertDeliverOrderV2Request($orderDelivery)
  {
    $array = array('request'=>array(
      'orderDelivery'=>$this->convertOrderDelivery($orderDelivery, true),
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertDeliverOrderV2Request()

  //----------------------------------------------------------------------------
  public function convertQueueBranchOrderPaymentOrderRequest($orderId, $paymentList)
  {
    $array = array('paymentRequest'=>array(
      'orderId'=>$orderId,
      'paymentList'=>$this->convertPaymentList($paymentList),
      ));
    $object = arrayToObject($array);
    return $object;
  }

  //----------------------------------------------------------------------------
  public function convertPaymentList($paymentList)
  {
    if ( ! isset($paymentList['payment'])) {
      $paymentList = array('payment' => $paymentList);
    }
    foreach ($paymentList['payment'] as $idx => $payment) {
      $paymentList['payment'][$idx] = $payment;
    }
    return $paymentList;
  } // END convertPaymentList()

  //----------------------------------------------------------------------------

  public function convertPluNumbers($pluNumbers)
  {
    $text = array();
    foreach (array_values($pluNumbers) as $pluNumber) {
      $text[] = array(
        'text' => $pluNumber,
        );
    }
    if (count($text) > 0) {
      $object = arrayToObject(array('text'=>$text));
    }
    else {
      $object = arrayToObject(array());
    }
    return $object;
  } // END convertPluNumbers()

  //----------------------------------------------------------------------------

  public function convertBarcodes($barcodes)
  {
    $text = array();
    foreach (array_values($barcodes) as $barcode) {
      $text[] = array(
        'text' => $barcode,
        );
    }
    if (count($text) > 0) {
      $object = arrayToObject(array('text'=>$text));
    }
    else {
      $object = arrayToObject(array());
    }
    return $object;
  } // END convertBarcodes()

  //----------------------------------------------------------------------------

  public function convertGetArticleGroupsRequest($groupNumbers, $syncMarker, $syncMarkerLimit)
  {
    $request = array();
    if ( ! is_array($groupNumbers)) {
      $groupNumbers = array($groupNumbers);
    }
    $request['groupNumbers'] = array_values($groupNumbers);
    if ( ! is_null($syncMarker)) {
      $request['syncMarker'] = $syncMarker;
    }
    if ( ! is_null($syncMarkerLimit)) {
      $request['syncMarkerLimit'] = $syncMarkerLimit;
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetArticleGroupsRequest()

  //----------------------------------------------------------------------------

  public function convertGetArticleGroupChangesRequest($groupNumbers, $syncMarker, $syncMarkerLimit)
  {
    $request = array();
    if ( ! is_null($groupNumbers)) {
      if ( ! is_array($groupNumbers)) {
        $groupNumbers = array($groupNumbers);
      }
      $request['groupNumbers'] = array_values($groupNumbers);
    } 
    if ( ! is_null($syncMarker)) {
      $request['syncMarker'] = $syncMarker;
    }
    if ( ! is_null($syncMarkerLimit)) {
      $request['syncMarkerLimit'] = $syncMarkerLimit;
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertGetArticleGroupChangesRequest()

  //----------------------------------------------------------------------------

  public function convertSaveArticleGroupsRequest($articleGroupList, $depth=0)
  {
    foreach ($articleGroupList as $idx => $articleGroup) {
      if ( ! array_key_exists('groupNumber', $articleGroup)) {
        $articleGroup['groupNumber'] = 0;
      }
      if ( ! array_key_exists('text', $articleGroup)) {
        $articleGroup['text'] = '';
      }
      if ( ! array_key_exists('subGroupList', $articleGroup)) {
        $articleGroup['subGroupList'] = array();
      }
      if ( ! array_key_exists('articleGroups', $articleGroup['subGroupList']) and ! empty($articleGroup['subGroupList'])) {
        $articleGroup['subGroupList'] = array('articleGroups'=>$articleGroup['subGroupList']);
      }      
      if (array_key_exists('articleGroups', $articleGroup['subGroupList'])) {
        $articleGroup['subGroupList']['articleGroups'] = $this->convertSaveArticleGroupsRequest($articleGroup['subGroupList']['articleGroups'], $depth+1);
      }
      $articleGroupList[$idx] = $articleGroup;
    }
    if ($depth > 0) {
      return $articleGroupList;
    } else {
      $object = arrayToObject(array('request'=>array('articleGroupList'=>array('articleGroups'=>$articleGroupList))));
      return $object;
    }
  } // END convertSaveArticleGroupsRequest()

  //----------------------------------------------------------------------------

  public function convertGetStockRequest($branchNumber, $articleNumbers, $stockId)
  {
    if ( ! is_array($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    $array = array('request'=>array(
      'branchNumber'=>$branchNumber,
      'articleNumbers'=>array('articleNumbers'=>$articleNumbers)));
    if ( ! empty($stockId)) {
      $array['request']['stockId'] = $stockId;
    }
    $object = arrayToObject($array);
    if (empty($articleNumbers)) {
      $object->request->articleNumbers = new stdClass();
      $object->request->articleNumbers->articleNumbers = array();
    }
    return $object;
  } // END convertGetStockRequest()

  //----------------------------------------------------------------------------

  public function convertGetStockHistoryRequest($branchNumber, $articleNumbers, $sinceStockId, $fromFinancialDateTime, $throughFinancialDateTime)
  {
    if ( ! is_array($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    $array = array('request'=>array(
      'branchNumber'=>$branchNumber,
      'articleNumbers'=>array('articleNumbers'=>$articleNumbers)));
    if ( ! is_null($sinceStockId) and ! empty($sinceStockId)) {
      $array['request']['sinceStockId'] = $sinceStockId;
    }
    if ( ! is_null($fromFinancialDateTime) and ! empty($fromFinancialDateTime)) {
      $fromFinancialDateTime = $this->convertMplusDateTime($fromFinancialDateTime, 'fromFinancialDateTime');
      $array['request']['fromFinancialDateTime'] = $fromFinancialDateTime;
    }
    if ( ! is_null($throughFinancialDateTime) and ! empty($throughFinancialDateTime)) {
      $throughFinancialDateTime = $this->convertMplusDateTime($throughFinancialDateTime, 'throughFinancialDateTime');
      $array['request']['throughFinancialDateTime'] = $throughFinancialDateTime;
    }
    $object = arrayToObject($array);
    if (empty($articleNumbers)) {
      $object->request->articleNumbers = new stdClass();
      $object->request->articleNumbers->articleNumbers = array();
    }
    return $object;
  } // END convertGetStockHistoryRequest()
  
  //----------------------------------------------------------------------------

  public function convertGetExchangeRateHistoryRequest($sinceHistoryId)
  {
    $array = array('request'=>array());
    if ( ! is_null($sinceHistoryId) and ! empty($sinceHistoryId)) {
      $array['request']['sinceHistoryId'] = $sinceHistoryId;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetExchangeRateHistoryRequest()

  //----------------------------------------------------------------------------

  public function convertUpdateExchangeRateRequest($exchangeRates)
  {
    $array = array('request'=>array('exchangeRateList'=>array('exchangeRate'=>array())));
    foreach ($exchangeRates as $exchangeRate) {
      if (isset($exchangeRate['buyPrice']) and !isset($exchangeRate['buyPriceDecimalPlaces'])) {
        list($exchangeRate['buyPrice'], $exchangeRate['buyPriceDecimalPlaces']) = get_quantity_and_decimal_places($exchangeRate['buyPrice']);
      }
      if (isset($exchangeRate['sellPrice']) and !isset($exchangeRate['sellPriceDecimalPlaces'])) {
        list($exchangeRate['sellPrice'], $exchangeRate['sellPriceDecimalPlaces']) = get_quantity_and_decimal_places($exchangeRate['sellPrice']);
      }
      $array['request']['exchangeRateList']['exchangeRate'][] = $exchangeRate;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertUpdateExchangeRateRequest()

  //----------------------------------------------------------------------------

  public function convertUpdateStockRequest($branchNumber, $articleNumber, $amountChanged)
  {
    list($amountChanged, $decimalPlaces) = get_quantity_and_decimal_places($amountChanged);
    $array = array('request'=>array(
      'branchNumber'=>$branchNumber,
      'articleNumber'=>$articleNumber,
      'amountChanged'=>$amountChanged,
      'decimalPlaces'=>$decimalPlaces,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertUpdateStockRequest()

  //----------------------------------------------------------------------------

  public function convertSetStockRequest($branchNumber, $articleNumber, $amount)
  {
    list($amount, $decimalPlaces) = get_quantity_and_decimal_places($amount);
    $array = array('request'=>array(
      'branchNumber'=>$branchNumber,
      'articleNumber'=>$articleNumber,
      'amount'=>$amount,
      'decimalPlaces'=>$decimalPlaces,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertSetStockRequest()

  //----------------------------------------------------------------------------

  public function convertSendMessageRequest($branchNumber, $terminalNumber, $text, $sender, $messageType, $forceBranchTerminalNumber=false)
  {
    $request = array('text'=>$text);
    if ($forceBranchTerminalNumber or ( ! is_null($branchNumber) and ! empty($branchNumber))) {
      $request['branchNumber'] = (int)$branchNumber;
    }
    if ($forceBranchTerminalNumber or ( ! is_null($branchNumber) and ! empty($branchNumber) and ! is_null($terminalNumber) and ! empty($terminalNumber))) {
      $request['terminalNumber'] = (int)$terminalNumber;
    }
    if ( ! is_null($sender) and ! empty($sender)) {
      $request['sender'] = $sender;
    }
    if ( ! is_null($messageType) and ! empty($messageType)) {
      $request['messageType'] = $this->convertMessageType($messageType);
    }
    $object = arrayToObject(array('request'=>$request));
    return $object;
  } // END convertSendMessageRequest()

  //----------------------------------------------------------------------------

  public function convertEncryptStringRequest($plainString, $encryptionKey)
  {
    $object = arrayToObject(array('request'=>array(
      'plainString'=>$plainString,
      'encryptionKey'=>$encryptionKey)));
    return $object;
  } // END convertEncryptStringRequest()

  //----------------------------------------------------------------------------

  public function convertMessageType($messageType)
  {
    $messageType = strtoupper($messageType);
    if (in_array($messageType, array('MESSAGE-TYPE-WARNING', 'WARNING'))) {
      return 'MESSAGE-TYPE-WARNING';
    } elseif (in_array($messageType, array('MESSAGE-TYPE-OK', 'OK'))) {
      return 'MESSAGE-TYPE-OK';
    } else {
      return 'MESSAGE-TYPE-INFO';
    }
  } // END convertMessageType()

  //----------------------------------------------------------------------------

  public function convertExtOrderId($extOrderId) {
    return arrayToObject(array('extOrderId'=>$extOrderId));
  } // END convertExtOrderId()

  //----------------------------------------------------------------------------

  public function convertOrderId($orderId) {
    $object = arrayToObject(array('orderId'=>$orderId));
    return $object;
  } // END convertOrderId()

  //----------------------------------------------------------------------------

  public function convertProposalId($proposalId) {
    $object = arrayToObject(array('proposalId'=>$proposalId));
    return $object;
  } // END convertProposalId()

  //----------------------------------------------------------------------------

  public function convertExtInvoiceId($extInvoiceId) {
    return arrayToObject(array('extInvoiceId'=>$extInvoiceId));
  } // END convertExtInvoiceId()

  //----------------------------------------------------------------------------

  public function convertInvoiceId($invoiceId) {
    $object = arrayToObject(array('invoiceId'=>$invoiceId));
    return $object;
  } // END convertInvoiceId()

  //----------------------------------------------------------------------------

  public function convertRelation($relation) {
    if (isset($relation['customFieldList']) and ! empty($relation['customFieldList'])) {
      if ( ! isset($relation['customFieldList']['customField'])) {
        $relation['customFieldList'] = array('customField' => $relation['customFieldList']);
        foreach ($relation['customFieldList']['customField'] as $cf_idx => $customField) {
          if ( ! isset($customField['dataType'])) {
            $relation['customFieldList']['customField'][$cf_idx]['dataType'] = 'DATA-TYPE-UNKNOWN';
          }
          if (isset($customField['dateValue'])) {
            $relation['customFieldList']['customField'][$cf_idx]['dateValue'] = $this->convertMplusDate($customField['dateValue'], 'dateValue');
          }
          if (isset($customField['dateTimeValue'])) {
            $relation['customFieldList']['customField'][$cf_idx]['dateTimeValue'] = $this->convertMplusDateTime($customField['dateTimeValue'], 'dateTimeValue');
          }
        }
      }
      if (isset($relation['customFieldList']['customField'])) {
        // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
        // Andere indexing levert problemen op met PHP SOAP client
        $relation['customFieldList']['customField'] = array_values($relation['customFieldList']['customField']);
      }
    }
    $object = arrayToObject(array('relation'=>$relation));
    return $object;
  } // END convertRelation()

  //----------------------------------------------------------------------------

  public function convertEmployee($employee) {
    if (isset($employee['customFieldList']) and ! empty($employee['customFieldList'])) {
      if ( ! isset($employee['customFieldList']['customField'])) {
        $employee['customFieldList'] = array('customField' => $employee['customFieldList']);
        foreach ($employee['customFieldList']['customField'] as $cf_idx => $customField) {
          if ( ! isset($customField['dataType'])) {
            $employee['customFieldList']['customField'][$cf_idx]['dataType'] = 'DATA-TYPE-UNKNOWN';
          }
          if (isset($customField['dateValue'])) {
            $employee['customFieldList']['customField'][$cf_idx]['dateValue'] = $this->convertMplusDate($customField['dateValue'], 'dateValue');
          }
          if (isset($customField['dateTimeValue'])) {
            $employee['customFieldList']['customField'][$cf_idx]['dateTimeValue'] = $this->convertMplusDateTime($customField['dateTimeValue'], 'dateTimeValue');
          }
        }
      }
      if (isset($employee['customFieldList']['customField'])) {
        // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
        // Andere indexing levert problemen op met PHP SOAP client
        $employee['customFieldList']['customField'] = array_values($employee['customFieldList']['customField']);
      }
    }
    $object = arrayToObject(array('employee'=>$employee));
    return $object;
  } // END convertEmployee()

  //----------------------------------------------------------------------------

  public function convertGeneric($name, $value) {
    $array = array($name=>$value);
    $object = arrayToObject($array, null, true);
    return $object;
  } // END convertGeneric()

  //----------------------------------------------------------------------------

  public function convertProduct($product, $add_default_fields=false)
  {
    if ($add_default_fields) {
      if ( ! isset($product['extraText'])) {
        $product['extraText'] = '';
      }
      if ( ! isset($product['sortOrderGroupList'])) {
        $product['sortOrderGroupList'] = array();
      }
    }
    if (isset($product['articleList']) and ! isset($product['articleList']['article'])) {
      $product['articleList'] = array('article' => $product['articleList']);
    }
    if (isset($product['articleList']['article'])) {
      foreach ($product['articleList']['article'] as $idx => $article) {
        if ($add_default_fields) {
          if ( ! isset($article['colour'])) {
            $article['colour'] = '';
          }
          if ( ! isset($article['receiptText'])) {
            $article['receiptText'] = '';
          }
          if ( ! isset($article['displayText'])) {
            $article['displayText'] = '';
          }
          if ( ! isset($article['priceExcl'])) {
            $article['priceExcl'] = 0;
          }
          if ( ! isset($article['webshop'])) {
            $article['webshop'] = false;
          }
          if ( ! isset($article['pluNumber'])) {
            $article['pluNumber'] = false;
          }
        }
        if (array_key_exists('turnoverGroup', $article)) {
          if ($article['turnoverGroup'] < 0 or $article['turnoverGroup'] > 999) {
            throw new MplusQAPIException(sprintf('Supplied value for field `turnoverGroup` (%d) is out of bounds (0-999).',
              $article['turnoverGroup']));
          }
        }
        if (isset($article['customFieldList']) and ! empty($article['customFieldList'])) {
          if ( ! isset($article['customFieldList']['customField'])) {
            $article['customFieldList'] = array('customField' => $article['customFieldList']);
          }
          foreach ($article['customFieldList']['customField'] as $cf_idx => $customField) {
            if ( ! isset($customField['dataType'])) {
              $article['customFieldList']['customField'][$cf_idx]['dataType'] = 'DATA-TYPE-UNKNOWN';
            }
            if (isset($customField['dateValue'])) {
              $article['customFieldList']['customField'][$cf_idx]['dateValue'] = $this->convertMplusDate($customField['dateValue'], 'dateValue');
            }
            if (isset($customField['dateTimeValue'])) {
              $article['customFieldList']['customField'][$cf_idx]['dateTimeValue'] = $this->convertMplusDateTime($customField['dateTimeValue'], 'dateTimeValue');
            }
          }
        }
        if (isset($article['customFieldList']['customField'])) {
          // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
          // Andere indexing levert problemen op met PHP SOAP client
          $article['customFieldList']['customField'] = array_values($article['customFieldList']['customField']);
        }
        if (isset($article['imageList']) and ! isset($article['imageList']['image']) and ! empty($article['imageList'])) {
          $article['imageList'] = array('image' => $article['imageList']);
        }
        if (isset($article['imageList']['image'])) {
          // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
          // Andere indexing levert problemen op met PHP SOAP client
          $article['imageList']['image'] = array_values($article['imageList']['image']);
        }
        if (isset($article['allergenList']) and ! empty($article['allergenList'])) {
          if ( ! isset($article['allergenList']['allergen'])) {
            $article['allergenList'] = array('allergen' => $article['allergenList']);
          }
          foreach ($article['allergenList']['allergen'] as $al_idx => $allergen) {
            if ( ! isset($allergen['description'])) {
              $article['allergenList']['allergen'][$al_idx]['description'] = '';
            }
          }
        }
        if (isset($article['allergenList']['allergen'])) {
          // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
          // Andere indexing levert problemen op met PHP SOAP client
          $article['allergenList']['allergen'] = array_values($article['allergenList']['allergen']);
        }
        if (isset($article['barcodeList']) and ! empty($article['barcodeList'])) {
          if ( ! isset($article['barcodeList']['barcode'])) {
            $article['barcodeList'] = array('barcode' => $article['barcodeList']);
          }
          foreach ($article['barcodeList']['barcode'] as $bc_idx => $barcode) {
            if (isset($barcode['barcodeDate'])) {
              $article['barcodeList']['barcode'][$bc_idx]['barcodeDate'] = $this->convertMplusDate($barcode[ 'barcodeDate'], 'barcodeDate');
            }
          }
        }
        if (isset($article['barcodeList']['barcode'])) {
          // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
          // Andere indexing levert problemen op met PHP SOAP client
          $article['barcodeList']['barcode'] = array_values($article['barcodeList']['barcode']);
        }
        if (isset($article['exchangeRateBuyPrice']) and ! isset($article['exchangeRateBuyPriceDecimalPlaces'])) {
          list($article['exchangeRateBuyPrice'], $article['exchangeRateBuyPriceDecimalPlaces']) = get_quantity_and_decimal_places($article['exchangeRateBuyPrice']);
        }
        if (isset($article['exchangeRateSellPrice']) and ! isset($article['exchangeRateSellPriceDecimalPlaces'])) {
          list($article['exchangeRateSellPrice'], $article['exchangeRateSellPriceDecimalPlaces']) = get_quantity_and_decimal_places($article['exchangeRateSellPrice']);
        }
        $product['articleList']['article'][$idx] = $article;
      }
      // Hier even een array_values() om zeker te weten dat de array 0, 1, 2 geïndexeerd is
      // Andere indexing levert problemen op met PHP SOAP client
      $product['articleList']['article'] = array_values($product['articleList']['article']);
    }
    if (array_key_exists('sortOrderGroupList', $product) and ! array_key_exists('sortOrderGroup', $product['sortOrderGroupList']) and ! empty($product['sortOrderGroupList'])) {
      $product['sortOrderGroupList'] = array('sortOrderGroup' => $product['sortOrderGroupList']);
    }
    // $object = arrayToObject(array('product'=>$product));
    $object = json_decode(json_encode(array('product'=>$product)));
    if (function_exists('debug_realtime')) {
      debug_realtime(json_encode($object));
    }
    return $object;
  } // END convertProduct()

  //----------------------------------------------------------------------------

  public function convertTerminal($terminal)
  {
    if ( ! isset($terminal['branchNumber'])) {
      $terminal['branchNumber'] = 0;
    }
    if ( ! isset($terminal['branchName'])) {
      $terminal['branchName'] = '';
    }
    if ( ! isset($terminal['terminalNumber'])) {
      $terminal['terminalNumber'] = 0;
    }
    if ( ! isset($terminal['terminalName'])) {
      $terminal['terminalName'] = '';
    }
    if ( ! isset($terminal['terminalStatus'])) {
      $terminal['terminalStatus'] = 'TERMINAL-STATUS-AVAILABLE';
    } 
    if ( ! isset($terminal['uniqueDeviceIdentifier'])) {
      $terminal['uniqueDeviceIdentifier'] = md5($_SERVER['REMOTE_ADDR']);
    }
    if ( ! array_key_exists('terminalSoftwareName', $terminal) or empty($terminal['terminalSoftwareName'])) {
      $terminal['terminalSoftwareName'] = 'Mplus PHP API Client';
    }
    if ( ! array_key_exists('terminalSoftwareVersion', $terminal) or empty($terminal['terminalSoftwareVersion'])) {
      $terminal['terminalSoftwareVersion'] = MplusQAPIclient::CLIENT_VERSION;
    }
    $object = arrayToObject(array('terminal'=>$terminal));
    return $object;
  } // END convertTerminal()

  //----------------------------------------------------------------------------

  public function convertGetCurrentTableOrdersRequest($request)
  {
    if (is_null($request)) {
      $request = [];
    }
    $object = arrayToObject(['request'=>$request]);
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertCreateOrderV2Request($order, $applySalesAndActions, $applySalesPrices, $applyPriceGroups)
  {
    $order = $this->convertOrder($order);
    $request = ['order'=>$order->order];
    if (!is_null($applySalesAndActions)) {
      $request['applySalesAndActions'] = $applySalesAndActions;
    }
    if (!is_null($applySalesPrices)) {
      $request['applySalesPrices'] = $applySalesPrices;
    }
    if (!is_null($applyPriceGroups)) {
      $request['applyPriceGroups'] = $applyPriceGroups;
    }
    $object = arrayToObject(['request'=>$request]);
    return $object;
  }

  //----------------------------------------------------------------------------

  public function convertOrder($order, $terminal=null, $as_array=false)
  {
    if ( ! isset($order['orderId']) or is_null($order['orderId'])) {
      $order['orderId'] = '';
    }
    if ( ! isset($order['extOrderId']) or is_null($order['extOrderId'])) {
      $order['extOrderId'] = '';
    }
    if ( ! isset($order['entryBranchNumber'])) {
      if (isset($order['financialBranchNumber'])) {
        $order['entryBranchNumber'] = $order['financialBranchNumber'];
      } elseif (!is_null($terminal) and isset($terminal['branchNumber'])) {
        $order['entryBranchNumber'] = $terminal['branchNumber'];
      } else {
        $order['entryBranchNumber'] = 0;
      }
    }
    if ( ! isset($order['employeeNumber'])) {
      $order['employeeNumber'] = 0;
    }
    if ( ! isset($order['entryTimestamp'])) {
      $order['entryTimestamp'] = time();
    }
    $order['entryTimestamp'] = $this->convertMplusDateTime($order['entryTimestamp'], 'entryTimestamp');
    if ( ! isset($order['relationNumber'])) {
      $order['relationNumber'] = 0;
    }
    if ( ! isset($order['financialDate'])) {
      $order['financialDate'] = time();
    }
    $order['financialDate'] = $this->convertMplusDate($order['financialDate'], 'financialDate');
    if (array_key_exists('deliveryDate', $order)) {
      $order['deliveryDate'] = $this->convertMplusDate($order['deliveryDate'], 'deliveryDate');
    }
    if (array_key_exists('deliveryPeriodBegin', $order)) {
      $order['deliveryPeriodBegin'] = $this->convertMplusDateTime($order['deliveryPeriodBegin'], 'deliveryPeriodBegin');
    }
    if (array_key_exists('deliveryPeriodEnd', $order)) {
      $order['deliveryPeriodEnd'] = $this->convertMplusDateTime($order['deliveryPeriodEnd'], 'deliveryPeriodEnd');
    }
    if ( ! isset($order['financialBranchNumber'])) {
      if (isset($order['entryBranchNumber'])) {
        $order['financialBranchNumber'] = $order['entryBranchNumber'];
      } elseif (!is_null($terminal) and isset($terminal['branchNumber'])) {
        $order['financialBranchNumber'] = $terminal['branchNumber'];
      } else {
        $order['financialBranchNumber'] = 0;
      }
    }
    if ( ! isset($order['reference'])) {
      $order['reference'] = '';
    }
    if ( ! isset($order['totalInclAmount'])) {
      $order['totalInclAmount'] = 0;
    }
    if ( ! isset($order['totalExclAmount'])) {
      $order['totalExclAmount'] = 0;
    }
    if ( ! isset($order['vatMethod'])) {
      $order['vatMethod'] = 'VAT-METHOD-INCLUSIVE';
    }
    if ( ! isset($order['changeCounter'])) {
      $order['changeCounter'] = 0;
    }
    if ( ! isset($order['versionNumber'])) {
      $order['versionNumber'] = 0;
    }
    if ( ! isset($order['prepaidAmount'])) {
      $order['prepaidAmount'] = 0;
    }
    if ( ! isset($order['fullyPaid'])) {
      $order['fullyPaid'] = false;
    }
    if ( ! isset($order['deliveryState'])) {
      $order['deliveryState'] = 'ORDER-DELIVERY-STATE-NOTHING';
    }
    if ( ! isset($order['cancelState'])) {
      $order['cancelState'] = 'ORDER-CANCEL-STATE-NOTHING';
    }
    if ( ! isset($order['completeState'])) {
      $order['completeState'] = 'ORDER-COMPLETE-STATE-NOTHING';
    }
    if ( ! isset($order['orderNumber'])) {
      $order['orderNumber'] = array(
        'year'=>0,
        'number'=>0,
        );
    }
    $order['orderNumber'] = $this->convertYearNumber($order['orderNumber']);
    if ( ! isset($order['lineList'])) {
      $order['lineList'] = array();
    }
    $order['lineList'] = $this->convertLineList($order['lineList']);
    if ( ! isset($order['invoiceIds'])) {
      $order['invoiceIds'] = array();
    }
    if ($as_array) {
      return $order;
    } else {
      $object = arrayToObject(array('order'=>$order));
      return $object;
    }
  } // END convertOrder()

  //----------------------------------------------------------------------------

  public function convertOrderDelivery($orderDelivery, $as_array=false)
  {
    if ( ! isset($orderDelivery['orderId']) or is_null($orderDelivery['orderId'])) {
      $orderDelivery['orderId'] = '';
    }
    $orderDelivery['lineList'] = $this->convertOrderDeliveryLineList($orderDelivery['lineList']);
    if ($as_array) {
      return $orderDelivery;
    } else {
      $object = arrayToObject(array('orderDelivery'=>$orderDelivery));
      return $object;
    }
  } // END convertOrderDelivery()

  //----------------------------------------------------------------------------

  public function convertInvoice($invoice)
  {
    if ( ! isset($invoice['invoiceId'])) {
      $invoice['invoiceId'] = '';
    }
    if ( ! isset($invoice['extInvoiceId'])) {
      $invoice['extInvoiceId'] = '';
    }
    if ( ! isset($invoice['entryBranchNumber'])) {
      if (isset($invoice['financialBranchNumber'])) {
        $invoice['entryBranchNumber'] = $invoice['financialBranchNumber'];
      }/* else {
        $invoice['entryBranchNumber'] = 0;
      }*/
    }
    /*if ( ! isset($invoice['employeeNumber'])) {
      $invoice['employeeNumber'] = 0;
    }
    if ( ! isset($invoice['entryTimestamp'])) {
      $invoice['entryTimestamp'] = time();
    }*/
    if (isset($invoice['entryTimestamp'])) {
      $invoice['entryTimestamp'] = $this->convertMplusDateTime($invoice['entryTimestamp'], 'entryTimestamp');
    }
    /*if ( ! isset($invoice['relationNumber'])) {
      $invoice['relationNumber'] = 0;
    }*/
    /*if ( ! isset($invoice['financialDate'])) {
      $invoice['financialDate'] = time();
    }*/
    if (isset($invoice['financialDate'])) {
      $invoice['financialDate'] = $this->convertMplusDate($invoice['financialDate'], 'financialDate');
    }
    if ( ! isset($invoice['financialBranchNumber'])) {
      if (isset($invoice['entryBranchNumber'])) {
        $invoice['financialBranchNumber'] = $invoice['entryBranchNumber'];
      }/* else {
        $invoice['financialBranchNumber'] = 0;
      }*/
    }
    /*if ( ! isset($invoice['reference'])) {
      $invoice['reference'] = '';
    }
    if ( ! isset($invoice['totalInclAmount'])) {
      $invoice['totalInclAmount'] = 0;
    }
    if ( ! isset($invoice['totalExclAmount'])) {
      $invoice['totalExclAmount'] = 0;
    }
    if ( ! isset($invoice['vatMethod'])) {
      $invoice['vatMethod'] = 'VAT-METHOD-INCLUSIVE';
    }
    if ( ! isset($invoice['changeCounter'])) {
      $invoice['changeCounter'] = 0;
    }
    if ( ! isset($invoice['versionNumber'])) {
      $invoice['versionNumber'] = 0;
    }
    if ( ! isset($invoice['paidAmount'])) {
      $invoice['paidAmount'] = 0;
    }
    if ( ! isset($invoice['state'])) {
      $invoice['state'] = 'INVOICE-STATE-OUTSTANDING';
    }
    if ( ! isset($invoice['invoiceNumber'])) {
      $invoice['invoiceNumber'] = array(
        'year'=>0,
        'number'=>0,
        );
    }*/
    if (isset($invoice['invoiceNumber'])) {
      $invoice['invoiceNumber'] = $this->convertYearNumber($invoice['invoiceNumber']);
    }
    if ( ! isset($invoice['lineList'])) {
      $invoice['lineList'] = array();
    }
    $invoice['lineList'] = $this->convertLineList($invoice['lineList']);
    $object = arrayToObject(array('invoice'=>$invoice));
    // i($object);
    return $object;
  } // END convertInvoice()

  //----------------------------------------------------------------------------

  public function convertProposal($proposal)
  {
    if ( ! isset($proposal['proposalId'])) {
      $proposal['proposalId'] = '';
    }
    if ( ! isset($proposal['extProposalId'])) {
      $proposal['extProposalId'] = '';
    }
    if ( ! isset($proposal['entryBranchNumber'])) {
      if (isset($proposal['financialBranchNumber'])) {
        $proposal['entryBranchNumber'] = $proposal['financialBranchNumber'];
      }
    }
    if (isset($proposal['entryTimestamp'])) {
      $proposal['entryTimestamp'] = $this->convertMplusDateTime($proposal['entryTimestamp'], 'entryTimestamp');
    }
    if (isset($proposal['financialDate'])) {
      $proposal['financialDate'] = $this->convertMplusDate($proposal['financialDate'], 'financialDate');
    }
    if ( ! isset($proposal['financialBranchNumber'])) {
      if (isset($proposal['entryBranchNumber'])) {
        $proposal['financialBranchNumber'] = $proposal['entryBranchNumber'];
      }
    }
    if (isset($proposal['proposalNumber'])) {
      $proposal['proposalNumber'] = $this->convertYearNumber($proposal['proposalNumber']);
    }
    if ( ! isset($proposal['lineList'])) {
      $proposal['lineList'] = array();
    }
    $proposal['lineList'] = $this->convertLineList($proposal['lineList']);
    $object = arrayToObject(array('proposal'=>$proposal));  
    return $object;
  } // END convertProposal()

  //----------------------------------------------------------------------------

  public function convertPurchaseOrder($purchaseOrder)
  {
    if (isset($purchaseOrder['orderDate'])) {
      $purchaseOrder['orderDate'] = $this->convertMplusDate($purchaseOrder['orderDate'], 'orderDate');
    }
    if (isset($purchaseOrder['deliveryDate'])) {
      $purchaseOrder['deliveryDate'] = $this->convertMplusDate($purchaseOrder['deliveryDate'], 'deliveryDate');
    }
    if (isset($purchaseOrder['entryTimestamp'])) {
      $purchaseOrder['entryTimestamp'] = $this->convertMplusDateTime($purchaseOrder['entryTimestamp'], 'entryTimestamp');
    }
    if (isset($purchaseOrder['purchaseOrderNumber'])) {
      $purchaseOrder['purchaseOrderNumber'] = $this->convertYearNumber($purchaseOrder['purchaseOrderNumber']);
    }
    if ( ! isset($purchaseOrder['lineList'])) {
      $purchaseOrder['lineList'] = array();
    }
    $purchaseOrder['lineList'] = $this->convertPurchaseOrderLineList($purchaseOrder['lineList']);
    $object = arrayToObject(array('purchaseOrder'=>$purchaseOrder));
    return $object;
  } // END convertPurchaseOrder()

  //----------------------------------------------------------------------------

  public function convertPurchaseOrderV2($purchaseOrder)
  {
    if (isset($purchaseOrder['orderDate'])) {
      $purchaseOrder['orderDate'] = $this->convertMplusDate($purchaseOrder['orderDate'], 'orderDate');
    }
    if (isset($purchaseOrder['deliveryDate'])) {
      $purchaseOrder['deliveryDate'] = $this->convertMplusDate($purchaseOrder['deliveryDate'], 'deliveryDate');
    }
    if (isset($purchaseOrder['entryTimestamp'])) {
      $purchaseOrder['entryTimestamp'] = $this->convertMplusDateTime($purchaseOrder['entryTimestamp'], 'entryTimestamp');
    }
    if (isset($purchaseOrder['purchaseOrderNumber'])) {
      $purchaseOrder['purchaseOrderNumber'] = $this->convertYearNumber($purchaseOrder['purchaseOrderNumber']);
    }
    if ( ! isset($purchaseOrder['lineList'])) {
      $purchaseOrder['lineList'] = array();
    }
    $purchaseOrder['lineList'] = $this->convertPurchaseOrderLineList($purchaseOrder['lineList']);
    $object = arrayToObject(array('request'=>array('savePurchaseOrder'=>$purchaseOrder)));
    return $object;
  } // convertPurchaseOrderV2()

  //----------------------------------------------------------------------------

  public function convertPurchaseDelivery($purchaseDelivery)
  {
    if (isset($invoice['deliveryDate'])) {
      $invoice['deliveryDate'] = $this->convertMplusDate($invoice['deliveryDate'], 'deliveryDate');
    }
    if (isset($purchaseDelivery['entryTimestamp'])) {
      $purchaseDelivery['entryTimestamp'] = $this->convertMplusDateTime($purchaseDelivery['entryTimestamp'], 'entryTimestamp');
    }
    if (isset($purchaseDelivery['purchaseDeliveryNumber'])) {
      $purchaseDelivery['purchaseDeliveryNumber'] = $this->convertYearNumber($purchaseDelivery['purchaseDeliveryNumber']);
    }
    if ( ! isset($purchaseDelivery['lineList'])) {
      $purchaseDelivery['lineList'] = array();
    }
    $purchaseDelivery['lineList'] = $this->convertPurchaseDeliveryLineList($purchaseDelivery['lineList']);
    $object = arrayToObject(array('purchaseDelivery'=>$purchaseDelivery));
    return $object;
  } // END convertPurchaseDelivery()

  //----------------------------------------------------------------------------

  public function convertPurchaseDeliveryV2($purchaseDelivery)
  {
    if (isset($invoice['deliveryDate'])) {
      $invoice['deliveryDate'] = $this->convertMplusDate($invoice['deliveryDate'], 'deliveryDate');
    }
    if (isset($purchaseDelivery['entryTimestamp'])) {
      $purchaseDelivery['entryTimestamp'] = $this->convertMplusDateTime($purchaseDelivery['entryTimestamp'], 'entryTimestamp');
    }
    if (isset($purchaseDelivery['purchaseDeliveryNumber'])) {
      $purchaseDelivery['purchaseDeliveryNumber'] = $this->convertYearNumber($purchaseDelivery['purchaseDeliveryNumber']);
    }
    if ( ! isset($purchaseDelivery['lineList'])) {
      $purchaseDelivery['lineList'] = array();
    }
    $purchaseDelivery['lineList'] = $this->convertPurchaseDeliveryLineList($purchaseDelivery['lineList']);
    $object = arrayToObject(array('request'=>array('savePurchaseDelivery'=>$purchaseDelivery)));
    return $object;
  } // END convertPurchaseDeliveryV2()

  //----------------------------------------------------------------------------

  public function convertLineList($lineList, $is_preparationList=false)
  {
    if ( ! isset($lineList['line']) and ! empty($lineList)) {
      $lineList = array('line'=>$lineList);
    }
    if (isset($lineList['line'])) {
      foreach ($lineList['line'] as $idx => $line) {
        /*if ( ! isset($line['lineId'])) {
          $line['lineId'] = '';
        }
        if ( ! isset($line['employeeNumber'])) {
          $line['employeeNumber'] = 0;
        }*/
        if ( ! isset($line['articleNumber'])) {
          $line['articleNumber'] = 0;
        }
        /*if ( ! isset($line['pluNumber'])) {
          $line['pluNumber'] = '';
        }
        if ( ! isset($line['text'])) {
          $line['text'] = '';
        }*/
        if (isset($line['data'])) {
          if ( ! isset($line['data']['quantity'])) {
            $line['data']['quantity'] = 1;
          }
          /*if ( ! isset($line['data']['decimalPlaces'])) {
            $line['data']['decimalPlaces'] = 0;
          }*/
          if (array_key_exists('turnoverGroup', $line['data'])) {
            if ($line['data']['turnoverGroup'] < 0 or $line['data']['turnoverGroup'] > 999) {
              throw new MplusQAPIException(sprintf('Supplied value for field `turnoverGroup` (%d) is out of bounds (0-999).',
                $line['data']['turnoverGroup']));
          }
          }
          /*if ( ! isset($line['data']['vatCode'])) {
            $line['data']['vatCode'] = 0;
          }
          if ( ! isset($line['data']['vatPercentage'])) {
            $line['data']['vatPercentage'] = 0;
          }
          if ( ! isset($line['data']['pricePerQuantity'])) {
            $line['data']['pricePerQuantity'] = 0;
          }
          if ( ! isset($line['data']['siUnit'])) {
            $line['data']['siUnit'] = '';
          }
          if ( ! isset($line['data']['discountPercentage'])) {
            $line['data']['discountPercentage'] = 0;
          }
          if ( ! isset($line['data']['discountAmount'])) {
            $line['data']['discountAmount'] = 0;
          }*/
        }
        /*if ( ! isset($line['courseNumber'])) {
          $line['courseNumber'] = 0;
        }
        if ( ! isset($line['lineType'])) {
          $line['lineType'] = 'LINE-TYPE-NONE';
        }
        if ( ! isset($line['preparationList'])) {
          $line['preparationList'] = array();
        }*/
        if ( ! $is_preparationList and isset($line['preparationList'])) {
          $line['preparationList'] = $this->convertLineList($line['preparationList'], true);
        }
        $lineList['line'][$idx] = $line;
      }
    }
    $object = arrayToObject($lineList);
    return $object;
  } // END convertLineList()

  //----------------------------------------------------------------------------

  public function convertOrderDeliveryLineList($orderDeliverylineList)
  {
    if ( ! isset($orderDeliverylineList['line']) and ! empty($orderDeliverylineList)) {
      $orderDeliverylineList = array('line'=>$orderDeliverylineList);
    }
    $object = arrayToObject($orderDeliverylineList);
    return $object;
  } // END convertOrderDeliveryLineList()

  //----------------------------------------------------------------------------

  public function convertPurchaseOrderLineList($lineList)
  {
    if ( ! isset($lineList['line']) and ! empty($lineList)) {
      $lineList = array('line'=>$lineList);
    }
    if (isset($lineList['line'])) {
      foreach ($lineList['line'] as $idx => $line) {
        if (isset($line['data'])) {
          if ( ! isset($line['data']['quantity'])) {
            $line['data']['quantity'] = 1;
          }
        }
        $lineList['line'][$idx] = $line;
      }
    }
    $object = arrayToObject($lineList);
    return $object;
  } // END convertPurchaseOrderLineList()

  //----------------------------------------------------------------------------

  public function convertPurchaseDeliveryLineList($lineList)
  {
    if ( ! isset($lineList['line']) and ! empty($lineList)) {
      $lineList = array('line'=>$lineList);
    }
    if (isset($lineList['line'])) {
      foreach ($lineList['line'] as $idx => $line) {
        if (isset($line['data'])) {
          if ( ! isset($line['data']['deliveredQuantity'])) {
            $line['data']['deliveredQuantity'] = 1;
          }
        }
        $lineList['line'][$idx] = $line;
      }
    }
    $object = arrayToObject($lineList);
    return $object;
  } // END convertPurchaseDeliveryLineList()

  //----------------------------------------------------------------------------

  public function convertMplusDate($timestamp, $field_name)
  {
    // Convert DateTime object to timestamp
    if ($timestamp instanceof \DateTime) {
      $timestamp = $timestamp->getTimestamp();
    }
    
    if (is_array($timestamp)) {
      // Probably already properly converted
      return $timestamp;
    } else if (is_object($timestamp)) {
      // Probably already properly converted, except not an array
      return objectToArray($timestamp);
    } else if (is_numeric($timestamp)) {
      return array(
        'day' => date('j', (int)$timestamp),
        'mon' => date('n', (int)$timestamp),
        'year' => date('Y', (int)$timestamp),
        );
    } else {
      // Probably invalid value
      throw new MplusQAPIException(sprintf('Supplied date value for field `%s` can not be properly converted.',
        $field_name));
    }
  } // END convertMplusDate()

  //----------------------------------------------------------------------------

  public function convertMplusDateTime($timestamp, $field_name)
  {
    // Convert DateTime object to timestamp
    if ($timestamp instanceof \DateTime) {
      $timestamp = $timestamp->getTimestamp();
    }
    
    if (is_array($timestamp)) {
      // Probably already properly converted
      return $timestamp;
    } else if (is_object($timestamp)) {
      // Probably already properly converted, except not an array
      return objectToArray($timestamp);
    } else if (is_numeric($timestamp)) {
      return array(
        'day' => date('j', (int)$timestamp),
        'mon' => date('n', (int)$timestamp),
        'year' => date('Y', (int)$timestamp),
        'hour' => date('H', (int)$timestamp),
        'min' => date('i', (int)$timestamp),
        'sec' => date('s', (int)$timestamp),
        'isdst' => false,
        'timezone' => 0,
        );
    } else {
      // Probably invalid value
      throw new MplusQAPIException(sprintf('Supplied datetime value for field `%s` can not be properly converted.',
        $field_name));
    }
  } // END convertMplusDateTime()

  private function fieldExistsInArray($fieldName, $array, $required = true)
  {
      if (!array_key_exists($fieldName, $array) || $array[$fieldName] === null) {
          if ($required) {
              throw new \Exception("Field : $fieldName does not exists in request");
          } else {
              return false;
          }
      }
      return true;
  }

//----------------------------------------------------------------------------

  public function convertReportRequest($method, $arguments)
  {
      $fields = array(
          'reportTurnover' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'turnoverGroups', 'perHour',
              ),
          ),
          'reportTurnoverByBranch' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'perHour',
              ),
          ),
          'reportTurnoverByEmployee' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'employeeNumbers', 'perHour',
              ),
          ),
          'reportTurnoverByActivity' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'activityNumbers',
              ),
          ),
          'reportTurnoverByTurnoverGroup' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'turnoverGroups', 'perHour',
              ),
          ),
          'reportTurnoverByArticle' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'turnoverGroups', 'articleNumbers',
              ),
          ),
          'reportHoursByEmployee' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'employeeNumbers',
              ),
          ),
          'reportPaymentMethods' => array(
              'required' => array(
                  'fromFinancialDate', 'throughFinancialDate',
              ),
              'optional' => array(
                  'branchNumbers', 'perHour',
              ),
          ),
          'reportTables' => array(
              'required' => array(
              ),
              'optional' => array(
                  'branchNumbers',
              ),
          ),
          'reportCancellations' => array(
                'required' => array(
                    'fromFinancialDate', 'throughFinancialDate',
                ),
                'optional' => array(
                    'branchNumbers', 'employeeNumbers',
                ),
          ),
      );
      $request = [];
      if (array_key_exists($method, $fields)) {
          foreach ($fields[$method] as $type => $callFields) {
              foreach ($callFields as $callField) {
                  $required = $type == 'required' ? true : false;
                  if ($this->fieldExistsInArray($callField, $arguments, $required)) {
                      switch ($callField) {
                          case "fromFinancialDate":
                          case "throughFinancialDate":
                              $request['dateFilter'][$callField] = $arguments[$callField];
                              break;
                          case "branchNumbers":
                              if (!is_array($arguments[$callField])) {
                                  $arguments[$callField] = array($arguments[$callField]);
                              }
                              $request['branchFilter'] = $arguments[$callField];
                              break;
                          case "turnoverGroups":
                              if (!is_array($arguments[$callField])) {
                                  $arguments[$callField] = array($arguments[$callField]);
                              }
                              $request['turnoverGroupFilter'] = $callField;
                              break;
                          case "employeeNumbers":
                              if (!is_array($arguments[$callField])) {
                                  $arguments[$callField] = array($arguments[$callField]);
                              }
                              $request['employeeFilter'] = $arguments[$callField];
                              break;
                          case "articleNumbers":
                              if (!is_array($arguments[$callField])) {
                                  $arguments[$callField] = array($arguments[$callField]);
                              }
                              $request['articleFilter'] = $arguments[$callField];
                              break;
                          case "activityNumbers":
                              if (!is_array($arguments[$callField])) {
                                  $arguments[$callField] = array($arguments[$callField]);
                              }
                              $request['activityFilter'] = $arguments[$callField];
                              break;
                          case "perHour":
                              $request['perHour'] = $arguments['perHour'] === true ? true : false;
                              break;
                      }

                  }
              }
          }
      }

      $object = arrayToObject(array('request' => $request));
      return $object;
  } // END convertReportRequest()
  
  public function convertGetSalePromotionsRequest($branchNumbers)
  {
      $array = [ 'request' => [] ];
      
      if($branchNumbers !== null) {
          if(!is_array($branchNumbers)) {
              $branchNumbers = array($branchNumbers);
          }
          $array['request']['branchFilter'] = $branchNumbers;
      }
                              
    $object = arrayToObject($array);
    return $object;
  } // END convertGetSalePromotionsRequest()

  //----------------------------------------------------------------------------

  public function parseMplusDate($mplus_date)
  {
    if ($mplus_date['day'] == 0 || $mplus_date['mon'] == 0 || $mplus_date['year'] == 0) {
      return null;
    } else {
      if ($this->convertToTimestamps) {
        return mktime(0, 0, 0, $mplus_date['mon'], $mplus_date['day'], $mplus_date['year']);
      } else {
        return sprintf('%d-%d-%d', $mplus_date['year'], $mplus_date['mon'], $mplus_date['day']);
      }
    }
  } // END parseMplusDate()

  //----------------------------------------------------------------------------

  public function parseMplusDateTime($mplus_date_time)
  {
    if ($mplus_date_time['day'] == 0 || $mplus_date_time['mon'] == 0 || $mplus_date_time['year'] == 0) {
      return null;
    } else {
      if ($this->convertToTimestamps) {
        return mktime($mplus_date_time['hour'], $mplus_date_time['min'], $mplus_date_time['sec'], $mplus_date_time['mon'], $mplus_date_time['day'], $mplus_date_time['year']);
      } else {
        if ($mplus_date_time['sec'] != 0) {
          return sprintf('%d-%d-%d %s:%s:%s', $mplus_date_time['year'], $mplus_date_time['mon'], $mplus_date_time['day'], str_pad($mplus_date_time['hour'], 2, '0', STR_PAD_LEFT), str_pad($mplus_date_time['min'], 2, '0', STR_PAD_LEFT), str_pad($mplus_date_time['sec'], 2, '0', STR_PAD_LEFT));
        } else {
          return sprintf('%d-%d-%d %s:%s', $mplus_date_time['year'], $mplus_date_time['mon'], $mplus_date_time['day'], str_pad($mplus_date_time['hour'], 2, '0', STR_PAD_LEFT), str_pad($mplus_date_time['min'], 2, '0', STR_PAD_LEFT));
        }
      }
    }
  } // END parseMplusDateTime()

  //----------------------------------------------------------------------------

  public function convertYearNumber($year_number)
  {
    if (is_array($year_number) and count($year_number) >= 2) {
      $year_number = array_values($year_number);
      return array('year'=>(int)$year_number[0],'number'=>(int)$year_number[1]);
    } else {
      $parts = explode('.', $year_number);
      if (count($parts) >= 2) {
        return array('year'=>(int)$parts[0], 'number'=>(int)$parts[1]);
      }
    }
    return $year_number;
  } // END convertYearNumber()

  //----------------------------------------------------------------------------

  public function convertWorkplaceYearNumber($workplace_year_number)
  {
    if (is_array($workplace_year_number) and count($workplace_year_number) >= 4) {
      $workplace_year_number = array_values($workplace_year_number);
      return array(
        'branchNumber'=>(int)$workplace_year_number[0],
        'workplaceNumber'=>(int)$workplace_year_number[1],
        'year'=>(int)$workplace_year_number[2],
        'number'=>(int)$workplace_year_number[3]);
    } else {
      $parts = explode('.', $workplace_year_number);
      if (count($parts) >= 4) {
        return array(
          'branchNumber'=>(int)$parts[0],
          'workplaceNumber'=>(int)$parts[1],
          'year'=>(int)$parts[2],
          'number'=>(int)$parts[3]);
      }
    }
    return $workplace_year_number;
  } // END convertWorkplaceYearNumber()

  //----------------------------------------------------------------------------

  public function convertGetWordAliasesRequest($locale)
  {
    $array = array('request'=>array(
      'locale'=>$locale,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertGetWordAliasesRequest()

  //----------------------------------------------------------------------------

  public function convertGetTableOrderRequest($terminal, $branchNumber, $tableNumber)
  {
    $terminal = $this->convertTerminal($terminal);
    $branchNumber = $this->convertBranchNumber($branchNumber);
    $tableNumber = $this->convertTableNumber($tableNumber);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'branchNumber'=>$branchNumber->branchNumber,
      'tableNumber'=>$tableNumber->tableNumber,
      ));
    return $object;
  } // END convertGetTableOrderRequest()

  //----------------------------------------------------------------------------

  public function convertGetTableOrderV2Request($terminal, $branchNumber, $tableNumber, $claimTable)
  {
    $terminal = $this->convertTerminal($terminal);
    $branchNumber = $this->convertBranchNumber($branchNumber);
    $tableNumber = $this->convertTableNumber($tableNumber);
    $array = array(
      'terminal'=>$terminal->terminal,
      'request'=>array('tableNumber'=>$tableNumber->tableNumber),
      );
    if ( ! is_null($claimTable)) {
      $array['request']['claimTable'] = $claimTable;
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetTableOrderV2Request()

  //----------------------------------------------------------------------------

  public function convertFindTableOrderRequest($terminal, $extOrderId)
  {
    $terminal = $this->convertTerminal($terminal);
    $extOrderId = $this->convertExtOrderId($extOrderId);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'extOrderId'=>$extOrderId->extOrderId,
      ));
    return $object;
  } // END convertFindTableOrderRequest()

  //----------------------------------------------------------------------------

  public function convertGetTableOrderCourseListRequest($terminal, $branchNumber, $tableNumber)
  {
    $terminal = $this->convertTerminal($terminal);
    $branchNumber = $this->convertBranchNumber($branchNumber);
    $tableNumber = $this->convertTableNumber($tableNumber);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'branchNumber'=>$branchNumber->branchNumber,
      'tableNumber'=>$tableNumber->tableNumber,
      ));
    return $object;
  } // END convertGetTableOrderCourseListRequest()

  //----------------------------------------------------------------------------

  public function convertSaveTableOrder($terminal, $order)
  {
    $order = $this->convertOrder($order, $terminal);
    $terminal = $this->convertTerminal($terminal);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'order'=>$order->order,
      ));
    return $object;
  } // END convertSaveTableOrder()

  //----------------------------------------------------------------------------

  public function convertMoveTableOrderRequest($terminal, $order, $tableNumber)
  {
    $order = $this->convertOrder($order, $terminal);
    $terminal = $this->convertTerminal($terminal);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'order'=>$order->order,
      'tableNumber'=>$tableNumber,
      ));
    return $object;
  } // END convertMoveTableOrderRequest()

  //----------------------------------------------------------------------------

  public function convertBranchNumber($branchNumber)
  {
    $object = arrayToObject(array('branchNumber'=>$branchNumber));
    return $object;
  } // END convertBranchNumber()

  //----------------------------------------------------------------------------

  public function convertTableNumber($tableNumber)
  {
    $object = arrayToObject(array('tableNumber'=>$tableNumber));
    return $object;
  } // END convertTableNumber()

  //----------------------------------------------------------------------------

  public function convertForceRegistration($forceRegistration)
  {
    $object = arrayToObject(array('forceRegistration'=>$forceRegistration));
    return $object;
  } // END convertForceRegistration()

  //----------------------------------------------------------------------------

  public function convertAdjustPointsRequest($relationNumber, $pointsAdjustment)
  {
    $array = array('request'=>array(
      'relationNumber'=>$relationNumber,
      'pointsAdjustment'=>$pointsAdjustment,
      ));
    $object = arrayToObject($array);
    return $object;
  } // END convertAdjustPointsRequest()

  //----------------------------------------------------------------------------
  
  public function convertGetRelationPointsRequest($relationNumbers, $syncMarker, $syncMarkerLimit)
  {
    if ( ! is_array($relationNumbers)) {
      $relationNumbers = array($relationNumbers);
    }
    $array = array('request'=>array(
      'relationNumbers'=>empty($relationNumbers) ? null : array_values($relationNumbers),
      ));
    if ( ! is_null($syncMarker)) {
      $array['request']['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['request']['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetRelationPointsRequest()

  //----------------------------------------------------------------------------
  
  public function convertGetActivitiesRequest($syncMarker, $syncMarkerLimit)
  {
    $array = array('request'=>array());
    if ( ! is_null($syncMarker)) {
      $array['request']['syncMarker'] = (int)$syncMarker;
      if ( ! is_null($syncMarkerLimit) and $syncMarkerLimit > 0) {
        $array['request']['syncMarkerLimit'] = (int)$syncMarkerLimit;
      }
    }
    $object = arrayToObject($array);
    return $object;
  } // END convertGetActivitiesRequest()

  //----------------------------------------------------------------------------

  public function convertCreateActivityRequest($createActivity)
  {
    $createActivity['employeeStartTimestamp'] = $this->convertMplusDateTime($createActivity['employeeStartTimestamp'], 'employeeStartTimestamp');
    $createActivity['employeeEndTimestamp'] = $this->convertMplusDateTime($createActivity['employeeEndTimestamp'], 'employeeEndTimestamp');
    $createActivity['managerStartTimestamp'] = $this->convertMplusDateTime($createActivity['managerStartTimestamp'], 'managerStartTimestamp');
    $createActivity['managerEndTimestamp'] = $this->convertMplusDateTime($createActivity['managerEndTimestamp'], 'managerEndTimestamp');    
    $object = arrayToObject(array('request'=>array('createActivity'=>$createActivity)));
    return $object;
  } // END convertCreateActivityRequest()

  //----------------------------------------------------------------------------

  public function convertUpdateActivityRequest($updateActivity)
  {
    if (isset($updateActivity['employeeStartTimestamp'])) {
      $updateActivity['employeeStartTimestamp'] = $this->convertMplusDateTime($updateActivity['employeeStartTimestamp'], 'employeeStartTimestamp');
    }
    if (isset($updateActivity['employeeEndTimestamp'])) {
      $updateActivity['employeeEndTimestamp'] = $this->convertMplusDateTime($updateActivity['employeeEndTimestamp'], 'employeeEndTimestamp');
    }
    if (isset($updateActivity['managerStartTimestamp'])) {
      $updateActivity['managerStartTimestamp'] = $this->convertMplusDateTime($updateActivity['managerStartTimestamp'], 'managerStartTimestamp');
    }
    if (isset($updateActivity['managerEndTimestamp'])) {
      $updateActivity['managerEndTimestamp'] = $this->convertMplusDateTime($updateActivity['managerEndTimestamp'], 'managerEndTimestamp');    
    }
    $object = arrayToObject(array('request'=>array('updateActivity'=>$updateActivity)));
    return $object;
  } // END convertUpdateActivityRequest()

  //----------------------------------------------------------------------------

  public function convertDeleteActivityRequest($activityId)
  {
    $object = arrayToObject(array('request'=>array('activityId'=>$activityId)));
    return $object;
  } // END convertDeleteActivityRequest()

  //----------------------------------------------------------------------------

  public function convertRegisterTerminalRequest($terminal, $forceRegistration)
  {
    $terminal = $this->convertTerminal($terminal);
    $forceRegistration = $this->convertForceRegistration($forceRegistration);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'forceRegistration'=>$forceRegistration->forceRegistration,
      ));
    return $object;
  } // END convertRegisterTerminalRequest()

  //----------------------------------------------------------------------------

  public function convertButtonLayoutRequest($terminal)
  {
    $terminal = $this->convertTerminal($terminal);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      ));
    return $object;
  } // END convertButtonLayoutRequest()

  //----------------------------------------------------------------------------

  public function convertVerifyCredentialsRequest($request)
  {
    $object = arrayToObject(['request'=>$request]);
    return $object;
  }

  //----------------------------------------------------------------------------
}

//------------------------------------------------------------------------------

if ( ! class_exists('MplusQAPIException', false)) {
  class MplusQAPIException extends Exception
  {

  }
}

//----------------------------------------------------------------------------

if ( ! function_exists('get_quantity_and_decimal_places')) {
  function get_quantity_and_decimal_places($input)
  {
    $input = str_replace(',', '.', $input);
    $input = round($input, 6);
    $orig_input = $input;
    $decimalPlaces = -1;
    do {
      $int_part = (int)$input;
      $input -= $int_part;
      $input *= 10;
      $input = round($input, 6);
      $decimalPlaces++;
    } while ($input >= 0.0000001);
    $quantity = (int)($orig_input * pow(10, $decimalPlaces));
    return array($quantity, $decimalPlaces);
  } // END get_quantity_and_decimal_places()
}

//----------------------------------------------------------------------------

if ( ! function_exists('from_quantity_and_decimal_places')) {
  function from_quantity_and_decimal_places($quantity, $decimalPlaces)
  {
    if (is_null($decimalPlaces)) {
      $decimalPlaces = 0;
    }
    if ( ! is_numeric($quantity) and ! is_numeric($decimalPlaces)) {
      return 0;
    }
    $output = (float)$quantity;
    $decimalPlaces = (float)$decimalPlaces;
    $output = $output / pow(10, $decimalPlaces);
    return $output;
  } // END from_quantity_and_decimal_places()
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

$global_leave_as_array = null;
if ( ! function_exists('arrayToObject')) {
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
      if (isset($d['articleNumbers']) or isset($d['groupNumbers']) 
          or isset($d['imageIds']) or isset($d['journalFilter']) 
          or isset($d['turnoverGroup']) or isset($d['customField'])          
          or isset($d['description']) or isset($d['receiptText'])
          or isset($d['invoiceText']) or isset($d['displayText'])
          or isset($d['relationNumbers']) or isset($d['supplierRelationNumbers'])
          or isset($d['articleTurnoverGroups']) or isset($d['branchNumbers'])
          or isset($d['employeeNumbers']) or isset($d['employeeNumbers'])
          or isset($d['allergen']) or isset($d['orderType'])) {
        if ( ! is_null($leave_as_array)) {
          $global_leave_as_array = null;
        }
        if (isset($d['customFieldList'])) {
          $d['customFieldList'] = (object)$d['customFieldList'];
        }
        if (isset($d['allergenList'])) {
          $d['allergenList'] = (object)$d['allergenList'];
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
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['description'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['receiptText'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['purchasePrice'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['priceIncl'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['priceExcl'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['groupNumber'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['amount'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['id'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['lineId'])) {
        return array_map(__FUNCTION__, $d);
      }
      elseif (is_array($d) and isset($d[0]) and is_array($d[0]) and isset($d[0]['group'])) {
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
