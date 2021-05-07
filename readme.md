# Google Analytics Measurement Protocol v1 for SilverStripe

## Introduction
This module adds functionality to be able to send hits to Google Analytics via server side using Google's Measurement Protocol Version 1: https://developers.google.com/analytics/devguides/collection/protocol/v1

The module currently allows the sending of the following hits:
- pageviews
- events
- timing

This is accomplished by constructing a GET request and using Guzzle to send the request to the Google Analytics endpoint.

## Requirements
* SilverStripe CMS ^4.0
* Guzzle ^6.3

## Installation
Install the module via composer:
``` 
composer require internetrix/silverstripe-ga-measurement-protocol
```

## Configuation & Setup
1. Set the following variables for `Internetrix\GaMeasurementProtocol\Model\GAHit` in config:
    - <b>useProductionGAProperty:</b> Set this true if you want to use the Production tracking ID. Set to false if you want to send hits to staging.
    - <b>stagingTrackingID:</b> Google Analytics UA tracking ID for staging/testing property. Expected format: 'UA-XXXXXXXX-X'
   - <b>productionTrackingID:</b> Google Analytics UA tracking ID for production property. Expected format: 'UA-XXXXXXXX-X'

<b>Example YML configuration:</b>
```
Internetrix\GaMeasurementProtocol\Model\GAHit:
  useProductionGAProperty: true
  stagingTrackingID: 'UA-XXXXXXXX-X'
  productionTrackingID: 'UA-XXXXXXXX-X'
  useTestingEndpoint: false
```

<b>Optional</b>: Another useful config variable that is useful for debugging / testing is to set ```useTestingEndpoint``` to true in config:
```
Internetrix\GaMeasurementProtocol\Model\GAHit:
  useTestingEndpoint: true
```         
By setting <b>useTestingEndpoint</b> to true, when sending hits, the module will send the request to Google's Validation Server. 

As Google's GA Measurement Protocol does not return HTTP error codes, by sending requests to the Measurement Protocol Validation Server, you can test the response. If the current SS environment is set to dev, it will also output the response to the browser / terminal. 

More information on Google's Measurement Protocol Validation Server can be found here: https://developers.google.com/analytics/devguides/collection/protocol/v1/validating-hits

## Sending hits to a Production Google Analytics property
For hits to be sent to a Production property, all the following conditions must be met. Otherwise hits will ALWAYS be sent to staging property / debug endpoint.
- Condition 1 - SilverStripe `SS_ENVIRONMENT_TYPE` variable must be set to `live`
- Condition 2 - `useProductionGAProperty` must be set to true in config
- Condition 3 - `useTestingEndpoint` must be set to false in config

## Generate ClientID
<b>IMPORTANT:</b> When sending a GA hit, always ensure that the hit type is set and that a unique client ID is given. This module contains a function called setClientID to help developers add a client ID.
The first parameter of function tells the function if the _ga cookie should be retrieved or not when creating a clientID. The second parameters allows a developer to override the clientID for a hit.


There are some ways this function can be used:
- Option 1: Retrieve the same ClientID using the _ga cookie (shared by frontend)
  - `$pageHit->setClientID(true);`

- Option 2: Generate a completely new unique client ID. If this option is saved, the unique client ID should be saved by the developer and re-used so that Google Analytics recognises the same user
  - `$pageHit->setClientID(false);`

- Option 3: Override and set the GA Client ID to a value arbitrarily defined by a developer
    - `$pageHit->setClientID(false, '<CUSTOM-CLIENT-ID>);`
    
## Usage Examples
The PHPDocs in <b>GAHit.php</b> contains links explaining what parameters are required for each type of event. Otherwise, please refer to the [Official Google Analytics Measurement Protocol documentation](https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters)

### Example 1: Sending a Pageview hit to GA

Required Parameters:
- HitType = pageview
- Set a GA Client ID by calling: `$hit->setClientID`. See above Generate ClientID section for more details
- Include either (Document Hostname + Document Path + (optionally) Document Title) OR set the Document Location URL
- Set a User-Agent by calling: `$hit->setUserAgent()` otherwise Google Analytics may classify the hit as bot spam
```
$hit = GAHit::create();
$hit->setHitType(GAHit::PAGEVIEW);
$hit->setClientID(true);
$hit->setUserAgent();
 
// Option 1
$hit->setPageviewParameters('https://example.com.au', '/foo', 'Test Page');
// OR Option 2
$hit->setDocumentLocationURL('https://example.com.au/foo', 'Test Page');

// Send hit to Google Analytics
$hit->sendHit();
```

### Example 2: Sending an Event hit to GA
Required Parameters:
- HitType = event
- Set a GA Client ID by calling: `$hit->setClientID`. See above Generate ClientID section for more details
- Set a User-Agent by calling: `$hit->setUserAgent()` otherwise Google Analytics may classify the hit as bot spam
- Event Category and Event Action are required parameters. Event Label and Event Value is optional. Set these parameters by calling: `$hit->setEventParameters('category', 'action', 'label', $value)`
```
$hit = GAHit::create();
$hit->setHitType(GAHit::EVENT);
$hit->setClientID(true);
$hit->setUserAgent();
$hit->setEventParameters('TestCategory', 'TestAction', 'TestLabel', 1);
$hit->setDocumentLocationURL('www.example.com.au/foo');

// (optional) Set as non-interaction hit if required
$hit->setNonInteractionHit();

// Send hit to Google Analytics
$hit->sendHit();
```

### Example 3: Sending a Timing hit to GA
```
        $hit = GAHit::create();
        $hit->setHitType(GAHit::TIMING);
        $hit->setClientID(true);
        $hit->setTimingParameters($category, $userTimingVariable, $timingTime, $optionalParameters);
        $hit->setDocumentLocationURL('www.example.com.au');
        
        // Send hit to Google Analytics
        $hit->sendHit();
```
Required Parameters:
- HitType = timing
- Set a GA Client ID by calling: `$hit->setClientID`. See above Generate ClientID section for more details
- Set a User-Agent by calling: `$hit->setUserAgent()` otherwise Google Analytics may classify the hit as bot spam
- Timing category, timing variable and timing timing are required. Set these parameters by calling: `$hit->setTimingParameters($category, $userTimingVariable, $timingTime, $optionalParameters)`

### Adding Custom Dimensions, Custom Metrics and / or adding extra parameters to a hit
To add additional parameters like Custom Dimensions, Custom Metrics etc to a hit, use the `addParameters` method where `$parameters` should be an array with `parameter name` as the key, and the `parameter value` as the array value:
```
// Adding a Custom Dimension (with dimension index 1) and setting it to the value 'Sports'
$parameters['cd1'] = 'Sports'; 

// Adding a Custom Metric (with metric index of 1) and setting it to the int alue 47
$parameters['cm1'] = 47'; 

// Add custom dimension / metric to the hit
$hit->addParameters($parameters);
```

## Todo
- Add support for other type of hits such as transactions etc.

## Licence
Please see [License File](LICENSE.md) for more information.
