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

    class Sosumi
    {
        public $authenticated; // True if we logged in successfully
        public $devices;   // An array of all devices on this MobileMe account
        private $lastURL;  // The previous URL as visited by curl
        private $tmpFile;  // Where we store our cookies
        private $lsc;      // Associative array of Apple auth tokens
		private $username;

        public function __construct($mobile_me_username, $mobile_me_password)
        {
            $this->tmpFile = tempnam('/tmp', 'sosumi');
            $this->lsc     = array();
            $this->devices = array();
            $this->authenticated = false;
			$this->username = $mobile_me_username;

            // Load the HTML login page and also get the init cookies set
	        $html = $this->curlGet('https://auth.me.com/authenticate?service=findmyiphone&ssoNamespace=primary-me&reauthorize=Y&returnURL=aHR0cHM6Ly9zZWN1cmUubWUuY29tL2ZpbmQv&anchor=findmyiphone', 'https://secure.me.com/find/');

            // Parse out the hidden fields
            preg_match_all('!hidden.*?name=["\'](.*?)["\'].*?value=["\'](.*?)["\']!ms', $html, $hidden);

            // Build the form post data
            $post = '';
            for($i = 0; $i < count($hidden[1]); $i++)
                $post .= $hidden[1][$i] . '=' . urlencode($hidden[2][$i]) . '&';
            $post  .= 'service=findmyiphone&username=' . urlencode($mobile_me_username) . '&password=' . urlencode($mobile_me_password);

            // Login
            $action_url = $this->match('!action=["\'](.*?)["\']!ms', $html, 1);
            $html = $this->curlPost('https://auth.me.com/authenticate', $post, $this->lastURL);
            $html = $this->curlGet('https://secure.me.com/find/', $this->lastURL);

            $headers = array(
                "X-Requested-With: XMLHttpRequest",
                "X-SproutCore-Version: 1.0",
                "X-Mobileme-Version: 1.0",
                "X-Inactive-Time: 1187",
                );

            $html = $this->curlPost('https://secure.me.com/fmipservice/client/initClient', '{"clientContext":{"appName":"MobileMe Find (Web)","appVersion":"1.0"}}', $this->lastURL, $headers);

            if(count($this->lsc) > 0)
            {
                $this->authenticated = true;
                $this->getDevices();
            }
        }

        public function __destruct()
        {
            if(file_exists($this->tmpFile))
                unlink($this->tmpFile);
        }

        public function locate($device_number = 0)
        {
			return $this->devices[$device_number]->location;
        }

        // Send a message to the device with an optional alarm sound
        public function sendMessage($msg, $alarm = false, $device_number = 0, $subject = 'Important Message')
        {
            $the_device = $this->devices[$device_number];

			$json = sprintf('{"device":"%s","text":"%s","sound":%s,"subject":"%s","serverContext":{"prefsUpdateTime":1276872996660,"timezone":{"tzCurrentName":"Pacific Daylight Time","previousTransition":1268560799999,"previousOffset":-28800000,"currentOffset":-25200000,"tzName":"America/Los_Angeles"},"callbackIntervalInMS":10000,"maxDeviceLoadTime":60000,"validRegion":true,"maxLocatingTime":90000,"hasDevices":true,"sessionLifespan":900000,"deviceLoadStatus":200,"clientId":"","lastSessionExtensionTime":"1276872334744_1276873014045","preferredLanguage":"en","id":"server_ctx"},"clientContext":{"appName":"MobileMe Find (Web)","appVersion":"1.0"}}',
			                $the_device->id, $msg, $alarm ? 'true' : 'false', $subject);

            $headers = array('Content-Type: application/json: charset=UTF-8',
                             'X-Inactive-Time: 1187',
                             'X-Requested-With: XMLHttpRequest',
                             'X-Prototype-Version: 1.6.0.3',
                             'Content-Type: application/json; charset=UTF-8',
                             'X-Mobileme-User: ' . $this->username,
                             'X-Mobileme-Version: 1.0',
                             'X-Sproutcore-Version: 1.0',
                             'X-Mobileme-Isc: ' . $this->lsc['secure.me.com']);

            $html = $this->curlPost('https://secure.me.com/fmipservice/client/sendMessage', $json, 'https://secure.me.com/find/', $headers);

            $json = json_decode(array_pop(explode("\n", $html)));
            return ($json !== false) && isset($json->statusString) && ($json->statusString == 'message sent');
        }

        public function remoteWipe()
        {
            // Remotely wiping a device is an exercise best
            // left to the reader.
        }

        private function getDevices()
        {
            $headers = array('Accept: text/javascript, text/html, application/xml, text/xml, */*',
                             'X-Requested-With: XMLHttpRequest',
                             'X-Mobileme-Version: 1.0',
                             'X-SproutCore-Version: 1.0',
                             'X-Mobileme-Isc: ' . $this->lsc['secure.me.com']);
            $html = $this->curlPost('https://secure.me.com/fmipservice/client/refreshClient', null, 'https://secure.me.com/find/', $headers, false);

            // Convert the raw json into an json object
            $json = json_decode($html);

            // Grab all of the devices
            $this->devices = $json->content;
        }

        private function curlGet($url, $referer = null, $headers = null)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->tmpFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->tmpFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
            if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
            if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            curl_setopt($ch, CURLOPT_HEADER, true);
            // curl_setopt($ch, CURLOPT_VERBOSE, true);

            $html = curl_exec($ch);

            if(curl_errno($ch) != 0)
            {
                throw new Exception("Error during GET of '$url': " . curl_error($ch));
            }

            $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

            preg_match_all('/[li]sc-(.*?)=([a-f0-9]+);/i', $html, $matches);
            for($i = 0; $i < count($matches[0]); $i++)
                $this->lsc[$matches[1][$i]] = $matches[2][$i];

            return $html;
        }

        private function curlPost($url, $post_vars = null, $referer = null, $headers = null, $return_headers = true)
        {
            if(is_null($post_vars))
                $post_vars = '';

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->tmpFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->tmpFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
            if(!is_null($referer)) curl_setopt($ch, CURLOPT_REFERER, $referer);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
            if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            if($return_headers) curl_setopt($ch, CURLOPT_HEADER, true);
            // curl_setopt($ch, CURLOPT_VERBOSE, true);

            $html = curl_exec($ch);

            if(curl_errno($ch) != 0)
            {
                throw new Exception("Error during POST of '$url': " . curl_error($ch));
            }

            $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

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
