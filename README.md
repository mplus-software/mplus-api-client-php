mplus-api-client-php
====================

PHP client for the Mplus Q-line API.

### Example of usage:

The following script will connect to the API with your credentials and try to request the currently running version of the API.

```php
<?php

require_once('Mplusqapiclient.php');

$mplusqapiclient = new Mplusqapiclient();
$mplusqapiclient->setApiServer($your_api_url);
$mplusqapiclient->setApiPort($your_api_port);
$mplusqapiclient->setApiFingerprint($certificate_fingerprint);
$mplusqapiclient->setApiIdent($your_api_ident);
$mplusqapiclient->setApiSecret($your_api_secret);

try {
  $mplusqapiclient->initClient();
} catch (MplusQAPIException $e) {
  exit($e->getMessage());
}
    
try {
  $api_version = $mplusqapiclient->getApiVersion();
  echo sprintf('Current API version: %d.%d.%d', 
    $api_version['majorNumber'], 
    $api_version['minorNumber'], 
    $api_version['revisionNumber']);
} catch (MplusQAPIException $e) {
  exit($e->getMessage());
}
```

Visit the [Mplus Developers website](http://developers.mpluskassa.nl/php/) for more information.
