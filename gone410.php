<?php
/*
Plugin Name: Gone 410 CSV
Description: Restituisce HTTP 410 per gli URL presenti in gone.csv
Version: 1.0
Author: Salvatore
*/

add_action('parse_request', function ($wp) {

    static $gone = null;

    $plugin_dir = plugin_dir_path(__FILE__);
    $csv_file   = $plugin_dir . 'gone.csv';
    $log_file   = $plugin_dir . 'gone-410.log';

    // Elimina il log dopo 24 ore
    if (file_exists($log_file) && filemtime($log_file) < (time() - DAY_IN_SECONDS)) {
        @unlink($log_file);
    }

    // Carica il CSV una sola volta
    if ($gone === null) {

        $gone = [];

        if (!file_exists($csv_file)) {
            return;
        }

        if (($handle = fopen($csv_file, 'r')) !== false) {

            // Salta l'intestazione
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {

                if (empty($row[0])) {
                    continue;
                }

                $path = trim(parse_url($row[0], PHP_URL_PATH), '/');

                if ($path !== '') {
                    $gone[$path] = true;
                }
            }

            fclose($handle);
        }
    }

    // $request = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

    $request = trim($wp->request, '/');

file_put_contents(
    __DIR__ . '/debug.txt',
    'REQUEST=[' . $request . ']' . PHP_EOL,
    FILE_APPEND
);

    error_log('REQUEST=[' . $request . ']');
    error_log(isset($gone[$request]) ? 'MATCH' : 'NO MATCH');

    if (!isset($gone[$request])) {
        return;
    }

    // Log
    @file_put_contents(
        $log_file,
        sprintf(
            "[%s] %s | %s | %s\n",
            date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? '-',
            $_SERVER['HTTP_USER_AGENT'] ?? '-',
            '/' . $request
        ),
        FILE_APPEND | LOCK_EX
    );

    status_header(410);
    nocache_headers();

    include __DIR__ . '/410.php';
    exit;

}, 0);