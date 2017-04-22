<?php

/**
 * Code related to the settings-apiservice.php interface.
 *
 * @package Sucuri Security
 * @subpackage settings-apiservice.php
 * @copyright Since 2010 Sucuri Inc.
 */

if (!defined('SUCURISCAN_INIT') || SUCURISCAN_INIT !== true) {
    if (!headers_sent()) {
        /* Report invalid access if possible. */
        header('HTTP/1.1 403 Forbidden');
    }
    exit(1);
}

/**
 * Returns the HTML to configure the API service status.
 *
 * @param bool $nonce True if the CSRF protection worked, false otherwise.
 * @return string HTML for the API service status option.
 */
function sucuriscan_settings_apiservice_status($nonce)
{
    global $sucuriscan_api_handlers;

    $params = array();

    $params['ApiStatus.StatusNum'] = '1';
    $params['ApiStatus.Status'] = 'Enabled';
    $params['ApiStatus.SwitchText'] = 'Disable';
    $params['ApiStatus.SwitchValue'] = 'disable';
    $params['ApiStatus.WarningVisibility'] = 'visible';
    $params['ApiStatus.ErrorVisibility'] = 'hidden';
    $params['ApiStatus.ServiceURL'] = SUCURISCAN_API_URL;

    if ($nonce) {
        // Enable or disable the API service communication.
        if ($api_service = SucuriScanRequest::post(':api_service', '(en|dis)able')) {
            $action_d = $api_service . 'd';
            $message = 'API service communication was <code>' . $action_d . '</code>';

            SucuriScanEvent::reportInfoEvent($message);
            SucuriScanEvent::notifyEvent('plugin_change', $message);
            SucuriScanOption::updateOption(':api_service', $action_d);
            SucuriScanInterface::info($message);
        }

        if ($api_handler = SucuriScanRequest::post(':api_handler')) {
            if (array_key_exists($api_handler, $sucuriscan_api_handlers)) {
                $message = 'API request handler set to <code>' . $api_handler . '</code>';

                SucuriScanOption::updateOption(':api_handler', $api_handler);
                SucuriScanEvent::reportWarningEvent($message);
                SucuriScanEvent::notifyEvent('plugin_change', $message);
                SucuriScanInterface::info($message);
            } else {
                SucuriScanInterface::error('Invalid value for the API request handler.');
            }
        }
    }

    $api_service = SucuriScanOption::getOption(':api_service');

    if ($api_service === 'disabled') {
        $params['ApiStatus.StatusNum'] = '0';
        $params['ApiStatus.Status'] = 'Disabled';
        $params['ApiStatus.SwitchText'] = 'Enable';
        $params['ApiStatus.SwitchValue'] = 'enable';
        $params['ApiStatus.WarningVisibility'] = 'hidden';
        $params['ApiStatus.ErrorVisibility'] = 'visible';
    }

    $api_handler = SucuriScanOption::getOption(':api_handler');
    $params['ApiHandlerOptions'] = SucuriScanTemplate::selectOptions(
        $sucuriscan_api_handlers,
        $api_handler
    );

    return SucuriScanTemplate::getSection('settings-apiservice-status', $params);
}

/**
 * Returns the HTML to configure the API service timeout.
 *
 * @param bool $nonce True if the CSRF protection worked, false otherwise.
 * @return string HTML for the API service timeout option.
 */
function sucuriscan_settings_apiservice_timeout($nonce)
{
    $params = array();

    // Update the API request timeout.
    if ($nonce) {
        $timeout = (int) SucuriScanRequest::post(':request_timeout', '[0-9]+');

        if ($timeout > 0) {
            if ($timeout <= SUCURISCAN_MAX_REQUEST_TIMEOUT) {
                $message = 'API request timeout set to <code>' . $timeout . '</code> seconds.';

                SucuriScanOption::updateOption(':request_timeout', $timeout);
                SucuriScanEvent::reportInfoEvent($message);
                SucuriScanEvent::notifyEvent('plugin_change', $message);
                SucuriScanInterface::info($message);
            } else {
                SucuriScanInterface::error('API request timeout in seconds is too high.');
            }
        }
    }

    $params['MaxRequestTimeout'] = SUCURISCAN_MAX_REQUEST_TIMEOUT;
    $params['RequestTimeout'] = SucuriScanOption::getOption(':request_timeout') . ' seconds';

    return SucuriScanTemplate::getSection('settings-apiservice-timeout', $params);
}

/**
 * Returns the HTML to configure the API service proxy.
 *
 * @return string HTML for the API service proxy option.
 */
function sucuriscan_settings_apiservice_proxy()
{
    $params = array(
        'APIProxy.Host' => 'no_proxy_host',
        'APIProxy.Port' => 'no_proxy_port',
        'APIProxy.Username' => 'no_proxy_username',
        'APIProxy.Password' => 'no_proxy_password',
        'APIProxy.PasswordType' => 'default',
        'APIProxy.PasswordText' => 'empty',
    );

    if (class_exists('WP_HTTP_Proxy')) {
        $wp_http_proxy = new WP_HTTP_Proxy();

        if ($wp_http_proxy->is_enabled()) {
            $proxy_host = SucuriScan::escape($wp_http_proxy->host());
            $proxy_port = SucuriScan::escape($wp_http_proxy->port());
            $proxy_username = SucuriScan::escape($wp_http_proxy->username());
            $proxy_password = SucuriScan::escape($wp_http_proxy->password());

            $params['APIProxy.Host'] = $proxy_host;
            $params['APIProxy.Port'] = $proxy_port;
            $params['APIProxy.Username'] = $proxy_username;
            $params['APIProxy.Password'] = $proxy_password;
            $params['APIProxy.PasswordType'] = 'info';
            $params['APIProxy.PasswordText'] = 'hidden';
        }
    }

    return SucuriScanTemplate::getSection('settings-apiservice-proxy', $params);
}
