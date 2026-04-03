# Billmax API Client

## Table of Contents

- [What is it?](#what-is-it)
- [Requirements](#requirements)
- [Installation](#installation)
- [Todo](#todo)
- [Configuration](#configuration)
    - [Settings](#settings)
    - [Authentication Mode](#authentication-mode)
    - [Configuring with Environment Variables](#configuring-with-environment-variables)
    - [Configuring with Constructor](#configuring-with-constructor)
    - [Guzzle HTTP Options](#guzzle-http-options)
- [Getting your API Key](#getting-your-api-key)
- [Response](#response)
- [Function Parameters](#function-parameters)
- [Method Functions](#method-functions)
    - [GET](#get)
    - [POST](#post)
    - [PATCH](#patch)
    - [DELETE](#delete)
- [Request Method](#request-method)
- [Filter](#filter)
    - [Condition Format](#condition-format)
    - [Example](#example)
    - [Condition Functions](#condition-functions)
    - [Operator Functions](#operator-functions)
- [Getting your API key](#Getting-your-API-key)

## What is it?

This is a small lightweight PHP client for using the REST API service of Billmax billing software.

## Requirements

- PHP ^8.3
- guzzlehttp/guzzle ^7.10
- ocolin/global-type ^2.0

## Installation

```
composer require ocolin/billmax
```

## Todo

- Add OAuth2 support
- Add more integration testing

## Configuration

### Settings 

The client can be configured by either environment variables, constructor arguments, or a combination of both, Here is a list of the settings needed for configuration.

|Environment|Constructor| Description                    |
|-----------|-----------|--------------------------------|
|BILLMAX_API_KEY|$apiKey| API Key to access server       |
|BILLMAX_API_HOST|$host| Hostname and URI of API server |
|BILLMAX_API_AUTH|$auth| Authentication mode            |
|BILLMAX_API_USERNAME|$username| Username for USERPASS mode     |
|BILLMAX_API_PASSWORD|$password| Password for USERPASS mode     |
|BILLMAX_API_OAUTH2_CLIENT_ID|$clientId| Client ID for OAuth2 mode      |
|BILLMAX_API_OAUTH2_CLIENT_SECRET|$clientSecret| Client secret for OAuth2 mode  |

### Authentication mode.

The Billmax REST API has 3 authentication modes:

- NONE - Uses only the Billmax remote application session ID
- USERPASS - Uses session ID of a staff employee via username/password
- OAUTH2 - Uses OAuth2 to authenticate. Not yet supported by this client.

### Configuring with Environment variables.

```php
// Manually setting variables for demonstration
$_ENV['BILLMAX_API_HOST']     = 'https://myhost.com:3100/api/billmaxCoreApi/v1/';
$_ENV['BILLMAX_API_KEY']      = 'GETTHISFROMSESSIONIDINREMOTEAPP';
$_ENV['BILLMAX_API_AUTH']     = 'USERPASS';
$_ENV['BILLMAX_API_USERNAME'] = 'staffmember';
$_ENV['BILLMAX_API_PASSWORD'] = 'staffmemberpassword';

$billmax = new Ocolin\BillMax\Billmax();
```

### Configuring with constructor

The Billmax client uses a Config object class to hold settings. You can configure this by creating a Config object.

```php
$config = new Ocolin\Billmax\Config(
        host: 'https://myhost.com:3100/api/billmaxCoreApi/v1/',
      apiKey: 'GETTHISFROMSESSIONIDINREMOTEAPP',
        auth: 'USERPASS',
    username: 'staffmember',
    password: 'staffmemberpassword'
);

$billmax = new \Ocolin\Billmax\Billmax( $config: $config );
```

### Guzzle HTTP options.

This client uses Guzzle to handle HTTP calls and allows for extra options to be specified in the Config object.

```php
$config = new Ocolin\Billmax\Config(
    options: [
        'timeout' => 30,
        'verify'  => true
    ]
);
```

## Response

The Billmax client will return a response object with the following properties:

| Property name | Type          | Description                      |
|---------------|---------------|----------------------------------|
| status        | int           | The HTTP status code (200,401, etc) |
| statusMessage | string        | The HTTP status message          |
| headers       | array         | HTTP response headers            |
| body          | object\|array | HTTP response body, json decoded    |

## Function Parameters

| parameter | method     | type          | description                                                                                                                                                                           |
|-----------|------------|---------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| endpoint  | ALL        | string        | Endpoint URI path. Any variable tokens will get replaced with matching kesy in query array/object                                                                                     |
| method    | N/A        | string        | HTTP method to use in the request() method                                                                                                                                            |
| query     | ALL        | array\|object | URI path and query parameters. Keys that match {tokens} in URI will ne interpolated into the URI path                                                                                 |
| body      | POST/PATCH | array\|object | HTTP body in array or object format.                                                                                                                                                  |
| autoGet   | PATCH      | boolean       | Automatically get the stored generation value of row to be updated in Billmax. If the generation value changed from the time you check to the time you patch, an error will be thrown |

## Method functions

### GET

```php
$response = $billmax->get(
    endpoint: '/accounts/{id}',
       query: [ 'id' => 1234 ]
);
```

### POST

```php
$response = $billmax->post(
    endpoint: '/aps',
        body: [
        'name' => 'AP name',
        'description' => 'AP description',
        'pop' => 'My POP',
        'maximumDownloadRate' => 1000
        'maximumUploadRate' => 1000,
        'fccTechnologyCode' => 'Other Wireline',
        'technology' => 'wireless'
    ]
);
```

### PATCH

```php
$response = $billmax->patch(
    endpoint: '/aps/{id}',
       query: [ 'id' => 1234 ],
        body: [ 'name' => 'Updated AP name' ],
     autoGet: true // Defaults to true, but showing for example
);
```

### DELETE

```php
$response = $billmax->delete(
    endpoint: '/aps/{id}',
       query: [ 'id' => 1234 ]
);
```

## Request method

Also included is a generic function without a specific HTTP method.

```php
$response = $billmax->request(
    endpoint: '/aps/{id}',
    method: 'PATCH',
    query: [ 'id' => 1234 ],
    body: [ 'name' => 'updated name' ]
);
```

## Filter

The Billmax API has a query parameter that allows requests to be filtered. This class is to make creating filters easier by putting them in function calls rather than typing out a filter query.

A filter can have multiple conditions and each condiction consists of a field (a column in the database), an operator, and a value. The Filter allows these to be stacked together.

### Condition format

```
Filter::where( name of columns )
    ->operatorFunction( value )
    ->build()

Filter::where( name of column1 )
    ->operator1( value )
    ->also( name of column2 )
    ->operator2( value )
    ->build()
```

### Example

```php
use Ocolin\Billmax\Filter;
$output = $billmax->get(
    endpoint: '/accounts',
    query: [
        'fltrs' => Filter::where( 'id' )
                          ->in( [1,2,3] )
                          ->also( 'state' )
                          ->ne( 1 )
                          ->build()
    ]   
);
```
### Condition functions

| Function name | Description                   |
|---------------|-------------------------------|
| where         | start condition               |
| also          | add additional condition      |
| build         | build the query into a string |


### Operator functions

| Function name | Value type         | Description                          |
|---------------|--------------------|--------------------------------------|
| eq            | string\|int\|float | equals                               |
| ne            | string\|int\|float | not equals                           |
| like          | string             | like                                 |
| nlike         | string             | not like                             |
| in            | array              | in []                                |
| nin           | array              | not in []                            |
| has           | array              | has []                               |
| nhas          | array              | does not have []                     |
| gt            | string\|int\|float | greater than                         |
| lt            | string\|int\|float | less than                            |
| gte           | string\|int\|float | greater than or equal                |
| lte           | string\|int\|float | less than or equal                   |
| ft            | string             | fulltextindex - match against        |
| ftb           | string             | fulltextindex - in boolean mode      |
| ftq           | string             | fulltextindex - with query expansion |

## Getting your API key.

The api ksy is part of your billmax remove application and was generated when you set up the remote application. Locating your API Key:

- Log into Billmax staff portal
- Under the **System Administration** menu, go to **Remote Applications**
- Click on the number next to your **billmaxCoreApi** app
- Copy the value from the **Session Id** field
- Click **Generate Session Id** if you want to create a new key.

## Uploading

Because uploading files works much different from the rest of endpoints, a specific function as been created to handle file uploading.

### Upload arguments

| Argument  | type    | description                                                             |
|-----------|---------|-------------------------------------------------------------------------|
| entity    | string  | Entity to attach file. Ex: account, ticket, message, service, etc.      |
| entityId  | integer | The ID of entity to attach to. Ex: account 1, ticket 3, service 1, etc. |
| filePaths | array   | An array of file arrays. See table below for file path values.          |

### File Path Properties

Each file takes an array of values:

| Property Name | Type   | Descrition                                      |
|---------------|--------|-------------------------------------------------|
| path          | string | Path to and including the file to upload.       |
| class         | string | Billmax file class to use. Default is 'support' |
| description   | string | A description of the file for display purposes  |

### Example

```php
$output = $billmax->upload(
    entity: 'account',
    entityId: 1
    files: [
        [
            'path'        => __DIR__ '/file.txt',
            'class'       => 'support',
            'description' => 'My special file'
        ]
    ]
);
```