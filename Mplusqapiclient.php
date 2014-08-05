<?php

define('TERMINAL_STATUS_AVAILABLE', 1);
define('TERMINAL_STATUS_REGISTERED', 2);
define('TERMINAL_STATUS_UNKNOWN', 3);

class MplusQAPIclient
{
  const CLIENT_VERSION  = '0.3.1';

  var $MIN_API_VERSION_MAJOR = 0;
  var $MIN_API_VERSION_MINOR = 3;
  var $MIN_API_VERSION_REVIS = 0;

  var $MAX_API_VERSION_MAJOR = 0;
  var $MAX_API_VERSION_MINOR = 3;
  var $MAX_API_VERSION_REVIS = 0;

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
      throw new MplusQAPIException('MplusQAPIException needs the CURL PHP extension.');
    }
    if( ! function_exists('json_decode'))
    {
      throw new MplusQAPIException('MplusQAPIException needs the JSON PHP extension.');
    }

    if ( ! is_nulL($params)) {
      $this->setApiServer($params['apiServer']);
      $this->setApiPort($params['apiPort']);
      $this->setApiPath($params['apiPath']);
      $this->setApiFingerprint($params['apiFingerprint']);
      $this->setApiIdent($params['apiIdent']);
      $this->setApiSecret($params['apiSecret']);
      $this->initClient();
    }

    $this->parser = new MplusQAPIDataParser();
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
      'trace' => true,
      'exceptions' => true, 
      'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
      'cache_wsdl' => WSDL_CACHE_NONE,
      );

    if ($require_fingerprint_check and ! $this->checkFingerprint($location)) {
      throw new MplusQAPIException('Fingerprint of SSL certificate doesn\'t match.');
    }

    $this->client = new SoapClient('https://api.mpluskassa.nl/MplusQapi.wsdl', $options);
    $this->checkApiVersion();
    // i($this->client->__getTypes());
    // $this->client = new SoapClient(null, $options);
  } // END initClient()

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
    $g = stream_context_create (array('ssl' => array('capture_peer_cert' => true)));
    if (false === ($r = @stream_socket_client(str_replace('https', 'ssl', $location), $errno,
      $errstr, 30, STREAM_CLIENT_CONNECT, $g))) {
      return $fingerprint_matches;
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
      }
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
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getApiVersion()

  //----------------------------------------------------------------------------

  public function getAvailableTerminalList()
  {
    try {
      $result = $this->client->getAvailableTerminalList();
      return $this->parser->parseTerminalList($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getAvailableTerminalList()

  //----------------------------------------------------------------------------

  public function getVatGroupList()
  {
    try {
      $result = $this->client->getVatGroupList();
      return $this->parser->parseVatGroupList($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getVatGroupList()

  //----------------------------------------------------------------------------

  public function getProducts($articleNumbers = array(), $groupNumbers = array(), $pluNumbers = array())
  {
    try {
      $result = $this->client->getProducts($this->parser->convertGetProductsRequest($articleNumbers, $groupNumbers, $pluNumbers));
      return $this->parser->parseProducts($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getProducts()

  //----------------------------------------------------------------------------

  public function getEmployees($employeeNumbers = array())
  {
    try {
      $result = $this->client->getEmployees($this->parser->convertGetEmployeesRequest($employeeNumbers));
      return $this->parser->parseEmployees($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getEmployees()

  //----------------------------------------------------------------------------

  public function createProduct($product)
  {
    try {
      $result = $this->client->createProduct($this->parser->convertProduct($product));
      return $this->parser->parseCreateProductResult($result);

    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END createProduct()

  //----------------------------------------------------------------------------

  public function updateProduct($product)
  {
    try {
      $result = $this->client->updateProduct($this->parser->convertProduct($product));
      return $this->parser->parseUpdateProductResult($result);

    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END updateProduct()

  //----------------------------------------------------------------------------

  public function getArticleGroups($groupNumbers = array())
  {
    try {
      $result = $this->client->getArticleGroups($this->parser->convertGetArticleGroupsRequest($groupNumbers));
      return $this->parser->parseArticleGroups($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getArticleGroups()

  //----------------------------------------------------------------------------

  public function getStock($branchNumber, $articleNumbers = array())
  {
    try {
      $result = $this->client->getStock($this->parser->convertGetStockRequest($branchNumber, $articleNumbers));
      return $this->parser->parseStock($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getStock()

  //----------------------------------------------------------------------------

  public function getShifts($financialDate, $branchNumbers = array(), $employeeNumbers = array())
  {
    try {
      $result = $this->client->getShifts($this->parser->convertGetShiftsRequest($financialDate, $branchNumbers, $employeeNumbers));
      return $this->parser->parseShifts($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getShifts()

  //----------------------------------------------------------------------------

  public function findOrder($extOrderId)
  {
    try {
      $result = $this->client->findOrder($this->parser->convertExtOrderId($extOrderId));
      // i($result);
      return $this->parser->parseOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END findOrder()

  //----------------------------------------------------------------------------

  public function getOrder($orderId)
  {
    try {
      $result = $this->client->getOrder($this->parser->convertOrderId($orderId));
      return $this->parser->parseOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getOrder()

  //----------------------------------------------------------------------------

  public function getReceiptsByOrder($orderId)
  {
    try {
      $result = $this->client->getReceiptsByOrder($this->parser->convertOrderId($orderId));
      return $this->parser->parseReceiptsByOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getReceiptsByOrder()

  //----------------------------------------------------------------------------

  public function createOrder($order)
  {
    try {
      $result = $this->client->createOrder($this->parser->convertOrder($order));
      return $this->parser->parseCreateOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END createOrder()

  //----------------------------------------------------------------------------

  public function updateOrder($order)
  {
    try {
      if ( ! isset($order['orderId'])) {
        throw new MplusQAPIException("No orderId set.");
      }
      $result = $this->client->updateOrder($this->parser->convertOrder($order));
      return $this->parser->parseUpdateOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END updateOrder()

  //----------------------------------------------------------------------------

  public function cancelOrder($orderId)
  {
    try {
      $result = $this->client->cancelOrder($this->parser->convertOrderId($orderId));
      return $this->parser->parseCancelOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END cancelOrder()

  //----------------------------------------------------------------------------

  public function findRelation($relation)
  {
    try {
      $result = $this->client->findRelation($this->parser->convertRelation($relation));
      return $this->parser->parseFindRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END findRelation()

  public function createRelation($relation)
  {
    try {
      $result = $this->client->createRelation($this->parser->convertRelation($relation));
      return $this->parser->parseCreateRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END createRelation()

  public function updateRelation($relation)
  {
    try {
      $result = $this->client->updateRelation($this->parser->convertRelation($relation));
      return $this->parser->parseUpdateRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END updateRelation()

  public function getRelation($relationNumber)
  {
    try {
      $result = $this->client->getRelation($this->parser->convertGeneric('relationNumber', $relationNumber));
      return $this->parser->parseGetRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getRelation()

  //----------------------------------------------------------------------------

  public function registerTerminal($terminal, $forceRegistration)
  {
    try {
      $result = $this->client->registerTerminal($this->parser->convertRegisterTerminalRequest($terminal, $forceRegistration));
      return $this->parser->parseRegisterTerminalResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END registerTerminal()

  //----------------------------------------------------------------------------

  public function getTableOrder($terminal, $branchNumber, $tableNumber)
  {
    try {
      $result = $this->client->getTableOrder($this->parser->convertGetTableOrderRequest($terminal, $branchNumber, $tableNumber));
      return $this->parser->parseGetTableOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getTableOrder()

  //----------------------------------------------------------------------------

  public function findTableOrder($terminal, $extOrderId)
  {
    try {
      $result = $this->client->findTableOrder($this->parser->convertFindTableOrderRequest($terminal, $extOrderId));
      return $this->parser->parseGetTableOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END findTableOrder()

  //----------------------------------------------------------------------------

  public function saveTableOrder($terminal, $order)
  {
    try {
      $result = $this->client->saveTableOrder($this->parser->convertSaveTableOrder($terminal, $order));
      return $this->parser->parseSaveTableOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getTableOrder()

  //----------------------------------------------------------------------------

  public function cancelTableOrder($terminal, $branchNumber, $tableNumber)
  {
    try {
      $result = $this->client->cancelTableOrder($this->parser->convertGetTableOrderRequest($terminal, $branchNumber, $tableNumber));
      return $this->parser->parseCancelOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END cancelTableOrder()

  //----------------------------------------------------------------------------

  public function sendMessage($branchNumber, $terminalNumber, $text)
  {
    try {
      $result = $this->client->sendMessage($this->parser->convertSendMessageRequest($branchNumber, $terminalNumber, $text));
      return $this->parser->parseSendMessageResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END sendMessage()

}

//==============================================================================

class MplusQAPIDataParser
{

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

  public function parseTerminalList($soapTerminalList) {
    if (isset($soapTerminalList->return)) {
      $soapTerminalList = $soapTerminalList->return;
    }
    $terminals = array();
    foreach ($soapTerminalList as $soapTerminal) {
      $terminal = objectToArray($soapTerminal);
      switch ($terminal['terminalStatus']) {
        case 'TERMINAL-STATUS-AVAILABLE':
          $terminal['terminalStatus'] = TERMINAL_STATUS_AVAILABLE;
          break;
        case 'TERMINAL-STATUS-REGISTERED':
          $terminal['terminalStatus'] = TERMINAL_STATUS_REGISTERED;
          break;
        default:
          $terminal['terminalStatus'] = TERMINAL_STATUS_UNKNOWN;
          break;
      }
      $terminals[] = $terminal;
    }
    return $terminals;
  } // END parseTerminalList()

  //----------------------------------------------------------------------------

  public function parseVatGroupList($soapVatGroupList) {
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

  public function parseProducts($soapProducts) {
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
        if (isset($product['groupNumbers'])) {
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
        if (isset($product['articleList']['article']) and ! isset($product['articleList']['article']['articleNumber'])) {
          $product['articleList'] = $product['articleList']['article'];
        }
        if (isset($product['articleList'])) {
          foreach ($product['articleList'] as $idx => $article) {
            $orig_article = $article;
            if ( ! isset($article['imageList'])) {
              $article['imageList'] = array();
            }
            if (isset($article['imageList']['image']) and ! isset($article['imageList']['image']['imageName'])) {
              $article['imageList'] = $article['imageList']['image'];
            }
            if ( ! is_array($article['imageList'])) {
              if ( ! empty($article['imageList'])) {
                $article['imageList'] = array($article['imageList']);
              } else {
                $article['imageList'] = array();
              }
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
  } // END parseProductsemployees

  //----------------------------------------------------------------------------

  public function parseEmployees($soapEmployees) 
  {
    if (isset($soapEmployees->employeeList)) {
      $soapEmployees = $soapEmployees->employeeList;
      $employees = array();
      if (isset($soapEmployees->employee)) {
        $soapEmployees = $soapEmployees->employee;
        $employees = objectToArray($soapEmployees);
      }
      return $employees;
    }
    return false;
  } // END parseEmployees()

  //----------------------------------------------------------------------------

  public function parseArticleGroups($soapArticleGroups) {
    if (isset($soapArticleGroups->articleGroupList->articleGroups)) {
      $soapArticleGroups = $soapArticleGroups->articleGroupList->articleGroups;
    } elseif (isset($soapArticleGroups->articleGroups)) {
      $soapArticleGroups = $soapArticleGroups->articleGroups;
    } else {
      $soapArticleGroups = null;
    }
    if ( ! is_null($soapArticleGroups)) {
      if ( ! is_array($soapArticleGroups)) {
        $soapArticleGroups = array($soapArticleGroups);
      }
      $products = array();
      foreach ($soapArticleGroups as $soapArticleGroup) {
        $articleGroup = objectToArray($soapArticleGroup);
        $articleGroup['subGroupList'] = $this->parseArticleSubGroups($articleGroup['subGroupList']);
        $articleGroups[] = $articleGroup;
      }
      return $articleGroups;
    }
    return false;
  } // END parseArticleGroups()

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

  public function parseOrderResult($soapOrderResult) {
    if (isset($soapOrderResult->result) and $soapOrderResult->result == 'GET-ORDER-RESULT-OK') {
      if (isset($soapOrderResult->order)) {
        $soapOrder = $soapOrderResult->order;
        $order = objectToArray($soapOrder);
        return $order;
      }
    }
    return false;
  } // END parseOrderResult()

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

  public function parseFindRelationResult($soapFindRelationResult) {
    if (isset($soapFindRelationResult->result) and $soapFindRelationResult->result == 'FIND-RELATION-RESULT-OK') {
      if (isset($soapFindRelationResult->relation)) {
        return objectToArray($soapFindRelationResult->relation);
      }
    }
    return false;
  } // END parseFindRelationResult()

  //----------------------------------------------------------------------------

  public function parseGetRelationResult($soapGetRelationResult) {
    if (isset($soapGetRelationResult->result) and $soapGetRelationResult->result == 'GET-RELATION-RESULT-OK') {
      if (isset($soapGetRelationResult->relation)) {
        return objectToArray($soapGetRelationResult->relation);
      }
    }
    return false;
  } // END parseGetRelationResult()

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
    } else {
      return false;
    }
  } // END parseUpdateOrderResult()

  //----------------------------------------------------------------------------

  public function parseCreateOrderResult($soapCreateOrderResult) {
    if (isset($soapCreateOrderResult->result) and $soapCreateOrderResult->result == 'CREATE-ORDER-RESULT-OK') {
      if (isset($soapCreateOrderResult->info)) {
        return objectToArray($soapCreateOrderResult->info);
      }
    } else {
      return false;
    }
  } // END parseCreateOrderResult()

  //----------------------------------------------------------------------------

  public function parseCancelOrderResult($soapCancelOrderResult) {
    if (isset($soapCancelOrderResult->result) and $soapCancelOrderResult->result == 'CANCEL-ORDER-RESULT-OK') {
      return true;
    } else {
      return false;
    }
  } // END parseCancelOrderResult()

  //----------------------------------------------------------------------------

  public function parseSendMessageResult($soapSendMessageResult) {
    if (isset($soapSendMessageResult->response)) {
      return strtolower($soapSendMessageResult->response) == 'true';
    }
    return false;
  } // END parseSendMessageResult())

  //----------------------------------------------------------------------------

  public function parseCreateRelationResult($soapCreateRelationResult) {
    if (isset($soapCreateRelationResult->result) and $soapCreateRelationResult->result == 'CREATE-RELATION-RESULT-OK') {
      if (isset($soapCreateRelationResult->relationNumber)) {
        return $soapCreateRelationResult->relationNumber;
      }
    }
    return false;
  } // END parseCreateRelationResult()

  //----------------------------------------------------------------------------

  public function parseUpdateRelationResult($soapUpdateRelationResult) {
    if (isset($soapUpdateRelationResult->result) and $soapUpdateRelationResult->result == 'UPDATE-RELATION-RESULT-OK') {
      return true;
    }
    return false;
  } // END parseUpdateRelationResult()

  //----------------------------------------------------------------------------

  public function parseCreateProductResult($soapCreateProductResult) {
    if (isset($soapCreateProductResult->result) and $soapCreateProductResult->result == 'CREATE-PRODUCT-RESULT-OK') {
      if (isset($soapCreateProductResult->productNumber)) {
        return $soapCreateProductResult->productNumber;
      }
    }
    return false;
  } // END parseCreateProductResult()

  //----------------------------------------------------------------------------

  public function parseUpdateProductResult($soapCreateProductResult) {
    if (isset($soapUpdateProductResult->result) and $soapUpdateProductResult->result == 'UPDATE-PRODUCT-RESULT-OK') {
      if (isset($soapUpdateProductResult->productNumber)) {
        return $soapUpdateProductResult->productNumber;
      }
    }
    return false;
  } // END parseUpdateProductResult()

  //----------------------------------------------------------------------------

  /*public function convertOrder($order) {
    if ( ! isset($order['orderId'])) {
      $order['orderId'] = null;
    }
    if ( ! isset($order['entryTimestamp'])) {
      $order['entryTimestamp'] = null;
    }
    if ( ! isset($order['orderDeliveryState'])) {
      $order['orderDeliveryState'] = null;
    }
    if ( ! isset($order['orderCancelState'])) {
      $order['orderCancelState'] = null;
    }
    if ( ! isset($order['orderCompleteState'])) {
      $order['orderCompleteState'] = null;
    }
    if (isset($order['lineList']) and is_array($order['lineList'])) {
      foreach ($order['lineList'] as $idx => $line) {
        if ( ! isset($line['articleNumber'])) {
          $line['articleNumber'] = 0;
        }
        $order['lineList'][$idx] = $line;
      }
      if (count($order['lineList']) > 1) {
        $order['lineList'] = array('line' => $order['lineList']);
      } elseif (count($order['lineList']) == 1) {
        $order['lineList'] = array('line' => current($order['lineList']));
      }
    }
    $object = arrayToObject(array('order'=>$order));
    // print_r($object);exit;
    return $object;
  } // END convertOrder()*/

  //----------------------------------------------------------------------------

  public function convertGetProductsRequest($articleNumbers, $groupNumbers, $pluNumbers)
  {
    if ( ! is_array($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    if ( ! is_array($groupNumbers)) {
      $groupNumbers = array($groupNumbers);
    }
    if ( ! is_array($pluNumbers)) {
      $pluNumbers = array($pluNumbers);
    }
    $object = arrayToObject(array('request'=>array(
      'articleNumbers'=>array_values($articleNumbers),
      'groupNumbers'=>array_values($groupNumbers),
      'pluNumbers'=>$this->convertPluNumbers($pluNumbers),
      )));
    return $object;
  } // END convertGetProductsRequest()

  //----------------------------------------------------------------------------

  public function convertGetEmployeesRequest($employeeNumbers)
  {
    if ( ! is_array($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    $object = arrayToObject(array('request'=>array(
      'employeeNumbers'=>empty($employeeNumbers)?null:array_values($employeeNumbers),
      )));
    return $object;
  } // END convertGetEmployeesRequest()

  //----------------------------------------------------------------------------

  public function convertGetShiftsRequest($financialDate, $branchNumbers, $employeeNumbers)
  {
    if ( ! isset($financialDate) or is_null($financialDate) or empty($financialDate)) {
      $financialDate = time();
    }
    $financialDate = $this->convertMplusDate($financialDate);
    if ( ! is_array($branchNumbers)) {
      $branchNumbers = array($branchNumbers);
    }
    if ( ! is_array($employeeNumbers)) {
      $employeeNumbers = array($employeeNumbers);
    }
    $object = arrayToObject(array('request'=>array(
      'financialDate'=>$financialDate,
      'branchNumbers'=>empty($branchNumbers)?null:array_values($branchNumbers),
      'employeeNumbers'=>empty($employeeNumbers)?null:array_values($employeeNumbers),
      )));
    return $object;
  } // END convertGetShiftsRequest()

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

  public function convertGetArticleGroupsRequest($groupNumbers)
  {
    if ( ! is_array($groupNumbers)) {
      $groupNumbers = array($groupNumbers);
    }
    $object = arrayToObject(array('request'=>array('groupNumbers'=>array_values($groupNumbers))));
    return $object;
  } // END convertGetArticleGroupsRequest()

  //----------------------------------------------------------------------------

  public function convertGetStockRequest($branchNumber, $articleNumbers)
  {
    if ( ! is_array($articleNumbers)) {
      $articleNumbers = array($articleNumbers);
    }
    $object = arrayToObject(array('request'=>array(
      'branchNumber'=>$branchNumber,
      'articleNumbers'=>array('articleNumbers'=>$articleNumbers))));
    if (empty($articleNumbers)) {
      $object->request->articleNumbers->articleNumbers = array();
    }
    return $object;
  } // END convertGetStockRequest()

  //----------------------------------------------------------------------------

  public function convertSendMessageRequest($branchNumber, $terminalNumber, $text)
  {
    $object = arrayToObject(array('request'=>array(
      'branchNumber'=>$branchNumber,
      'terminalNumber'=>$terminalNumber,
      'text'=>$text)));
    return $object;
  } // END convertSendMessageRequest()

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

  public function convertRelation($relation) {
    if ( ! isset($relation['relationNumber'])) {
      $relation['relationNumber'] = 0;
    }
    if ( ! isset($relation['name'])) {
      $relation['name'] = '';
    }
    if ( ! isset($relation['address'])) {
      $relation['address'] = '';
    }
    if ( ! isset($relation['zipcode'])) {
      $relation['zipcode'] = '';
    }
    if ( ! isset($relation['city'])) {
      $relation['city'] = '';
    }
    if ( ! isset($relation['country'])) {
      $relation['country'] = '';
    }
    if ( ! isset($relation['deliveryAddress'])) {
      $relation['deliveryAddress'] = '';
    }
    if ( ! isset($relation['deliveryZipcode'])) {
      $relation['deliveryZipcode'] = '';
    }
    if ( ! isset($relation['deliveryCity'])) {
      $relation['deliveryCity'] = '';
    }
    if ( ! isset($relation['deliveryCountry'])) {
      $relation['deliveryCountry'] = '';
    }
    if ( ! isset($relation['contact'])) {
      $relation['contact'] = '';
    }
    if ( ! isset($relation['telephone'])) {
      $relation['telephone'] = '';
    }
    if ( ! isset($relation['mobile'])) {
      $relation['mobile'] = '';
    }
    if ( ! isset($relation['email'])) {
      $relation['email'] = '';
    }
    if ( ! isset($relation['website'])) {
      $relation['website'] = '';
    }
    if ( ! isset($relation['points'])) {
      $relation['points'] = 0;
    }
    if ( ! isset($relation['balance'])) {
      $relation['balance'] = 0;
    }
    $object = arrayToObject(array('relation'=>$relation));
    return $object;
  } // END convertRelation()

  //----------------------------------------------------------------------------

  public function convertGeneric($name, $value) {
    $object = arrayToObject(array($name=>$value));
    return $object;
  } // END convertGeneric()

  //----------------------------------------------------------------------------

  public function convertProduct($product)
  {
    if ( ! isset($product['productNumber'])) {
      $product['productNumber'] = 0;
    }
    if ( ! isset($product['description'])) {
      $product['description'] = '';
    }
    if ( ! isset($product['articleList'])) {
      $product['articleList'] = array();
    }
    if ( ! isset($product['articleList']['article'])) {
      $product['articleList'] = array('article' => $product['articleList']);
    }
    foreach ($product['articleList']['article'] as $idx => $article) {
      if ( ! isset($article['articleNumber'])) {
        $article['articleNumber'] = 0;
      }
      if ( ! isset($article['pluNumber'])) {
        $article['pluNumber'] = 0;
      }
      if ( ! isset($article['description'])) {
        $article['description'] = '';
      }
      if ( ! isset($article['colour'])) {
        $article['colour'] = '';
      }
      if ( ! isset($article['size'])) {
        $article['size'] = '';
      }
      if ( ! isset($article['invoice-text'])) {
        $article['invoice-text'] = '';
      }
      if ( ! isset($article['receipt-text'])) {
        $article['receipt-text'] = '';
      }
      if ( ! isset($article['display-text'])) {
        $article['display-text'] = '';
      }
      if ( ! isset($article['barcode'])) {
        $article['barcode'] = '';
      }
      if ( ! isset($article['turnoverGroup'])) {
        $article['turnoverGroup'] = 0;
      }
      if ( ! isset($article['vatCode'])) {
        $article['vatCode'] = 0;
      }
      if ( ! isset($article['vatPercentage'])) {
        $article['vatPercentage'] = 0;
      }
      if ( ! isset($article['purchasePrice'])) {
        $article['purchasePrice'] = 0;
      }
      if ( ! isset($article['priceIncl'])) {
        $article['priceIncl'] = 0;
      }
      if ( ! isset($article['priceExcl'])) {
        $article['priceExcl'] = 0;
      }
      if ( ! isset($article['imageList'])) {
        $article['imageList'] = array();
      }
      if ( ! isset($article['imageList']['image']) and ! empty($article['imageList'])) {
        $article['imageList'] = array('image' => $article['imageList']);
      }
      $product['articleList']['article'][$idx] = $article;
    }
    $object = arrayToObject(array('product'=>$product));
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
    $object = arrayToObject(array('terminal'=>$terminal));
    return $object;
  } // END convertTerminal()

  //----------------------------------------------------------------------------

  public function convertOrder($order)
  {
    if ( ! isset($order['orderId'])) {
      $order['orderId'] = '';
    }
    if ( ! isset($order['extOrderId'])) {
      $order['extOrderId'] = '';
    }
    if ( ! isset($order['entryBranchNumber'])) {
      $order['entryBranchNumber'] = 0;
    }
    if ( ! isset($order['employeeNumber'])) {
      $order['employeeNumber'] = 0;
    }
    if ( ! isset($order['entryTimestamp'])) {
      $order['entryTimestamp'] = time();
    }
    $order['entryTimestamp'] = $this->convertMplusDateTime($order['entryTimestamp']);
    if ( ! isset($order['relationNumber'])) {
      $order['relationNumber'] = 0;
    }
    if ( ! isset($order['financialDate'])) {
      $order['financialDate'] = time();
    }
    $order['financialDate'] = $this->convertMplusDate($order['financialDate']);
    if ( ! isset($order['financialBranchNumber'])) {
      $order['financialBranchNumber'] = 0;
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
    if ( ! isset($order['deliveryState'])) {
      $order['deliveryState'] = 'ORDER-DELIVERY-STATE-NOTHING';
    }
    if ( ! isset($order['cancelState'])) {
      $order['cancelState'] = 'ORDER-CANCEL-STATE-NOTHING';
    }
    if ( ! isset($order['completeState'])) {
      $order['completeState'] = 'ORDER-COMPLETE-STATE-NOTHING';
    }
    if ( ! isset($order['tableNumber'])) {
      $order['tableNumber'] = 0;
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
    $order['lineList'] = $this->convertOrderLineList($order['lineList']);
    $object = arrayToObject(array('order'=>$order));
    return $object;
  } // END convertOrder();

  //----------------------------------------------------------------------------

  public function convertOrderLineList($lineList, $is_preparationList=false)
  {
    if ( ! isset($lineList['line']) and ! empty($lineList)) {
      $lineList = array('line'=>$lineList);
    }
    if (isset($lineList['line'])) {
      foreach ($lineList['line'] as $idx => $line) {
        if ( ! isset($line['lineId'])) {
          $line['lineId'] = '';
        }
        if ( ! isset($line['employeeNumber'])) {
          $line['employeeNumber'] = 0;
        }
        if ( ! isset($line['articleNumber'])) {
          $line['articleNumber'] = 0;
        }
        if ( ! isset($line['pluNumber'])) {
          $line['pluNumber'] = 0;
        }
        if ( ! isset($line['text'])) {
          $line['text'] = '';
        }
        if (isset($line['data'])) {
          if ( ! isset($line['data']['quantity'])) {
            $line['data']['quantity'] = 1;
          }
          if ( ! isset($line['data']['price'])) {
            $line['data']['price'] = 0;
          }
          if ( ! isset($line['data']['turnoverGroup'])) {
            $line['data']['turnoverGroup'] = 0;
          }
          if ( ! isset($line['data']['vatCode'])) {
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
          }
        }
        if ( ! isset($line['courseNumber'])) {
          $line['courseNumber'] = 0;
        }
        if ( ! isset($line['lineType'])) {
          $line['lineType'] = 'ORDER-LINE-TYPE-NONE';
        }
        if ( ! isset($line['preparationList'])) {
          $line['preparationList'] = array();
        }
        if ( ! $is_preparationList) {
          $line['preparationList'] = $this->convertOrderLineList($line['preparationList'], true);
        }
        $lineList['line'][$idx] = $line;
      }
    }
    $object = arrayToObject($lineList);
    return $object;
  } // END convertOrderLineList()

  //----------------------------------------------------------------------------

  public function convertMplusDateTime($timestamp)
  {
    return array(
      'day' => date('j', $timestamp),
      'mon' => date('n', $timestamp),
      'year' => date('Y', $timestamp),
      'hour' => date('H', $timestamp),
      'min' => date('i', $timestamp),
      'sec' => date('s', $timestamp),
      'isdst' => false,
      'timezone' => 0,
      );
  } // END convertMplusDateTime()

  //----------------------------------------------------------------------------

  public function parseMplusDateTime($mplus_date_time)
  {
    if ($mplus_date_time['day'] == 0 || $mplus_date_time['mon'] == 0 || $mplus_date_time['year'] == 0) {
      return null;
    } else {
      return mktime($mplus_date_time['hour'], $mplus_date_time['min'], $mplus_date_time['sec'], $mplus_date_time['mon'], $mplus_date_time['day'], $mplus_date_time['year']);
    }
  } // END parseMplusDateTime()

  //----------------------------------------------------------------------------

  public function convertMplusDate($timestamp)
  {
    return array(
      'day' => date('j', $timestamp),
      'mon' => date('n', $timestamp),
      'year' => date('Y', $timestamp),
      );
  } // END convertMplusDate()

  //----------------------------------------------------------------------------

  public function parseMplusDate($mplus_date)
  {
    if ($mplus_date['day'] == 0 || $mplus_date['mon'] == 0 || $mplus_date['year'] == 0) {
      return null;
    } else {
      return mktime(0, 0, 0, $mplus_date['mon'], $mplus_date['day'], $mplus_date['year']);
    }
  } // END parseMplusDate()

  //----------------------------------------------------------------------------

  public function convertYearNumber($year_number)
  {
    return $year_number;
  } // END convertYearNumber()

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

  public function convertSaveTableOrder($terminal, $order)
  {
    $terminal = $this->convertTerminal($terminal);
    $order = $this->convertOrder($order);
    $object = arrayToObject(array(
      'terminal'=>$terminal->terminal,
      'order'=>$order->order,
      ));
    return $object;
  } // END convertSaveTableOrder()

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
}

//------------------------------------------------------------------------------

class MplusQAPIException extends Exception
{

}

//------------------------------------------------------------------------------

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

//------------------------------------------------------------------------------

$global_leave_as_array = null;
function arrayToObject($d, $leave_as_array=null) {
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
    if (isset($d['articleNumbers']) or isset($d['groupNumbers'])) {
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

//------------------------------------------------------------------------------