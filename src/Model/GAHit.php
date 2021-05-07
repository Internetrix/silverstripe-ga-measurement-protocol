<?php

namespace Internetrix\GaMeasurementProtocol\Model;

/* Copyright 2021 Internetrix
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
version 2 as published by the Free Software Foundation.
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details. */

use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\FieldType\DBDatetime;
use GuzzleHttp\Client;

/**
 * Class GAHit
 * @package Internetrix\GaMeasurementProtocol\Model
 * @link https://developers.google.com/analytics/devguides/collection/protocol/v1
 */
class GAHit extends DataObject
{
    use Configurable;
    /**
     * Pageview hit type
     */
    const PAGEVIEW = 'pageview';
    /**
     * Event hit type
     */
    const EVENT = 'event';
    /**
     * Timing hit type
     */
    const TIMING = 'timing';
    /**
     * @var string
     */
    private static $table_name = 'IRX_GoogleAnalyticsHit';
    /**
     * @var string
     */
    private static $singular_name = 'GA Hit';
    /**
     * @var string
     */
    private static $plural_name = 'GA Hits';
    /**
     * @var string[]
     */
    private static $allowedHitTypes = [
        self::PAGEVIEW,
        self::EVENT,
        self::TIMING,
    ];
    /**
     * @var
     */
    private $trackingID;
    /**
     * @var
     */
    private $clientID;
    /**
     * @var
     */
    private $hitType;
    /**
     * @var
     */
    private $documentLocationURL;
    /**
     * @var array
     */
    private $parameters = [];

    /**
     * Determines where the GA hit should be sent to: Production or Staging property
     * @return bool
     */
    public function useProductionGAProperty()
    {
        $envType = Environment::getEnv('SS_ENVIRONMENT_TYPE');

        if (in_array($envType, ['dev', 'test'])) {
            return false;
        }

        $useProductionGA =  (bool)$this->config()->get('useProductionGAProperty');
        // Only use production GA if live env and config is set to true
        if ($envType == 'live' && $useProductionGA) {
            return true;
        }

        return false;
    }

    /**
     * Returns the protocol version. Will only change if Google introduces backwards incompatible changes
     * in the future
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#v
     * @return int
     */
    public function getProtocolVersion()
    {
        return 1;
    }

    /**
     * Set the GA Tracking ID of the property where hits will be sent to
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#tid
     */
    public function setTrackingID()
    {
        if ($this->useProductionGAProperty()) {
            $this->trackingID = $this->config()->get('productionTrackingID');
        } else {
            $this->trackingID = $this->config()->get('stagingTrackingID');
        }
    }

    /**
     *  Set the GA Client ID for the hit
     * @param false $useGACookie - flag to either read from _ga cookie or generate a new ClientID
     * @param null $overrideClientId - manually override the client Id for a hit
     */
    public function setClientID($useGACookie = false, $overrideClientId = null)
    {
        $this->clientID = $overrideClientId
            ? $overrideClientId
            : $this->generateClientID($useGACookie);
    }

    /**
     * Generates a unique ClientID or retrieve it from the _ga cookie
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#cid
     * @param $getGACookie
     * @return string|null
     */
    public static function generateClientID($getGACookie)
    {
        // Read GA Client ID cookie from frontend
        if ($getGACookie) {
            return Cookie::get('_ga');
        }
        // Otherwise generate one
        $randomNumber = mt_rand(1000, 9999999999);
        $currentTimeStamp = DBDatetime::create()->now()->getTimeStamp();
        return sprintf('%s.%s', $randomNumber, $currentTimeStamp);
    }

    /**
     * Set the user agent for a hit. Usually required as Google Analytics will often
     * class any hits without a user-agent as spam bot traffic
     * Either reads from the headers or can be manually overriden by a developer
     * @param null $userAgent - override User-Agent
     */
    public function setUserAgent($userAgent = null)
    {
        if (!$userAgent) {
            $userAgent = Controller::curr()->getRequest()->getHeader('user-agent');
        }
        $this->parameters['ua'] = $userAgent;
    }

    /**
     * Set the type of hit being sent.
     * For example: pageview, event, timing etc
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#t
     * @param $hitType
     */
    public function setHitType($hitType)
    {
        if (in_array($hitType, self::$allowedHitTypes)) {
            $this->hitType = $hitType;
        }
    }

    /**
     * Set the Document Location URL
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#dl
     * @param $documentLocationURL
     */
    public function setDocumentLocationURL($documentLocationURL, $documentTitle = null)
    {
        $this->documentLocationURL = $documentLocationURL;
        if ($pageTitle) {
            $this->parameters['dt'] = $documentTitle;
        }
    }

    /**
     * Set the parameters for Pageview hits
     * For 'pageview' hits, either &dl or both &dh and &dp have to be specified for the hit to be valid.
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#content
     */

    public function setPageviewParameters($documentHostName = null, $documentPath = null, $documentTitle = null)
    {
        if ($documentHostName) {
            $this->parameters['dh'] = $documentHostName;
        }
        if ($documentPath) {
            $this->parameters['dp'] = $documentPath;
        }
        if ($documentTitle) {
            $this->parameters['dt'] = $documentTitle;
        }
    }

    /**
     * Sets the parameters for Event hits
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#events
     * @param $category
     * @param $action
     * @param null $label
     * @param null $value
     */
    public function setEventParameters($category, $action, $label = null, $value = null)
    {
        $this->parameters['ec'] = $category;
        $this->parameters['ea'] = $action;

        if ($label) {
            $this->parameters['el'] = $label;
        }
        if ($value && $value >= 0) {
            $this->parameters['ev'] = $label;
        }
    }

    /**
     * Sets the parameters for timing hits
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#timing
     * @param $category
     * @param $userTimingVariable
     * @param $timingValue
     * @param array $optionalParameters
     */
    public function setTimingParameters($category, $userTimingVariable, $timingValue, $optionalParameters = [])
    {
        $this->parameters['utc'] = $category;
        $this->parameters['utv'] = $userTimingVariable;
        $this->parameters['utt'] = $timingValue;

        if (!empty($optionalParameters)) {
            foreach ($optionalParameters as $parameter => $value) {
                $this->parameters[$parameter] = $value;
            }
        }
    }

    /**
     * Set the hit as a non-interaction hit
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#ni
     */
    public function setNonInteractionHit()
    {
        $this->parameters['ni'] = 1;
    }

    /**
     * Random integer appended to end of request so that requests is not cached
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters#z
     * @return int
     */
    public function getCacheBuster()
    {
        $randomNumber = mt_rand(1000, 9999999999);
        return $randomNumber;
    }

    /**
     * Send the hit to Google Analytics using Measurement Protocol
     */
    public function sendHit()
    {
        $this->setTrackingID();
        $url = $this->buildURL();
        $validated = $this->checkMinimumParameters();

        if ($validated) {

            // Send hit to GA
            $client = new Client;
            $options = [
                CURLOPT_SSL_VERIFYPEER => false
            ];

            try {
                $response = $client->request('GET', $url, $options);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $response = $e->getResponse();
                $responseString = $response->getBody()->getContents();
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $response = $e->getResponse();
                $responseString = $response->getBody()->getContents();
            }

            $useTestingEndpoint =  (bool)$this->config()->get('useTestingEndpoint');

            if ($useTestingEndpoint) {
                Debug::show($response->getBody()->getContents());
            }
        }
    }

    /**
     * Check that all required parameters have been provided.
     * Required parameters are dependent on type of hit. Please consult Offical Google Analytics MP documentation for details:
     * @link https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters
     * @return bool
     */
    public function checkMinimumParameters()
    {
        if (!$this->trackingID || !$this->clientID) {
            return false;
        }
        if (!(in_array($this->hitType, [self::PAGEVIEW, self::EVENT, self::TIMING]))) {
            return false;
        }

        $parameters = $this->parameters;
        if ($this->hitType == self::PAGEVIEW) {
            if ((isset($parameters['dl'])) || (isset($parameters['dh'], $parameters['dp']))) {
                return true;
            }
        }
        if ($this->hitType == self::EVENT) {
            if (isset($parameters['ec'], $parameters['ea'])) {
                return true;
            }
        }
        if ($this->hitType == self::TIMING) {
            if (isset($parameters['utc'], $parameters['utv'], $parameters['utt'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build out the URL with query string
     * @return string
     */
    public function buildURL()
    {
        $this->parameters['v'] = $this->getProtocolVersion();
        $this->parameters['t'] = $this->hitType;
        $this->parameters['tid'] = $this->trackingID;
        $this->parameters['cid'] = $this->clientID;
        if ($this->documentLocationURL) {
            $this->parameters['dl'] = $this->documentLocationURL;
        }
        $this->parameters['z'] = $this->getCacheBuster();
        $this->parameters['uip'] = $this->getIPAddress();

        $query = http_build_query($this->parameters, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);

        $url = $this->getEndpoint() . $query;

        return $url;
    }

    /**
     * Returns Google Measurement Protocol endpoint where hits are sent to
     * @return string
     */
    public function getEndpoint()
    {
        $useTestingEndpoint =  (bool)$this->config()->get('useTestingEndpoint');

        if ($useTestingEndpoint) {
            return 'https://www.google-analytics.com/debug/collect?';
        }

        return 'https://www.google-analytics.com/collect?';
    }

    /**
     * Return the IP of the current request and attaches it to the hit.
     * Required usually, otherwise GA will classify hits without an IP as spam bot traffic
     * @return string
     */
    public function getIPAddress()
    {
        return Controller::curr()->getRequest()->getIP();
    }

    /**
     * Add additional parameters to the hits
     * i.e For Custom Dimensions / Custom Metric or any parameters not currently covered by this module
     * @param $parameters
     */
    public function addParameters($parameters)
    {
        foreach($parameters as $parameterName => $value) {
            $this->parameters[$parameterName] = $value;
        }
    }
}
