Sosumi
=========

A PHP client for Apple's Find My iPhone service. This allows you to programmatically retrieve your phone's current location and push messages (and an optional alarm) to the remote device.

The previous version of Sosumi (June 2009 - June 20, 2010) scraped MobileMe's website to determine your location information. However, with Apple's recently released "Find My iPhone" app, we can piggy-back on their "official" web service and pull your information much faster and more reliably as it's not prone to breaking whenever there's a website update. I highly recommend upgrading to the new version.

Much love to the MobileMe team for a wonderful service :-)

FEATURES
--------

 * Retrieve your device's current location and margin of error.
 * Push a custom text message to the device and an optional audible alarm.

INSTALL
-------

This script requires PHP 5.2 and the JSON extension, which should be included by default. PHP's CURL extension (with SSL support) is also required.

EXAMPLES
--------
Two example scripts are included. The first one, `example.php` retrieves your current location and plots it on a Google map. It also allows you to send a push notification message and an optional alarm. The second script, `cron.php`, will grab your location and store it in a MySQL database, allowing you to track your position over time.

UPDATES
-------

Code is hosted at GitHub: [http://github.com/tylerhall/sosumi](http://github.com/tylerhall/sosumi)

LICENSE
-------

The MIT License

Copyright (c) 2009 Tyler Hall <tylerhall AT gmail DOT com>

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
