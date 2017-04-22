<?php

/**
 * Code related to the settings-scanner.php interface.
 *
 * @package Sucuri Security
 * @subpackage settings-scanner.php
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
 * Returns the HTML for the project scanner options.
 *
 * This method renders a section in the settings page that allows the website
 * owner to configure the functionality of the main file system scanner. This
 * includes the frequency in which such scanner will run and information about
 * the availability of the required dependencies.
 *
 * @return string HTML for the project scanner options.
 */
function sucuriscan_settings_scanner_options()
{
    global $sucuriscan_schedule_allowed;

    $params = array();

    if (SucuriScanInterface::checkNonce()) {
        // Modify the schedule of the filesystem scanner.
        if ($frequency = SucuriScanRequest::post(':scan_frequency')) {
            if (array_key_exists($frequency, $sucuriscan_schedule_allowed)) {
                SucuriScanOption::updateOption(':scan_frequency', $frequency);

                // Remove all the scheduled tasks associated to the plugin.
                wp_clear_scheduled_hook('sucuriscan_scheduled_scan');

                // Install new cronjob unless the user has selected "Never".
                if ($frequency !== '_oneoff') {
                    wp_schedule_event(time() + 10, $frequency, 'sucuriscan_scheduled_scan');
                }

                $frequency_title = strtolower($sucuriscan_schedule_allowed[ $frequency ]);
                $message = 'File system scanning frequency set to <code>' . $frequency_title . '</code>';

                SucuriScanEvent::reportInfoEvent($message);
                SucuriScanEvent::notifyEvent('plugin_change', $message);
                SucuriScanInterface::info($message);
            }
        }
    }

    $scan_freq = SucuriScanOption::getOption(':scan_frequency');

    // Generate the HTML code for the option list in the form select fields.
    $freq_options = SucuriScanTemplate::selectOptions($sucuriscan_schedule_allowed, $scan_freq);

    $params['ScanningFrequency'] = 'Undefined';
    $params['ScanningFrequencyOptions'] = $freq_options;

    $hasSPL = SucuriScanFileInfo::isSplAvailable();
    $params['NoSPL.Visibility'] = SucuriScanTemplate::visibility(!$hasSPL);

    if (array_key_exists($scan_freq, $sucuriscan_schedule_allowed)) {
        $params['ScanningFrequency'] = $sucuriscan_schedule_allowed[ $scan_freq ];
    }

    return SucuriScanTemplate::getSection('settings-scanner-options', $params);
}

/**
 * Returns a list of directories in the website.
 *
 * @return string List of directories in the website.
 */
function sucuriscan_settings_ignorescan_ajax()
{
    if (SucuriScanRequest::post('form_action') == 'get_ignored_files') {
        $response = '';

        // Scan the project and get the ignored paths.
        $ignored_dirs = SucuriScanFSScanner::getIgnoredDirectoriesLive();

        foreach ($ignored_dirs as $group => $dir_list) {
            foreach ($dir_list as $dir_data) {
                $valid_entry = false;
                $snippet = array(
                    'IgnoreScan.Directory' => '',
                    'IgnoreScan.DirectoryPath' => '',
                    'IgnoreScan.IgnoredAt' => '',
                    'IgnoreScan.IgnoredAtText' => 'ok',
                );

                if ($group == 'is_ignored') {
                    $valid_entry = true;
                    $snippet['IgnoreScan.Directory'] = urlencode($dir_data['directory_path']);
                    $snippet['IgnoreScan.DirectoryPath'] = $dir_data['directory_path'];
                    $snippet['IgnoreScan.IgnoredAt'] = SucuriScan::datetime($dir_data['ignored_at']);
                    $snippet['IgnoreScan.IgnoredAtText'] = 'ignored';
                } elseif ($group == 'is_not_ignored') {
                    $valid_entry = true;
                    $snippet['IgnoreScan.Directory'] = urlencode($dir_data);
                    $snippet['IgnoreScan.DirectoryPath'] = $dir_data;
                }

                if ($valid_entry) {
                    $response .= SucuriScanTemplate::getSnippet('settings-scanner-ignore-folders', $snippet);
                }
            }
        }

        print($response);
        exit(0);
    }
}

/**
 * Returns the HTML for the folder scanner skipper.
 *
 * If the website has too many files it would be wise to force the plugin to
 * ignore some directories that are not relevant for the scanner. This includes
 * directories with media files like images, audio, videos, etc and directories
 * used to store cache data.
 *
 * @param bool $nonce True if the CSRF protection worked, false otherwise.
 * @return string HTML for the folder scanner skipper.
 */
function sucuriscan_settings_scanner_ignore_folders($nonce)
{
    $params = array();

    if ($nonce) {
        // Ignore a new directory path for the file system scans.
        if ($action = SucuriScanRequest::post(':ignorescanning_action', '(ignore|unignore)')) {
            $ign_dirs = SucuriScanRequest::post(':ignorescanning_dirs', '_array');
            $ign_file = SucuriScanRequest::post(':ignorescanning_file');

            if ($action == 'ignore') {
                // Target a single file path to be ignored.
                if ($ign_file !== false) {
                    $ign_dirs = array($ign_file);
                    unset($_POST['sucuriscan_ignorescanning_file']);
                }

                // Target a list of directories to be ignored.
                if (is_array($ign_dirs) && !empty($ign_dirs)) {
                    $were_ignored = array();

                    foreach ($ign_dirs as $resource_path) {
                        if (file_exists($resource_path)
                            && SucuriScanFSScanner::ignoreDirectory($resource_path)
                        ) {
                            $were_ignored[] = $resource_path;
                        }
                    }

                    if (!empty($were_ignored)) {
                        SucuriScanInterface::info('Items selected will be ignored in future scans.');
                        SucuriScanEvent::reportWarningEvent(sprintf(
                            'Resources will not be scanned: (multiple entries): %s',
                            @implode(',', $ign_dirs)
                        ));
                    }
                }
            } elseif ($action == 'unignore'
                && is_array($ign_dirs)
                && !empty($ign_dirs)
            ) {
                foreach ($ign_dirs as $directory_path) {
                    SucuriScanFSScanner::unignoreDirectory($directory_path);
                }

                SucuriScanInterface::info('Items selected will not be ignored anymore.');
                SucuriScanEvent::reportNoticeEvent(sprintf(
                    'Resources will be scanned: (multiple entries): %s',
                    @implode(',', $ign_dirs)
                ));
            }
        }
    }

    return SucuriScanTemplate::getSection('settings-scanner-ignore-folders', $params);
}
