<?PHP

    // Sosumi - a Find My iPhone web scraper.
    //
    // June 22, 2009
    // Tyler Hall <tylerhall@gmail.com>
    // http://github.com/tylerhall/sosumi/tree/master
    //
    // Usage:
    // $ssm = new Sosumi('username', 'password');
    // $location_info = $ssm->locate();
    // $ssm->sendMessage('Daisy, daisy...');
    //
    // TODO: Need to see how many HTTP requests we can remove. The current
    // implementation hasn't been minified yet.

    class Sosumi
    {
        public $devices;   // An array of all devices on this MobileMe account
        private $lastURL;  // The previous URL as visited by curl
        private $tmpFile;  // Where we store our cookies
        private $lsc;      // Associative array of Apple auth tokens
        private $deviceId; // The device ID to ping

        public function __construct($mobile_me_username, $mobile_me_password)
        {
            $this->tmpFile = tempnam('/tmp', 'sosumi');
            $this->lsc     = array();
            $this->devices = array();

            // Load the HTML login page and also get the init cookies set
            $html = $this->curlGet("https://auth.me.com/authenticate?service=account&ssoNamespace=primary-me&reauthorize=Y&returnURL=aHR0cHM6Ly9zZWN1cmUubWUuY29tL2FjY291bnQvI2ZpbmRteWlwaG9uZQ==&anchor=findmyiphone");

            // Parse out the hidden fields
            preg_match_all('!hidden.*?name=["\'](.*?)["\'].*?value=["\'](.*?)["\']!ms', $html, $hidden);

            // Build the form post data
            $post = '';
            for($i = 0; $i < count($hidden[1]); $i++)
                $post .= $hidden[1][$i] . '=' . urlencode($hidden[2][$i]) . '&';
            $post  .= 'username=' . urlencode($mobile_me_username) . '&password=' . urlencode($mobile_me_password);

            // Login
            $action_url = $this->match('!action=["\'](.*?)["\']!ms', $html, 1);
            $html = $this->curlPost('https://auth.me.com/authenticate', $post, $this->lastURL);
            $html = $this->curlGet('https://secure.me.com/account/', $this->lastURL);

            $headers = array('X-Mobileme-Version: 1.0');
            $html = $this->curlGet('https://secure.me.com/wo/WebObjects/Account2.woa?lang=en&anchor=findmyiphone', $this->lastURL, $headers);

            $this->getDevices();
	}

        public function __destruct() {
                if (file_exists($this->tmpFile))
                {
                        unlink($this->tmpFile);
                }
        }

        // Return a stdClass object of location information. Example...
        // stdClass Object
        // (
        //     [isLocationAvailable] => 1
        //     [longitude] => -121.010392
        //     [accuracy] => 47.421634
        //     [time] => 9:24 PM
        //     [isOldLocationResult] => 1
        //     [isRecent] => 1
        //     [statusString] => locate status available
        //     [status] => 1
        //     [isLocateFinished] =>
        //     [latitude] => 38.319117
        //     [date] => June 22, 2009
        //     [isAccurate] =>
        // )
        public function locate($the_device = null)
        {
            // Grab the first device is none is specified
            if(is_null($the_device))
            {
                reset($this->devices);
                $the_device = current($this->devices);
            }

            $arr = array('deviceId' => $the_device['deviceId'], 'deviceOsVersion' => $the_device['deviceOsVersion']);

            $post = 'postBody=' . json_encode($arr);

            $headers = array('Accept: text/javascript, text/html, application/xml, text/xml, */*',
                             'X-Requested-With: XMLHttpRequest',
                             'X-Prototype-Version: 1.6.0.3',
                             'Content-Type: application/json; charset=UTF-8',
                             'X-Mobileme-Version: 1.0',
                             'X-Mobileme-Isc: ' . $this->lsc['secure.me.com']);
            $html = $this->curlPost('https://secure.me.com/wo/WebObjects/DeviceMgmt.woa/wa/LocateAction/locateStatus', $post, 'https://secure.me.com/account/', $headers);
            $json = json_decode(array_pop(explode("\n", $html)));
            return $json;
        }

        // Send a message to the device with an optional alarm sound
        public function sendMessage($msg, $alarm = false, $the_device = null)
        {
            // Grab the first device is none is specified
            if(is_null($the_device))
            {
                reset($this->devices);
                $the_device = current($this->devices);
            }

            $arr = array('deviceId' => $the_device['deviceId'],
                         'message' => $msg,
                         'playAlarm' => $alarm ? 'Y' : 'N',
                         'deviceType' => $the_device['deviceType'],
                         'deviceClass' => $the_device['deviceClass'],
                         'deviceOsVersion' => $the_device['deviceOsVersion']);

            $post = 'postBody=' . json_encode($arr);

            $headers = array('Accept: text/javascript, text/html, application/xml, text/xml, */*',
                             'X-Requested-With: XMLHttpRequest',
                             'X-Prototype-Version: 1.6.0.3',
                             'Content-Type: application/json; charset=UTF-8',
                             'X-Mobileme-Version: 1.0',
                             'X-Mobileme-Isc: ' . $this->lsc['secure.me.com']);

            $html = $this->curlPost('https://secure.me.com/wo/WebObjects/DeviceMgmt.woa/wa/SendMessageAction/sendMessage', $post, 'https://secure.me.com/account/', $headers);

            $json = json_decode(array_pop(explode("\n", $html)));
            return ($json !== false) && isset($json->statusString) && ($json->statusString == 'message sent');
        }

        public function remoteWipe()
        {
            // Remotely wiping a device is an exercise best
            // left to the reader.
        }

        // Grab the details for each device on the MobileMe account
        // (We could also use this opportunity to parse out the last know lat/lng of the device
        // and save a couple round trips in the future.)
        private function getDevices()
        {
            $headers = array('Accept: text/javascript, text/html, application/xml, text/xml, */*',
                             'X-Requested-With: XMLHttpRequest',
                             'X-Prototype-Version: 1.6.0.3',
                             'X-Mobileme-Version: 1.0',
                             'X-Mobileme-Isc: ' . $this->lsc['secure.me.com']);
            $html = $this->curlPost('https://secure.me.com/device_mgmt/en', null, 'https://secure.me.com/account/', $headers);

            $headers = array('Accept: text/javascript, text/html, application/xml, text/xml, */*',
                             'X-Requested-With: XMLHttpRequest',
                             'X-Prototype-Version: 1.6.0.3',
                             'X-Mobileme-Version: 1.0',
                             'X-Mobileme-Isc: ' . $this->lsc['secure.me.com']);
            $html = $this->curlPost('https://secure.me.com/wo/WebObjects/DeviceMgmt.woa/?lang=en', null, 'https://secure.me.com/account/', $headers);

            // Grab all of the devices
            preg_match_all('/new Device\((.*?)\)/ms', $html, $matches);
            for($i = 0; $i < count($matches[0]); $i++)
            {
                $values = str_replace("'", '', $matches[1][$i]);
                list($unknown, $id, $type, $class, $os) = explode(',', $values);
                $this->devices[$id] = array('deviceId' => $id, 'deviceType' => $type, 'deviceClass' => $class, 'deviceOsVersion' => $os);
            }
        }

        private function curlGet($url, $referer = null, $headers = null)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->tmpFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->tmpFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
            if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
            if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_HEADER, true);
            // curl_setopt($ch, CURLOPT_VERBOSE, true);

            $html = curl_exec($ch);

            if (curl_errno($ch) != 0)
            {
                  echo "error during get of '$url': " . curl_error($ch) . "\n";
                  exit();
            }

            $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            preg_match_all('/[li]sc-(.*?)=([a-f0-9]+);/i', $html, $matches);
            for($i = 0; $i < count($matches[0]); $i++)
                $this->lsc[$matches[1][$i]] = $matches[2][$i];

            return $html;
        }

        private function curlPost($url, $post_vars = null, $referer = null, $headers = null)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->tmpFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->tmpFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
            if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
            curl_setopt($ch, CURLOPT_POST, true);
            if(!is_null($post_vars)) curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
            if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_HEADER, true);
            // curl_setopt($ch, CURLOPT_VERBOSE, true);

            $html = curl_exec($ch);
            $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            // preg_match_all('/Set-Cookie:(.*)/i', $html, $matches);
            preg_match_all('/[li]sc-(.*?)=([a-f0-9]+);/i', $html, $matches);
            for($i = 0; $i < count($matches[0]); $i++)
                $this->lsc[$matches[1][$i]] = $matches[2][$i];

            return $html;
        }

        private function match($regex, $str, $i = 0)
        {
            return preg_match($regex, $str, $match) == 1 ? $match[$i] : false;
        }
    }
?>
