<?php

/**
 * @package Auto-Install Free SSL
 * This package is a WordPress Plugin. It issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 *
 * @author Free SSL Dot Tech <support@freessl.tech>
 * @copyright  Copyright (C) 2019-2020, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://freessl.tech
 * @since      Class available since Release 1.0.0
 *
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */
namespace AutoInstallFreeSSL\FreeSSLAuto;

use AutoInstallFreeSSL\FreeSSLAuto\Admin\Factory;
use DateTime;
//you can use any logger according to Psr\Log\LoggerInterface
class Logger {
    /**
     *
     *
     * @param $message
     */
    public function log( $message ) {
        $this->write_log( 'info', $message, [
            'event' => 'ping',
        ] );
        /*try {
                    $this->write_log('info', $message, ['event' => 'ping']);
                }
                catch (RuntimeException $e){
                    $message = print_r($e, true);
        
                    $this->display_log('error', $message, ['event' => 'exit']);
        
                    /*else{
                        $this->log_v2('error', $message, ['event' => 'exit']);
                    }*/
        //}*/
    }

    /**
     *
     *
     * @param $level
     * @param $message
     * @param string[] $context
     */
    public function log_v2( $level, $message, $context = [
        'event' => 'ping',
    ] ) {
        $this->write_log( $level, $message, $context );
    }

    /**
     *
     *
     * Write log in file
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function write_log( $level, $message, $context ) {
        //get log file name as per date
        $log_directory = AIFS_UPLOAD_DIR . DS . 'log';
        if ( !is_dir( $log_directory ) ) {
            @mkdir( $log_directory, 0700, true );
        }
        if ( !is_dir( $log_directory ) ) {
            //throw new \RuntimeException("Can't create directory '$log_directory'. Please manually create this directory, set permission 0755 and try again.");
            /*$this->exception_sse_friendly(
              /* translators: %s: A directory path */
            /* sprintf(__( "Can't create directory '%s'. Please manually create this directory, set permission 0755, and try again.", 'auto-install-free-ssl' ), $log_directory),
                __FILE__,
                __LINE__,
                false
               );*/
            $this->exception_sse_friendly(
                /* translators: %s: A directory path */
                sprintf( "Can't create directory '%s'. Please manually create this directory, set permission 0755, and try again.", $log_directory ),
                __FILE__,
                __LINE__,
                false
            );
            //since 3.6.1, Don't translate exception message.
        }
        /*if(!file_exists($log_directory . DS . ".htaccess")){
              if(!file_put_contents($log_directory . DS . ".htaccess", "Order deny,allow\nDeny from all")){
                  $exp_text = "Can't create a .htaccess file in the directory '".$log_directory."'. Please manually create it, and paste the following code in it. \n\nOrder deny,allow\nDeny from all";
                  //throw new \RuntimeException("Can't create a .htaccess file in the directory '".$log_directory."'. Please manually create it, and paste the following code in it. \n\nOrder deny,allow\nDeny from all \n\n");
                  $this->exception_sse_friendly($exp_text, __FILE__, __LINE__, false);
              }
          }*/
        $factory = new Factory();
        $factory->create_security_files( $log_directory );
        $filename = ( function_exists( 'wp_date' ) ? wp_date( 'Y-m-d' ) . '.log' : date( 'Y-m-d' ) . '.log' );
        $handle = fopen( $log_directory . DS . $filename, 'a' );
        $log_text = wp_kses_post( current_time( 'mysql' ) . " [{$level}] {$message}" ) . "\n\n";
        if ( $context['event'] == 'exit' ) {
            $log_text .= "-----------------------------------------------------------------------------------------------------------------------------------------------------------------\n\n";
        }
        fwrite( $handle, $log_text );
        // htmlspecialchars($log_text);
        fclose( $handle );
        //$this->clean_log_directory();
    }

    /**
     *
     *
     * @param string $exp_text
     * @param string $file_name
     * @param integer$line_number
     * @param bool $write_in_log_file
     */
    public function exception_sse_friendly(
        $exp_text,
        $file_name,
        $line_number,
        $write_in_log_file = true
    ) {
        //$exp_text .= "\n\n". __( "File:", 'auto-install-free-ssl' ) ." ".$file_name . "\n". __( "Line number:", 'auto-install-free-ssl' ) ." ". $line_number . "\n\n";
        $exp_text .= "\n\n File: " . $file_name . "\n Line number: " . $line_number . "\n\n";
        //since 3.6.1, Don't translate exception message
        if ( $this->is_cli() ) {
            if ( $write_in_log_file ) {
                //$this->write_log('error', current_time('mysql')." [error] ". $exp_text ."\n\n". __( "Connection closed", 'auto-install-free-ssl' ) ."\n", ['event' => 'exit']);
                $this->write_log( 'error', current_time( 'mysql' ) . " [error] " . $exp_text . "\n\n Connection closed \n", [
                    'event' => 'exit',
                ] );
                //since 3.6.1, Don't translate exception message
            }
            throw new \RuntimeException($exp_text);
            /*echo current_time('mysql')." [error] ".$exp_text;
              die("Connection closed\n");*/
        } else {
            $this->log_v2( 'error', str_replace( "\n", "<br />", $exp_text ), [
                'event' => 'exit',
            ] );
        }
    }

    /**
     *
     *
     * Delete log files older than 45 days
     */
    /*public function clean_log_directory_v0(){
    
            $log_directory = AIFS_UPLOAD_DIR . DS . 'log' . DS;
            $retain_files = 90; //Previous value was 45
    
            $files = glob($log_directory.'*', GLOB_MARK);
    	    $files = array_values(array_diff($files, array($log_directory . "web.config"))); //remove web.config and re-index array
    
            foreach ($files as $file){
                $date = str_replace('.log', '', basename($file));
                $file_date = new DateTime($date);
                $today_date = function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d');
                $today = new DateTime($today_date);
                $interval = (int)$file_date->diff($today)->format('%R%a');
    
                if($interval > $retain_files){
                    unlink($file);
                }
            }
        }*/
    /**
     * Delete the oldest log files keeping the latest 90 log files.
     */
    public function clean_log_directory() {
        $log_directory = AIFS_UPLOAD_DIR . DS . 'log' . DS;
        $retain_files = 90;
        $files = glob( $log_directory . '*.log' );
        $file_count = count( $files );
        if ( $file_count > $retain_files ) {
            // Sort the files by creation date in descending order
            usort( $files, function ( $a, $b ) {
                $aDate = str_replace( '.log', '', basename( $a ) );
                $bDate = str_replace( '.log', '', basename( $b ) );
                return strtotime( $bDate ) - strtotime( $aDate );
            } );
            // Delete the extra files beyond the retention limit
            for ($i = $retain_files; $i < $file_count; $i++) {
                unlink( $files[$i] );
            }
        }
    }

    /**
     * Check if the script is running from CLI/cron or browser.
     * Improved since 3.6.0
     * @return boolean
     */
    public function is_cli() {
        if ( defined( 'STDIN' ) ) {
            return true;
        }
        if ( php_sapi_name() === 'cli' ) {
            return true;
        }
        if ( !isset( $_SERVER['HTTP_USER_AGENT'] ) && !isset( $_SERVER['SERVER_ADDR'] ) && !isset( $_SERVER['REMOTE_ADDR'] ) && !isset( $_SERVER['HTTP_HOST'] ) && !isset( $_SERVER['REQUEST_METHOD'] ) ) {
            return true;
        }
        /*if(isset($_SERVER['SHELL']) && strpos($_SERVER['SHELL'], '/bin/bash') !== false){
           return true;
          }*/
        if ( !isset( $_SERVER['SERVER_SOFTWARE'] ) || empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
            return true;
        }
        /*if(isset($_SERVER['PWD'])){
           return true;
          }*/
        return false;
    }

    /**
     *
     *
     * Check if running from CLI/cron or browser
     *
     * @return boolean
     */
    public function is_cli_v0() {
        if ( defined( 'STDIN' ) ) {
            return true;
        }
        if ( empty( $_SERVER['REMOTE_ADDR'] ) && !isset( $_SERVER['HTTP_USER_AGENT'] ) && is_array( $_SERVER['argv'] ) && count( $_SERVER['argv'] ) > 0 ) {
            return true;
        }
        return false;
    }

}
