ZOHO Inventory API SDK
==================
Unofficial ZOHO Inventory API SDK for PHP using O'Auth 2.0

Installation
------------
Quick install
```
composer require plakidan/zoho-inventory-api
```

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist plakidan/zoho-inventory-api "*"
```

or add

```
"plakidan/zoho-inventory-api": "*"
```

to the require section of your `composer.json` file.


Usage
-----

```
require_once 'vendor/autoload.php';
use plakidan\Zoho\Inventory\Client;

$inventory = new Client([
        'accessToken' => 'your access token', 
        'refreshToken' => 'your refresh token', 
        'clientId' => 'your client ID', 
        'clientSecret' => 'your client secret', 
        'redirectUri' => 'one of any redirect URI', 
        'organizationId' => 'your org id'
    ]);

$inventory->listContacts(); // Get all contacts
```