<?php

/**
 * Simwood API class
 *
 * Copyright (c) 2010 Ca-Phun Ung <caphun at yelotofu dot com>
 *
 * Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software and associated documentation
 * files (the "Software"), to deal in the Software without
 * restriction, including without limitation the rights to use,
 * copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following
 * conditions:
 * 
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
 * OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
 * OTHER DEALINGS IN THE SOFTWARE.
 * 
 * @version v0.0.1
 */

class Simwood {

    var $options = array();
    var $request = array();
    var $response = array();

    // singleton pattern
    private static $instance;
    public function get_instance($options = array()) {
        if (!self::$instance) self::$instance = new Simwood($options);
        return self::$instance;     
    }

    // constructor (private)
    private function __construct($options = array()) {
        
        $this->options = array_merge(array(
            'api_url' => "http://ws.simwood.com/REST.php",
            'threshold' => 3600 * 24, // 1 day
            'user' => null,
            'password' => null,
            'output' => 'xml',
        ), $options);
        
        return $this;
    }
    
    // get
    function get($mode, $options = array()) {
        // build request queque
        $this->request[] = array('url' => "{$this->options['api_url']}?mode={$mode}", 'params' => $options);
        // return self
        return $this;
    }
    
    // executes all requests and spits out the result in a concatinated object literal
    function run() {
        
        // TODO: authenticate first
        // get token from session
        $token = $this->get_auth_token();
        
        if ($token) {
            // loop through queque to execute each request
            foreach ($this->request as $request) {
                $this->request(
                    $request['url'], 
                    array_merge(
                        $request['params'], 
                        array(
                            'token' => $token,
                            'output' => $this->options['output']
                        )
                    )
                );
            }
            
            // write response to output
            $output = $this->response;
        } else {
            $output = array(
                'ERROR' => 'Could not authenticate you',
            );
        }
        
        unset($this->request); // clear request array
        unset($this->response); // clear response array
        
        // output results
        return $output;
    }
    
    // authentication
    function get_auth_token() {
        if (!isset($_SESSION['token'])) {
    
            $key = $this->get_auth_key();
            $expiry = $this->response['TIME']->results->timestamp + $this->options['threshold'];

            // authenticate
            $this->request("{$this->options['api_url']}?mode=AUTH", array(
              'user'=> $this->options['user'],
              'expiry' => $expiry,
              'key' => $key,
              'output'=> 'json',
            ));
            $response = $this->response['AUTH'];
            
            // TODO: check whether authentication is valid before returning a token
            if ($response->status == 1) {
                $token = $response->results->token;
                // save token to session
                $_SESSION['token'] = $token;
            } else {
                $token = null;
            }
        } else {
            $token = $_SESSION['token'];
        }
        
        // return auth token
        return $token;
    }
    
    // revoke authentication token
    function revoke_auth_token() {
        // get token from session
        $token = isset($_SESSION['token']) ? $_SESSION['token'] : null;
        if ($token) {
            // revoke token
            $this->request("{$this->options['api_url']}?mode=DEAUTH", array(
              'user' => $this->options['user'],
              'token' => $token,
              'key' => $this->get_deauth_key(),
              'output' => 'json',
            ));
        }

        unset($_SESSION['token']);
        
        return $this;
    }

    // get_auth_key
    function get_auth_key() {

        $clientip = $this->get_my_ip();
        $expiry = $this->get_timestamp() + $this->options['threshold'];

        return htmlspecialchars(sha1($clientip.$expiry.$this->options['password']));
    }
    
    // get_deauth_key
    function get_deauth_key() {
        $clientip = $this->get_my_ip();
        $token = isset($_SESSION['token']) ? $_SESSION['token'] : null;
        return htmlspecialchars(sha1($clientip.$token.$this->options['password']));
    }
    
    // get_my_ip
    function get_my_ip() {
        // get client ip
        $this->request("{$this->options['api_url']}?mode=MYIP", array('output'=> 'json',));
        return $this->response['MYIP']->results->ip;
    }

    // get_timestamp
    function get_timestamp() {
        $this->request("{$this->options['api_url']}?mode=TIME", array('output'=> 'json',));
        return $this->response['TIME']->results->timestamp;
    }
    
    // request - make curl request
    function request($url, $options = null) {
        $ch = curl_init($url);
        curl_setopt ($ch, CURLOPT_POST, 1);
        if ($options != null) {
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $options); 
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec ($ch);
        //$info = curl_getinfo($ch);
        //$result['http_code'];
        curl_close ($ch);

        // get mode
        $parts = parse_url($url);
        parse_str($parts['query'], $params);
        $mode = $params['mode'];

        // write response into response array
        $this->response[$mode] = isset($options['output']) && $options['output'] === 'json' ? json_decode($result) : $result;
        
        // return self
        return $this;
    }
    
}