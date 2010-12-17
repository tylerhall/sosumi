<?PHP
    // Sosumi - a PHP client for Apple's Find My iPhone web service
    //
    // June 20, 2010
    // Tyler Hall <tylerhall@gmail.com>
    // http://github.com/tylerhall/sosumi/tree/master
    //
    // Usage:
    // $ssm = new Sosumi('username', 'password');
    // $location_info = $ssm->locate(<device number>);
    // $ssm->sendMessage(<device number>, 'Your Subject', 'Your Message');
    //

    class Sosumi
    {
        public $devices;
        public $debug;
        private $username;
        private $password;
        private $inactiveTime;
        private $nextPollTime;
        private $lastStatus;
        private $lastURL;
        private $cookieFile;

        public function __construct($mobile_me_username, $mobile_me_password, $debug = false)
        {
            // Set the properties
            $this->devices  = array();
            $this->debug    = $debug;
            $this->username = $mobile_me_username;
            $this->password = $mobile_me_password;
            $this->inactiveTime = 0;
            $this->nextPollTime = time();
            $this->clientContext = array(
                "appName" => "MobileMe Find (Web)",
                "appVersion" => "1.0",
                );

            // Login to MobileMe
            $this->cookieFile = tempnam('/tmp', 'sosumi');
            $this->lsc     = array();
            $this->devices = array();
            $this->authenticated = false;
            $this->username = $mobile_me_username;

            // Load the HTML login page and also get the init cookies set
            $html = $this->curlGet('https://auth.me.com/authenticate?service=findmyiphone&ssoNamespace=appleid&formID=loginForm&returnURL=aHR0cHM6Ly9tZS5jb20vZmluZC8=&anchor=findmyiphone&lang=en', $this->lastURL);

            // Parse out the hidden fields
            preg_match_all('!hidden.*?name=["\'](.*?)["\'].*?value=["\'](.*?)["\']!ms', $html, $hidden);

            // Build the form post data
            $post = '';
            for($i = 0; $i < count($hidden[1]); $i++)
                $post .= $hidden[1][$i] . '=' . urlencode($hidden[2][$i]) . '&';
            $post  .= 'service=findmyiphone&username=' . urlencode($mobile_me_username) . '&password=' . urlencode($mobile_me_password);

            // Login
            $action_url = $this->match('!action=["\'](.*?)["\']!ms', $html, 1);
            $html = $this->curlPost($action_url, $post, $this->lastURL);

            if ($this->lastStatus != "200")
            {
                throw new Exception("Unable to login to MobileMe");
            }

            // Obtain the partition value from the cookie store
            $this->partition = $this->match('/nc-partition\s+(p\d+)/', file_get_contents($this->cookieFile), 1);

            $this->updateDevices();

            // Obtain the ISC value from the cookie store
            $this->isc = $this->match('!lsc-findmyiphone\s+(.*?)\s+!ms', file_get_contents($this->cookieFile), 1);
        }

        public function locate($device_num = 0, $max_wait = 300)
        {
            $start = time();

            // Loop until the device has been located...
            while($this->devices[$device_num]->deviceStatus != 200 && $this->devices[$device_num]->isLocating == true)
            {
                $this->iflog('Waiting for location... (current status: ' . $this->devices[$device_num]->deviceStatus . ')');
                if((time() - $start) > $max_wait)
                {
                    throw new Exception("Unable to find location within '$max_wait' seconds\n");
                }

                sleep(10);

                $this->iflog('Updating location...');
                $post = json_encode(array('serverContext' => $this->serverContext, 'clientContext' => $this->clientContext));
                $json_str = $this->curlPost('https://' . $this->partition . '-fmipweb.me.com/fmipservice/client/refreshClient', $post, 'https://' . $this->partition . '-fmipweb.me.com/find/resources/frame.html');
                $this->iflog('Rocation updates received');
                $this->parseJsonResponse($json_str);
            }

            $loc = array(
                        "latitude"  => $this->devices[$device_num]->latitude,
                        "longitude" => $this->devices[$device_num]->longitude,
                        "accuracy"  => $this->devices[$device_num]->horizontalAccuracy,
                        "timestamp" => $this->devices[$device_num]->locationTimestamp,
                        );

            return $loc;
        }

        private function updateDevices()
        {
            $this->iflog('Updating devices...');
            $post = json_encode(array('clientContext' => $this->clientContext));
            $json_str = $this->curlPost('https://' . $this->partition . '-fmipweb.me.com/fmipservice/client/initClient', $post, 'https://' . $this->partition . '-fmipweb.me.com/find/resources/frame.html');
            $this->iflog('Device updates received');
            $this->parseJsonResponse($json_str);
        }

        private function parseJsonResponse($json_str)
        {
            $json = json_decode($json_str);

            if(is_null($json))
                throw new Exception("Error parsing json string");

            if(isset($json->error))
                throw new Exception("Error from web service: '$json->error'");

            $this->devices = array();
            $this->iflog('Parsing ' . count($json->content) . ' devices...');
            foreach($json->content as $json_device)
            {
                $device = new SosumiDevice();
                if(isset($json_device->location) && is_object($json_device->location))
                {
                    $device->locationTimestamp  = date('Y-m-d H:i:s', $json_device->location->timeStamp / 1000);
                    $device->locationType        = $json_device->location->positionType;
                    $device->horizontalAccuracy = $json_device->location->horizontalAccuracy;
                    $device->locationFinished   = $json_device->location->locationFinished;
                    $device->longitude          = $json_device->location->longitude;
                    $device->latitude           = $json_device->location->latitude;
                    $device->locationOld        = $json_device->location->isOld;
                }
                $device->isLocating     = $json_device->isLocating;
                $device->deviceModel    = $json_device->deviceModel;
                $device->deviceStatus   = $json_device->deviceStatus;
                $device->id             = $json_device->id;
                $device->name           = $json_device->name;
                $device->deviceClass    = $json_device->deviceClass;
                $device->chargingStatus = $json_device->a;
                $device->batteryLevel   = $json_device->b;
                $this->devices[]        = $device;
            }

            $this->serverContext = $json->serverContext;

            // Update the timezone offset
            $this->timezoneSource = $json->serverContext->timezone->tzName;
            $this->timezoneDestination = date_default_timezone_get();
        }

        private function curlGet($url, $referer = null, $headers = null)
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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
            $this->lastStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return $html;
        }

        private function curlPost($url, $post_vars = '', $referer = null)
        {
            $headers[] = 'Accept: */*';
            $headers[] = 'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.3';
            $headers[] = 'Accept-Encoding: gzip,deflate,sdch';
            $headers[] = 'Accept-Language: en-US,en;q=0.8';
            $headers[] = 'Connection: keep-alive';

            if (isset($this->partition) && strpos($url, 'fmipweb.me.com'))
            {
                $headers[] = 'Origin: https://' . $this->partition . '-fmipweb.me.com';
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'X-Requested-With: XMLHttpRequest';
                $headers[] = 'X-SproutCore-Version: 1.0';
                $headers[] = 'X-Mobileme-Version: 1.0';
                $headers[] = 'X-Inactive-Time: ' . ($this->inactiveTime += rand(1, 90) * 1000);
            }

            if (isset($this->isc))
                $headers[] = 'X-Mobileme-Isc: ' . $this->isc;

            if (isset($this->serverContext->prsId))
                $headers[] = 'X-Mobileme-User: ' . $this->serverContext->prsId;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_1; en-us) AppleWebKit/531.9 (KHTML, like Gecko) Version/4.0.3 Safari/531.9");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
            if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // curl_setopt($ch, CURLOPT_VERBOSE, true);

            $html = curl_exec($ch);

            $this->lastURL = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            $this->lastStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return $html;
        }

        private function iflog($str)
        {
            if($this->debug === true)
                echo $str . "\n";
        }

        private function match($regex, $str, $i = 0)
        {
            return preg_match($regex, $str, $match) == 1 ? $match[$i] : false;
        }
    }

    class SosumiDevice
    {
        public $isLocating;
        public $locationTimestamp;
        public $locationType;
        public $horizontalAccuracy;
        public $locationFinished;
        public $longitude;
        public $latitude;
        public $deviceModel;
        public $deviceStatus;
        public $id;
        public $name;
        public $deviceClass;

        // These values only recently appeared in Apple's JSON response.
        // Their final names will probably change to something other than
        // 'a' and 'b'.
        public $chargingStatus; // location->a
        public $batteryLevel; // location->b
    }
