<?php

define('TERMINAL_STATUS_AVAILABLE', 1);
define('TERMINAL_STATUS_REGISTERED', 2);
define('TERMINAL_STATUS_UNKNOWN', 3);

class MplusQAPIclient
{
  const CLIENT_VERSION  = '0.0.1';

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
    if (false === stripos($location, 'http://') and false === stripos($location, 'https://')) {
      $location = 'https://'.$location;
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
      'cache_wsdl' => WSDL_CACHE_NONE, 
      'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
      );

    if ( ! $this->checkFingerprint($location)) {
      throw new MplusQAPIException('Fingerprint of SSL certificate doesn\'t match.');
    }

    // $this->client = new SoapClient('MplusQapi.wsdl', $options);
    $this->client = new SoapClient(null, $options);
  } // END initClient()

  //----------------------------------------------------------------------------

  protected function checkFingerprint($location)
  {
    $fingerprint_matches = false;
    $g = stream_context_create (array('ssl' => array('capture_peer_cert' => true)));
    if (false === ($r = stream_socket_client(str_replace('https', 'ssl', $location), $errno,
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

  public function getProducts($groupNumbers = array())
  {
    try {
      $result = $this->client->getProducts($this->parser->convertGetProductsRequest($groupNumbers));
      return $this->parser->parseProducts($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getOrder()

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

  public function getOrder($orderUuid)
  {
    try {
      $result = $this->client->getOrder($this->parser->convertOrderUuid($orderUuid));
      // print_r($result);exit;
      return $this->parser->parseOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END getOrder()

  //----------------------------------------------------------------------------

  public function createOrder($order)
  {
    try {
      if ( ! isset($order['OrderUuid'])) {
        $order['OrderUuid'] = null;
      }
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
      if ( ! isset($order['OrderUuid'])) {
        throw new MplusQAPIException("No OrderUuid set.");
      }
      // print_r($this->parser->convertOrder($order));exit;
      $result = $this->client->updateOrder($this->parser->convertOrder($order));
      return $this->parser->parseUpdateOrderResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  } // END updateOrder()

  //----------------------------------------------------------------------------

  public function cancelOrder($orderUuid)
  {
    try {
      $result = $this->client->cancelOrder($this->parser->convertOrderUuid($orderUuid));
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
      if ( ! isset($relation['relationNumber'])) {
        $relation['relationNumber'] = 0;
      }
      $result = $this->client->findRelation($this->parser->convertRelation($relation));
      return $this->parser->parseFindRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  }

  public function createRelation($relation)
  {
    try {
      if ( ! isset($relation['relationNumber'])) {
        $relation['relationNumber'] = 0;
      }
      $result = $this->client->createRelation($this->parser->convertRelation($relation));
      return $this->parser->parseCreateRelationResult($result);
    } catch (SoapFault $e) {
      throw new MplusQAPIException("SoapFault occurred: ".$e->getMessage(), 0, $e);
    } catch (Exception $e) {
      throw new MplusQAPIException("Exception occurred: ".$e->getMessage(), 0, $e);
    }
  }

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
        case 'TERMINAL-STATUS-UNKNOWN':
        default:
          $terminal['terminalStatus'] = TERMINAL_STATUS_UNKNOWN;
          break;
      }
      $terminals[] = $terminal;
    }
    return $terminals;
  } // END parseTerminalList()

  //----------------------------------------------------------------------------

  public function parseProducts($soapProducts) {
    if (isset($soapProducts->products)) {
      $soapProducts = $soapProducts->products;
    } elseif (isset($soapProducts['products'])) {
      $soapProducts = $soapProducts['products'];
    } else {
      $soapProducts = null;
    }
    if ( ! is_null($soapProducts)) {
      if ( ! is_array($soapProducts)) {
        $soapProducts = array($soapProducts);
      }
      $products = array();
      foreach ($soapProducts as $soapProduct) {
        $product = objectToArray($soapProduct);
        if ( ! is_array($product['groupNumbers'])) {
          if ( ! empty($product['groupNumbers'])) {
            $product['groupNumbers'] = array($product['groupNumbers']);
          } else {
            $product['groupNumbers'] = array();
          }
        }
        if (isset($product['articles']['article']) and ! isset($product['articles']['article']['articleNumber'])) {
          $product['articles'] = $product['articles']['article'];
        }
        foreach ($product['articles'] as $idx => $article) {
          $orig_article = $article;
          if (isset($article['images']['image']) and ! isset($article['images']['image']['imageName'])) {
            $article['images'] = $article['images']['image'];
          }
          if ( ! is_array($article['images'])) {
            if ( ! empty($article['images'])) {
              $article['images'] = array($article['images']);
            } else {
              $article['images'] = array();
            }
          }
          $product['articles'][$idx] = $article;
        }
        $products[] = $product;
      }
      return $products;
    }
    return false;
  } // END parseProducts()

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
  } // END parseStock();

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

  public function parseFindRelationResult($soapFindRelationResult) {
    if (isset($soapFindRelationResult->result) and $soapFindRelationResult->result == 'FIND-RELATION-RESULT-OK') {
      if (isset($soapFindRelationResult->relation)) {
        return objectToArray($soapFindRelationResult->relation);
      }
    }
    return false;
  } // END parseCreateRelationResult()

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
      return $soapSendMessageResult->response;
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

  public function convertOrder($order) {
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
    // print_r($order);exit;
    $object = arrayToObject(array('order'=>$order));
    // print_r($object);exit;
    return $object;
  } // END convertOrder()

  //----------------------------------------------------------------------------

  public function convertGetProductsRequest($groupNumbers)
  {
    if ( ! is_array($groupNumbers)) {
      $groupNumbers = array($groupNumbers);
    }
    $object = arrayToObject(array('request'=>array('groupNumbers'=>array_values($groupNumbers))));
    return $object;
  } // END convertGetProductsRequest()

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

  public function convertOrderUuid($orderUuid) {
    $object = arrayToObject(array('orderUuid'=>$orderUuid));
    return $object;
  } // END convertOrderUuid()

  //----------------------------------------------------------------------------

  public function convertRelation($relation) {
    if ( ! isset($relation['website'])) {
      $relation['website'] = '';
    }
    if ( ! isset($relation['points'])) {
      $relation['points'] = 0;
    }
    if ( ! isset($relation['balance'])) {
      $relation['balance'] = 0;
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
    $object = arrayToObject(array('relation'=>$relation));
    // print_r($object);exit;
    return $object;
  } // END convertRelation()

  //----------------------------------------------------------------------------
}

class MplusQAPIException extends Exception
{

}

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

function arrayToObject($d) {
  if (is_array($d)) {
    /*
    * Return array converted to object
    * Using __FUNCTION__ (Magic constant)
    * for recursive call
    */
    if (isset($d['groupNumbers'])) {
      return (object) $d;
    } elseif (isset($d[0]['articleNumber'])) { // Als het om een element van 'articleNumber' gaat, dan moet het een Array zijn
      return array_map(__FUNCTION__, $d);
    } elseif (isset($d[0]) and is_integer($d[0])) {
      return array_map(__FUNCTION__, $d);
    } else {
      return (object) array_map(__FUNCTION__, $d);
    }
  }
  else {
    // Return object
    return $d;
  }
} // END arrayToObject()