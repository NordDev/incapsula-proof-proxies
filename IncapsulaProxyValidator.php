<?php

class IncapsulaProxyValidator
{
    const SAME_PROXY_RETRIES            = 5;
    const CURL_TIMEOUT                  = 20;

    //constructor parameters
    private $_arrProxies = array();
    private $_strDomain;
    private $_intMaxProxiesFork;
    private $_strCookieFolder;
    private $_strRawDataFolder;

    private $_strProxyVerifyLink = '_Incapsula_Resource?'; //SWKMTFSR=1&e=

    private $_arrCookieConfig           = array(
        "navigator"=>"exists",
        "navigator.vendor"=>"value",
        "navigator.appName"=>"value",
        "navigator.plugins.length==0"=>"value",
        "navigator.platform"=>"value",
        "navigator.webdriver==undefined"=>"value",
        "platform"=>"plugin_extentions",
        "ActiveXObject"=>"false",
        "webkitURL"=>"exists",
        "_phantom"=>"false",
        "callPhantom"=>"false",
        "chrome"=>"exists",
        "yandex"=>"false",
        "opera"=>"false",
        "opr"=>"false",
        "safari"=>"false",
        "awesomium"=>"false",
        "puffinDevice"=>"false",
        "__nightmare"=>"false",
        "_Selenium_IDE_Recorder"=>"false",
        "document.__webdriver_script_fn"=>"false",
        'document.$cdc_asdjflasutopfhvcZLmcfl_'=>"false",
        "process.version"=>"false",
        "navigator.cpuClass"=>"false",
        "navigator.oscpu"=>"value",
        "navigator.connection"=>"false",
        "window.outerWidth==1920"=>"value",
        "window.outerHeight==1053"=>"value",
        "window.WebGLRenderingContext"=>"exists",
        "document.documentMode==undefined"=>"value",
        "eval.toString().length==33"=>"value"
    );

    /* user agents to use when making requests*/
    private $_arrUserAgents = array(
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1309.0 Safari/537.17",
        "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1",
        "Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0",
        "Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10; rv:33.0) Gecko/20100101 Firefox/33.0",
        "Mozilla/5.0 (X11; Linux i586; rv:31.0) Gecko/20100101 Firefox/31.0",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.246",
        "Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201",
        "Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9a3pre) Gecko/20070330",
        "Mozilla/5.0 (Windows; U; Windows NT 5.0; en-US; rv:1.9.2a1pre) Gecko",
        "Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16",
        "Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; fr) Presto/2.9.168 Version/11.52",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A",
        "Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.13+ (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2"
        );

    private $_arrGoodProxies = array();

    public function __construct($arrProxies, $strDomain, $intMaxProxiesFork = 15, $strCookieFolder = 'cookies', $strRawDataFolder = 'raw_data')
    {
        $this->_arrProxies = $arrProxies;
        $this->_strDomain = $strDomain;
        $this->_intMaxProxiesFork = $intMaxProxiesFork;
        $this->_strCookieFolder = $strCookieFolder;
        $this->_strRawDataFolder = $strRawDataFolder;
    }

    public function _createLegitProxies()
    {
        //creating cookies folder
        if (!is_dir($this->_strCookieFolder))
        {
            mkdir($this->_strCookieFolder, 0777, true);
        }

        //creating raw_data folder
        if (!is_dir($this->_strRawDataFolder))
        {
            mkdir($this->_strRawDataFolder, 0777, true);
        }

        if($this->_strDomain[strlen($this->_strDomain) - 1] !== '/')
            $this->_strDomain .= '/';

        $this->_strProxyVerifyLink = $this->_strDomain.$this->_strProxyVerifyLink;


        $intChunksNumber = round(count($this->_arrProxies) / $this->_intMaxProxiesFork);
        $arrProcChunks = array_chunk($this->_arrProxies, $intChunksNumber, true);
        $intProxiesPerProc = count($arrProcChunks);
        print_r("Splitting into $intProxiesPerProc subprocesses".PHP_EOL);
        $arrPids = array();

        for ($intProc= 0; $intProc < $intProxiesPerProc; $intProc++)
        {
            $arrPids[$intProc] = pcntl_fork();

            if (0 == $arrPids[$intProc])
            {
                //start process
                $intTotalProxiesPerProc = count($arrProcChunks[$intProc]);

                print_r("Starting process $intProc with $intTotalProxiesPerProc proxies".PHP_EOL);

                $intCount = 0;
                $arrGoodProxies = array();
                foreach($arrProcChunks[$intProc] as $arrProxy)
                {
//                    $arrProxy = $this->_arrProxies[$strProxyKey];
                    $strDebugString = "[$intProc: $intCount/$intTotalProxiesPerProc]";
                    $strResponse = $this->_getPage($this->_strDomain, $arrProxy);
                    if(!$strResponse)
                    {
                        print_r("couldn't get ".$this->_strDomain.PHP_EOL);
                        continue;
                    }
                    $strObfuscatedCode = $this->_getObfuscatedCode($strResponse);
                    if(!$strObfuscatedCode)
                    {
                        print_r("$strDebugString Didn't find obfuscated code in the request with ".$arrProxy['IP:PORT'].PHP_EOL);
                        continue;
                    }
                    $arrTiming = array();
                    $intTimeStart = round(microtime(true)*1000);

                    $arrResources = $this->_decodeObfuscatedCode($strObfuscatedCode);
                    if(!$arrResources || count($arrResources) < 2)
                    {
                        print_r("$strDebugString Error! Couldn't get link resources from the obfuscated code block for ".$arrProxy['IP:PORT'].PHP_EOL);
                        return false;
                    }

                    $this->_strProxyVerifyLink .= $arrResources[0];

                    print_r("$strDebugString Loading data and navigator plugins to create Incapsula cookie for ".$arrProxy['IP:PORT'].PHP_EOL);

                    //getting random browser navigator
                    $arrNavigators = glob('navigators/*.json', GLOB_NOSORT);
                    $strRandomNavigator = $arrNavigators[array_rand($arrNavigators)];
                    $strJsonRaw = file_get_contents($strRandomNavigator);
                    $objNavigator = json_decode($strJsonRaw);

                    //getting navigator extensions and properties
                    $arrExtensions = $this->_getNavigatorProperties($objNavigator, $this->_arrCookieConfig);

                    //setting incapsula cookie
                    $strIncapsulaCookie = $this->_setIncapsulaCookie($arrExtensions, $arrProxy['IP:PORT']);
                    if(!$strIncapsulaCookie)
                    {
                        print_r("$strDebugString Couldn't find the cookie for ".$arrProxy['IP:PORT'].PHP_EOL);
                        continue;
                    }

                    print_r("$strDebugString Requesting access link #1: " . $this->_strDomain.$arrResources[1] . ' with '.$arrProxy['IP:PORT'].PHP_EOL);
                    $bolAccessGranted = false;
                    for($intI = 0; $intI < self::SAME_PROXY_RETRIES; $intI++)
                    {
                        $arrTiming[] = 's:'.(round(microtime(true)*1000)-$intTimeStart);
                        $strResponse = $this->_getPage($this->_strDomain . $arrResources[1], $arrProxy, $strIncapsulaCookie);
                        if($strResponse)
                        {
                            $bolAccessGranted = true;
                            break;
                        }
                        sleep(0.1);
                    }
                    if(!$bolAccessGranted)
                    {
                        print_r("$strDebugString Access was not granted in link #1 for ".$arrProxy['IP:PORT'].'. Proxy may fail...'.PHP_EOL);
                        //                        continue;
                    }

                    //might not be needed
                    //                    $arrTiming[] = 'c:'.(round(microtime(true)*1000)-$intTimeStart);
                    //                    //simulating a page reload
                    //                    sleep(0.2);
                    //                    $arrTiming[] = 'r:'.(round(microtime(true)*1000)-$intTimeStart);
                    //                    $strResponse = $this->_getPage(self::PROXY_CHECK_URL.$arrResources[0] . urlencode('complete ('.implode(',',$arrTiming).')'), $arrProxy, $strIncapsulaCookie);
                    //                    if(!$strResponse)
                    //                    {
                    //                        print_r('Access was not granted in link #2 for '.$arrProxy['IP:PORT']. '. Proxy may fail...');
                    //                    }
                    $bolAccessGranted = false;
                    for($intI = 0; $intI < self::SAME_PROXY_RETRIES; $intI++)
                    {
                        $strResponse = $this->_getPage($this->_strProxyVerifyLink.((float)rand()/(float)getrandmax()), $arrProxy, $strIncapsulaCookie);
                        if($strResponse)
                        {
                            $bolAccessGranted = true;
                            break;
                        }
                    }
                    if(!$bolAccessGranted)
                    {
                        print_r("$strDebugString Access was not granted in link #3 for ".$arrProxy['IP:PORT']. '. Proxy may fail...'.PHP_EOL);
                    }

                    //after these requests, we should be able to make normal requests from the goodProxy
                    print_r("$strDebugString Requesting stations URL: " . $this->_strDomain . ' with '.$arrProxy['IP:PORT'].PHP_EOL);
                    $bolCookiedProxyCheck = false;
                    for($intI = 0; $intI < self::SAME_PROXY_RETRIES; $intI++)
                    {
                        sleep(0.1);
                        $strCheckMainURLResponse = $this->_getPage($this->_strDomain, $arrProxy);
                        if(strpos($strCheckMainURLResponse, 'Prodotti') !== false)
                        {
                            print_r("$strDebugString Proxy ".$arrProxy['IP:PORT'].' was successful!'.PHP_EOL);
                            $bolCookiedProxyCheck = true;
                            break;
                        }
                        else
                        {
                            continue;
                        }
                    }
                    if(!$bolCookiedProxyCheck)
                    {
                        print_r("$strDebugString Proxy ".$arrProxy['IP:PORT'].' failed!'.PHP_EOL);
                        continue;
                    }
                    $arrGoodProxies[] = $arrProxy;
                }

                //save process results
                $strPdataFilename = $this->_getProcessPath($intProc, '.serial');

                file_put_contents($strPdataFilename, serialize($arrGoodProxies));
                print_r("Process $intProc finished work.");
                // End process
                exit;
            }
        }

        for ($intProc= 0; $intProc < $intProxiesPerProc; $intProc++)
        {
            pcntl_waitpid($arrPids[$intProc], $_, WUNTRACED);

            // check file existence
            $strPdataFilename = $this->_getProcessPath($intProc, '.serial');
            if (!file_exists($strPdataFilename))
            {
                print_r('Partial process file for process '.$intProc.' not found'.PHP_EOL);
                continue;
            }

            // verify tht the file has contents
            $strResponse = file_get_contents($strPdataFilename);
            unlink($strPdataFilename);
            if (!$strResponse)
            {
                print_r('Partial process file for process '.$intProc.' could not be read'.PHP_EOL);
                continue;
            }

            // unserialize data from file
            $arrSliceGoodProxies = unserialize($strResponse);
            if (!$arrSliceGoodProxies || !is_array($arrSliceGoodProxies))
            {
                print_r('Partial process file for process '.$intProc.' could not be unserialised into an array'.PHP_EOL);
                continue;
            }

            $this->_arrGoodProxies = array_merge($arrSliceGoodProxies, $this->_arrGoodProxies);
        }
        print_r('Found '.count($this->_arrGoodProxies).' legit proxies!'.PHP_EOL);
        return true;
    }

    private function _getProcessPath($intNum,$intExtension)
    {
        if ($intNum===0)
            return $this->_strRawDataFolder.'/Incapsula_cookies'.$intExtension;
        else
            return $this->_strRawDataFolder.'/Incapsula_cookies_'.$intNum.$intExtension;
    }

    /**
     * Function that gets a chunk of hexa obfuscated code
     * that is placed at the end of the response string
     *
     * @param $strResponse
     * @return bool
     */
    private function _getObfuscatedCode($strResponse)
    {
        if(preg_match('%var\s?b\s?=\s?\"(.*?)\"%s', $strResponse, $arrCode))
            return $arrCode[1];
        return false;
    }

    /**
     * Function that decodes the hexa string and returns the
     * link resources inside it
     *
     * @param $strCode
     * @return string
     */
    private function _decodeObfuscatedCode($strCode)
    {
        $arrData = array();
        $strDecoded = '';
        $arrChunks = explode("\n",chunk_split($strCode, 2));
        foreach($arrChunks as $strChunk)
        {
            $arrData[] = intval(strval($strChunk), 16);
        }
        foreach($arrData as $intX)
        {
            $strDecoded .= chr($intX);
        }
        if(preg_match_all('%(\/_Incapsula_Resource.*?)\"%s', $strDecoded, $arrResources))
        {
            return $arrResources[1];
        }
        return false;
    }

    /**
     * Create navigator properties using a random navigator
     * and the config file found in the decoded JS block
     *
     * @param $objNavigator
     * @param $arrConfig
     * @return array
     */
    private function _getNavigatorProperties($objNavigator, $arrConfig)
    {
        $arrProperties = array();

        foreach($arrConfig as $strKey => $strItem)
        {
            if($strKey === 'navigator.plugins.length==0')
            {
                $arrProperties[] = urlencode($strKey.'=false');
                continue;
            }
            switch($strItem)
            {
                case 'exists':
                    $arrProperties[] = urlencode($strKey.'=true');
                    break;
                case 'false':
                    $arrProperties[] = urlencode($strKey.'=false');
                    break;
                case 'value':
                    if(strpos($strKey, '==') !== false)
                    {
                        if(preg_match('%([\(\)a-zA-Z\._0-9\$]+?)==(\d{0,})$%s', $strKey, $arrExpression))
                        {
                            $arrProperties[] = urlencode($arrExpression[1].'='.$arrExpression[2]);
                        }
                        else if(preg_match('%([a-zA-Z\._0-9\$]+?)==undefined%s', $strKey, $arrExpression))
                        {
                            $arrProperties[] = urlencode($arrExpression[1].'=undefined');
                        }
                    }
                    else
                    {
                        $arrElements = explode('.', $strKey);
                        switch($arrElements[0])
                        {
                            case 'navigator':
                                if(isset($objNavigator->$arrElements[1]))
                                {
                                    $arrProperties[] = urlencode($strKey.'='.$objNavigator->$arrElements[1]);
                                }
                                else
                                {
                                    $arrProperties[] = urlencode($strKey.'=null');
                                }
                        }
                    }
                    break;
                case 'plugin_extentions':
                    foreach($objNavigator->plugins as $objPlugin)
                    {
                        if(!isset($objPlugin->filename))
                        {
                            $arrProperties[] = urlencode("plugin_ext=filename is undefined");
                            continue;
                        }
                        $arrSeparateExtension = explode('.',$objPlugin->filename);
                        $strExt = "no extention"; //yes I know, but that's the way it is in the incapsula JS. Do NOT modify
                        if(count($arrSeparateExtension) == 2)
                        {
                            $strExt = $arrSeparateExtension[0];
                        }
                        $arrProperties[] = urlencode("plugin_ext=".$strExt);
                    }
                    break;
                default:
                    break;
            }
        }
        return $arrProperties;
    }

    /**
     * Function that creates and returns a specific Incapsula cookie
     * that allows the IP to be validated
     *
     * @param $arrExtensions
     * @return string
     */
    private function _setIncapsulaCookie($arrExtensions, $strProxy)
    {
        $strCookieFilename = $this->_strCookieFolder.'/cookie-'.$strProxy.'.txt';
        if(!file_exists($strCookieFilename))
        {
            print_r("No cookie found for $strProxy !");
            return false;
        }
        $strCookie = file_get_contents($strCookieFilename);
        $arrSessionCookies = $this->_getSessionCookies($strCookie);
        if(count($arrSessionCookies) < 1)
        {
            print_r("No Incapsula cookie found in the $strProxy cookie!");
            return false;
        }
        $intI=0;
        $arrDigests = array();
        foreach($arrSessionCookies as $strKey => $strSessionCookie)
        {
            $arrDigests[$intI] = $this->_toDigest(implode(',',$arrExtensions) . $strSessionCookie);
            $intI++;
        }
        $strNewCookieValue = implode(',',$arrExtensions) . ",digest=" . implode(',',$arrDigests);

        $strFinalIncapsulaCookie = $this->_createCookie("___utmvc", $strNewCookieValue, 20);
        $strCookiestr='';
        foreach($arrSessionCookies as $strKey => $strValue)
        {
            $strCookiestr .= $strKey . '='.$strValue.';';
        }
        $strFinalIncapsulaCookie = $strCookiestr.$strFinalIncapsulaCookie;

        return $strFinalIncapsulaCookie;
    }

    /**
     * Function that parses a cookie in order to get the in
     *
     * @param $strCookie
     * @return array
     */
    private function _getSessionCookies($strCookie)
    {
        $arrCookies = array();
        if(preg_match_all('%((incap_ses|visid_incap)_[0-9_]+)\s+?([A-Za-z0-9=]+)%s', $strCookie, $arrSessionCookies))
        {
            $intCount = count($arrSessionCookies[0]); //TODO be careful (one change made)
            for($i = 0; $i < $intCount; $i++)
            {
                $arrCookies[$arrSessionCookies[1][$i]] = $arrSessionCookies[3][$i];
            }
        }
        return $arrCookies;
    }

    /**
     * Return the sum of the ASCII values of all of a string's characters
     *
     * @param $strProperties
     * @return int
     */
    private function _toDigest($strProperties)
    {
        $intDigest = 0;
        $arrStr = str_split($strProperties);
        foreach($arrStr as $strChar)
            $intDigest += ord($strChar);
        return $intDigest;
    }

    /**
     * Function that creates a cookie with key, value and expiration date
     *
     * @param $strName
     * @param $strValue
     * @param $intSeconds
     * @return string
     */
    private function _createCookie($strName, $strValue, $intSeconds)
    {
        $objDate = new DateTime('+'.$intSeconds.' seconds');
        $strFormatedDate = $objDate->format('D, d M Y H:i:s');
        $strCookie = $strName . "=" . $strValue . '; expires=' . $strFormatedDate . "; path=/";
        return $strCookie;
    }

    /**
     * Function that returns the Incapsula validated proxy
     *
     * @return array
     */
    public function _getValidProxies()
    {
        return $this->_arrGoodProxies;
    }

    /**
     * @param $strFullURL
     * @param $arrProxy
     * @param $bolStoreReferer
     * @param string $strCookie
     * @return bool|mixed
     */
    private function _getPage($strFullURL, $arrProxy, $strCookie='')
    {
        $arrHeader = array();
        $ch = curl_init();
        if($strCookie === '')
        {
            // set one cookie file per proxy
            $strCookieFile = $this->_strCookieFolder."/cookie-".$arrProxy['IP:PORT'].".txt";

            curl_setopt($ch,CURLOPT_COOKIEJAR, $strCookieFile);
            curl_setopt($ch,CURLOPT_COOKIEFILE, $strCookieFile);
        }
        else
        {
            $arrHeader[] = 'cookie: '.$strCookie;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        curl_setopt($ch, CURLOPT_PROXY, $arrProxy['IP:PORT']);

        switch(trim(strtolower($arrProxy['PROTOCOL'])))
        {
            case 'http':
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                break;
            case 'socks':
            case 'socks5':
                return false;
            default:
                return false;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $arrHeader);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->_arrUserAgents[array_rand($this->_arrUserAgents)]);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
//        curl_setopt($ch, CURLOPT_CAINFO, "cacert.pem");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_URL, $strFullURL);


        $strResponse = curl_exec($ch);
        $arrCurlInfo = curl_getinfo($ch);
        print_r($arrCurlInfo['http_code']. " error code\n");
        if(curl_exec($ch) === false)
        {
            echo 'Curl error: ' . curl_error($ch).PHP_EOL;
        }
        if($arrCurlInfo['http_code'] == 200)
        {
            return $strResponse;
        }
        return false;
    }
}
