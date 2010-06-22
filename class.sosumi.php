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
		private $username;
		private $password;

		public function __construct($mobile_me_username, $mobile_me_password)
		{
			$this->devices = array();
			$this->username = $mobile_me_username;
			$this->password = $mobile_me_password;

			$this->updateDevices();
		}

		public function locate($device_num = 0, $max_wait = 300)
		{
			$start = time();

			/* loop until the device has been located */
			while ($this->devices[$device_num]->locationFinished == false)
			{
				if ((time() - $start) > $max_wait)
				{
					throw new Exception("Unable to find location within '$max_wait' seconds");
				}

				sleep(5);
				$this->updateDevices();
			}
	
			$loc = array(
				"latitude"  => $this->devices[$device_num]->latitude,
				"longitude" => $this->devices[$device_num]->longitude,
				"accuracy"  => $this->devices[$device_num]->horizontalAccuracy,
				"timestamp" => $this->devices[$device_num]->locationTimestamp,
				);

			return $loc;
		}

		public function sendMessage($device_num = 0, $subject = 'Important Message', $msg, $alarm = false)
		{
			$post = sprintf('{"clientContext":{"appName":"FindMyiPhone","appVersion":"1.0","buildVersion":"57","deviceUDID":"0000000000000000000000000000000000000000","inactiveTime":5911,"osVersion":"3.2","productType":"iPad1,1","selectedDevice":"%s","shouldLocate":false},"device":"%s","serverContext":{"callbackIntervalInMS":3000,"clientId":"0000000000000000000000000000000000000000","deviceLoadStatus":"203","hasDevices":true,"lastSessionExtensionTime":null,"maxDeviceLoadTime":60000,"maxLocatingTime":90000,"preferredLanguage":"en","prefsUpdateTime":1276872996660,"sessionLifespan":900000,"timezone":{"currentOffset":-25200000,"previousOffset":-28800000,"previousTransition":1268560799999,"tzCurrentName":"Pacific Daylight Time","tzName":"America/Los_Angeles"},"validRegion":true},"sound":%s,"subject":"%s","text":"%s"}',
								$this->devices[$device_num]->id, $this->devices[$device_num]->id,
								$alarm ? 'true' : 'false', $subject, $msg);

			$this->curlPost("https://fmipmobile.me.com/fmipservice/device/$mobile_me_username/sendMessage", $post);
		}

		public function remoteLock($device_num = 0, $passcode)
		{
			$post = sprintf('{"clientContext":{"appName":"FindMyiPhone","appVersion":"1.0","buildVersion":"57","deviceUDID":"0000000000000000000000000000000000000000","inactiveTime":5911,"osVersion":"3.2","productType":"iPad1,1","selectedDevice":"%s","shouldLocate":false},"device":"%s","oldPasscode":"","passcode":"%s","serverContext":{"callbackIntervalInMS":3000,"clientId":"0000000000000000000000000000000000000000","deviceLoadStatus":"203","hasDevices":true,"lastSessionExtensionTime":null,"maxDeviceLoadTime":60000,"maxLocatingTime":90000,"preferredLanguage":"en","prefsUpdateTime":1276872996660,"sessionLifespan":900000,"timezone":{"currentOffset":-25200000,"previousOffset":-28800000,"previousTransition":1268560799999,"tzCurrentName":"Pacific Daylight Time","tzName":"America/Los_Angeles"},"validRegion":true}}',
								$this->devices[$device_num]->id, $this->devices[$device_num]->id, $passcode);

			$this->curlPost("https://fmipmobile.me.com/fmipservice/device/$mobile_me_username/remoteLock", $post);
		}

		public function remoteWipe()
		{
			// Remotely wiping a device is an exercise best
			// left to the reader.
		}

		private function updateDevices()
		{
			$post = '{"clientContext":{"appName":"FindMyiPhone","appVersion":"1.0","buildVersion":"57","deviceUDID":"0cf3dc989ff812adb0b202baed4f37274b210853","inactiveTime":2147483647,"osVersion":"3.2","productType":"iPad1,1"}}';
			$json_str = $this->curlPost("https://fmipmobile.me.com/fmipservice/device/$this->username/initClient", $post);
			$json = json_decode($json_str);

			if (is_null($json))
				throw new Exception("Error parsing json string");

			if (isset($json->error))
				throw new Exception("Error from web service: '$json->error'");

			$this->devices = array();
			foreach($json->content as $json_device)
			{
				$device = new Device();
				$device->isLocating = $json_device->isLocating;
				$device->locationTimestamp = $json_device->location->timeStamp;
				$device->locationType = $json_device->location->positionType;
				$device->horizontalAccuracy = $json_device->location->horizontalAccuracy;
				$device->locationFinished = $json_device->location->locationFinished;
				$device->longitude = $json_device->location->longitude;
				$device->latitude = $json_device->location->latitude;
				$device->deviceModel = $json_device->deviceModel;
				$device->deviceStatus = $json_device->deviceStatus;
				$device->id = $json_device->id;
				$device->name = $json_device->name;
				$device->deviceClass = $json_device->deviceClass;
				$this->devices[] = $device;
			}
		}

		private function curlPost($url, $post_vars = '', $headers = array())
		{
			$headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password);
			$headers[] = 'X-Apple-Realm-Support: 1.0';
			$headers[] = 'Content-Type: application/json; charset=utf-8';
			$headers[] = 'X-Client-Name: Steve\'s iPad';
			$headers[] = 'X-Client-Uuid: 0cf3dc491ff812adb0b202baed4f94873b210853';

			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_USERAGENT, "Find iPhone/1.0 (iPad: iPhone OS/3.2");
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
			if(!is_null($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			// curl_setopt($ch, CURLOPT_VERBOSE, true);

			return curl_exec($ch);
		}
	}

	class Device
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
	}
