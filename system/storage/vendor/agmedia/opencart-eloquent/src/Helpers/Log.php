<?php
/**
 * User: fj.agmedia.hr
 * Date: 16/06/2017
 * Time: 14:36
 */

namespace Agmedia\Helpers;

class Log
{
    /**
     * @param        $message
     * @param string $filename
     */
    public static function error($message, $filename = 'error_')
    {
        return self::store($message, $filename);
    }

    /**
     * @param        $message
     * @param string $filename
     */
    public static function warning($message, $filename = 'warning_')
    {
        return self::store($message, $filename);
    }

    /**
     * @param        $message
     * @param string $filename
     */
    public static function notice($message, $filename = 'notice_')
    {
        return self::store($message, $filename);
    }

    /**
     * @param        $message
     * @param string $filename
     */
    public static function info($message, $filename = 'info_')
    {
        return self::store($message, $filename);
    }

    /**
     * @param        $message
     * @param string $filename
     */
    public static function debug($message, $filename = 'debug_')
    {
        return self::store($message, $filename);
    }

    /**
     * Log messages.
     *
     * @param        $message
     * @param string $filename  Base naziv fajla bez ekstenzije (npr. 'tabs' ili 'error_')
     */
    public static function store($message, $filename)
    {
        $year  = date('Y');
        $month = date('m');
        $day   = date('d');

        // Osnovna log lokacija iz OpenCart konfiguracije.
        $basePath = rtrim(DIR_LOGS, '/\\');

        // Kreiraj dnevnu strukturu: /logs/YYYY/MM/DD
        $dir = $basePath . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month . DIRECTORY_SEPARATOR . $day;

        // Rekurzivno kreiranje direktorija (sigurnije i jednostavnije)
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                // Ako i dalje ne postoji, baci iznimku
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }

        // Sanitiziraj naziv fajla (ukloni slash-eve i kontrolne znakove)
        $safeFilename = preg_replace('/[\/\\\\\x00-\x1F]+/', '_', (string) $filename);
        if ($safeFilename === '' || $safeFilename === null) {
            $safeFilename = 'log_';
        }

        $filePath = $dir . DIRECTORY_SEPARATOR . $safeFilename . '.log';

        // Otvori (ili kreiraj) log fajl u append modu
        $handle = @fopen($filePath, 'a');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open log file: %s', $filePath));
        }

        // Upis poruke
        $line = date('Y-m-d H:i:s') . ' - ' . print_r($message, true) . PHP_EOL;
        fwrite($handle, $line);
        fclose($handle);
    }

    /**
     * Spremi u jedan "plain" log fajl u rootu DIR_LOGS (bez dnevne strukture).
     *
     * @param        $message
     * @param string $filename  Base naziv fajla bez ekstenzije (default: 'info')
     */
    public static function stockPile($message, $filename = 'info')
    {
        $basePath = rtrim(DIR_LOGS, '/\\');

        // Pobrinemo se da postoji bazni direktorij (obično postoji, ali za svaki slučaj)
        if (!is_dir($basePath)) {
            if (!mkdir($basePath, 0755, true) && !is_dir($basePath)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $basePath));
            }
        }

        $safeFilename = preg_replace('/[\/\\\\\x00-\x1F]+/', '_', (string) $filename);
        if ($safeFilename === '' || $safeFilename === null) {
            $safeFilename = 'info';
        }

        $filePath = $basePath . DIRECTORY_SEPARATOR . $safeFilename . '.log';

        $handle = @fopen($filePath, 'a');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open log file: %s', $filePath));
        }

        fwrite($handle, date('Y-m-d H:i:s') . ' - ' . print_r($message, true) . PHP_EOL);
        fclose($handle);
    }
}
