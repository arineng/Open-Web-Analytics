<?php

class owa_trackingEventHelpers {
	
	// incoming tracking event control flow:
	// 0. create event
	// 0. translate request property keys
	// 0. set all properties from request
	// 0. set environmental properties if not present
	// 0. queue or notify event
	
	// in event handler...
	// 0. register all filters callbacks for required, derived, and optional properties
	// 0. deconstruct custom variable properties
	// 0. filter existing event properties
	// 0. filter/set required event properties
	// 0. filter/set derived event properties
	// 0. handler specific logic
	
	static function getInstance() {
		
		static $o;
		
		if ( ! $o ) {
			
			$o = new owa_trackingEventHelpers();
		}
		
		return $o;
	}
	
	public function __construct() {
		
	
		
		
	}
	
	public function translateKeys( $event ) {
		
		foreach ( $this->translations as $k => $v ) {
			
			$event->set( $v, $event->get( $k ));
			$event->delete( $k );
		}
	}
	
	public function setEnvironmentals( $event ) {
		
		foreach ( $this->environmentals as $k => $v ) {
			// loop and execute call backs.
			$event->set( $k, call_user_func( $this->environmentals[ $k ][ 'default_value' ][0] ) );
		}
		
	}
	
	public function registerCallbacks( $items, $priority = 0 ) {
		
		foreach ($items as $name => $item ) {
		
			if ( isset( $item['callbacks'] ) && ! empty($item['callbacks'] ) ) {
				
				if ( is_array(  $item['callbacks'] ) ) {
					
				
					foreach ($item['callbacks'] as $callback ) {
						
						owa_coreAPI::registerFilter( $name, $callback,'', $priority);		
					}
				} else {
					
					owa_coreAPI::registerFilter( $name, $item['callbacks'],'', $priority);	
				}
			}
		}
	}
	
/*
	public function generateIds( $event, $properties ) {
		
		$this->registerCallbacks( $properties, 0 );
		
	}
*/
	
	public function setTrackerProperties( $event, $properties ) {
		
		$this->registerCallbacks( $properties, 0 );
		
		$eq = owa_coreAPI::getEventDispatch();
		
		foreach ( $properties as $name => $property ) {
			
			$value = $event->get( $name );
			/*
			if ( isset( $property['data_type'] ) && $property['data_type'] ) {
				$data_type = $property['data_type'];
			}
			
			
			$value = $this->setDataType( $value, $data_type );
			*/
			$required = false;
			
			if ( isset( $property['required'] ) ) {
				
				$required = $property['required'];
			}
			
			if ( ! $value && $value !== 0 && $value !== "0" ) {
				
				if ( isset( $property['alternative_key'] ) &&  $property['alternative_key'] ) {
						
					$value = $event->get( $property['alternative_key'] );
					// should we delete the original key on the event? if so:
					//$event->delete( $name );
				}
			}
			
			// filter value
			$value = $eq->filter( $name, $value, $event );
			
			//set default value
			if ( $required && ! $value && $value !== 0 && $value !== "0") {
			
				if ( isset( $property['default_value'] ) && $property['default_value'] ) { 	
			
					$value = $property['default_value'];
				}
			}
			
			// set value on the event
				
			if ( $required || $value || $value === 0 || $value === "0" ) {
				
				$event->set( $name,  $value );
			}
		}
	}
	
	static function setDataType( $var, $type = 'string' ) {
		
		switch( $type ) {
			
			case "integer":
			
				$var = $var + 0;
				break;
		}
		
		return $var;
	}
		
	function addCustomVariableProperties( $properties ) {
		
		$maxCustomVars = owa_coreAPI::getSetting( 'base', 'maxCustomVars' );
		
		for ($i = 1; $i <= $maxCustomVars; $i++) {
		
			$properties[ 'cv'.$i.'_name' ] = array(
						
				'required'		=> true,
				'data_type'		=> 'string',
				'callbacks'		=> array( 'owa_trackingEventHelpers::lowercaseString'),
				'default_value'	=> '(not set)'
			);
					
			$properties[ 'cv'.$i.'_value' ] = array(
						
				'required'		=> true,
				'data_type'		=> 'string',
				'callbacks'		=> array( 'owa_trackingEventHelpers::lowercaseString' ),
				'default_value'	=> '(not set)'
			);

		}
		
		return $properties;
	}
	
	function translateCustomVariables( $event ) {
		
		$maxCustomVars = owa_coreAPI::getSetting( 'base', 'maxCustomVars' );
		
		for ($i = 1; $i <= $maxCustomVars; $i++) {
		
			$cvar = $event->get( 'cv'.$i );
			
			if ( $cvar ) {
				//split the string
				$pieces = explode( '=' , trim( $cvar ) );
				if ( isset( $pieces[1] ) ) {
					$event->set( 'cv'.$i.'_name', $pieces[0] );
					$event->set( 'cv'.$i.'_value', $pieces[1] );
				}
			}
		}
	}
	
		static function remoteHostDefault() {
	
		return owa_coreAPI::getServerParam('REMOTE_HOST');	
	}
	
	static function userAgentDefault() {
		
		return owa_coreAPI::getServerParam('HTTP_USER_AGENT');	
	}
	
	static function httpHostDefault() {
		
		return owa_coreAPI::getServerParam('HTTP_HOST');	
	}
	
	static function languageDefault() {
		
		return substr( owa_coreAPI::getServerParam( 'HTTP_ACCEPT_LANGUAGE' ), 0, 5 );	
	}
	
	static function ipAddressDefault() {
		
		return owa_coreAPI::getServerParam('REMOTE_ADDR');	
	}
	
	static function timestampDefault() {
		
		return owa_coreAPI::getRequestTimestamp();	
	}
	
	static function microtimeDefault() {
		
		return microtime();	
	}
	
	static function generateLocationId( $property_name, $event ) {
		
		if ( $event->get( 'country' ) ) {
			$s = owa_coreAPI::serviceSingleton();	
			return $s->geolocation->generateId( $event->get( 'country' ), $event->get( 'state' ), $event->get( 'city' ) );	
		}
	}
	
	static function generateDimensionId ( $property_value, $event ) {
		
		if ( $property_value ) {
			
			return owa_lib::setStringGuid( $property_value );
		}
		 
	}
		
	static function setRepeatVisitorFlag( $flag, $event ) {
		
		// set repeat visitor type flag visitor is not new.		
		if ( ! $event->get( 'is_new_visitor' ) ) {
		
			return true;
		}
	}
	
	static function deriveYear( $year, $event ) {
		
		return date( "Y", $event->get('timestamp') );
		
	}
	
	static function deriveMonth( $month, $event ) {
		
		return date("Ym", $event->get('timestamp') );
		
	}
	
	static function deriveDay( $day, $event ) {
		
		return date("d", $event->get('timestamp') );
		
	}
	
	static function deriveYyyymmdd( $yyyymmdd, $event ) {
		
		return date("Ymd", $event->get('timestamp') );
		
	}
	
	static function deriveDayOfWeek( $dayofweek, $event ) {
		
		return date("D", $event->get('timestamp') );
		
	}
	
	static function deriveDayOfYear( $dayofyear, $event ) {
		
		return date("z", $event->get('timestamp') );
		
	}
	
	static function deriveWeekOfYear( $weekofyear, $event ) {
		
		return date("W", $event->get('timestamp') );
		
	}
	
	static function deriveHour( $hour, $event ) {
		
		return date("G", $event->get('timestamp') );
		
	}
	
	static function deriveMinute( $minute, $event ) {
		
		return date("i", $event->get('timestamp') );
		
	}
	
	static function deriveSecond( $second, $event ) {
		
		return date("s", $event->get('timestamp') );
		
	}
	
	static function deriveSec( $sec, $event ) {
		
		list( $msec, $sec ) = explode( " ", $event->get( 'microtime' ) );
		return $sec;
	}
	
	static function deriveMsec( $msec, $event ) {
		
		list( $msec, $sec ) = explode( " ", $event->get( 'microtime' ) );
		return $msec;
	}
	
	static function derivePageUri( $page_uri, $event ) {
				
		$page_parse = parse_url( $event->get( 'page_url' ) );
		
		if ( ! array_key_exists( 'path', $page_parse ) || empty( $page_parse['path'] ) ) {
			
			$page_parse['path'] = '/';
		}
	
		if ( array_key_exists( 'query', $page_parse ) || ! empty( $page_parse['query'] ) ) {
			
			return sprintf( '%s?%s', $page_parse['path'], $page_parse['query'] );	
			
		} else {
			
			return $page_parse['path'] ;
		}
	}

	/**
	 * Filter function Strips a URL of certain defined session or tracking params
	 *
	 * @return string
	 */
	static function makeUrlCanonical( $url, $event ) {
		
		$site_id = $event->getSiteId();
		
		if ( ! $site_id ) {
			owa_coreAPI::debug('no site_id passed to make makeUrlCanonical. Returning URL as is.');
			return $url;
		} 			
			
		// remove port, pass, user, and fragment
		$url = owa_lib::unparseUrl( parse_url( $url ), array( 'port', 'user', 'pass', 'fragment' ) );
		
		owa_coreAPI::debug('makeUrlCanonical using site_id: '.$site_id);
		$site = owa_coreAPI::entityFactory('base.site');
		$site->load( $site->generateId( $site_id ) );
		
		$filter_string = $site->getSiteSetting( 'query_string_filters' );
		
		if ($filter_string) {
			$filters = str_replace(' ', '', $filter_string);
			$filters = explode(',', $filter_string);
		} else {
			$filters = array();
		}
		
		// merge global filters
		$global_filters = owa_coreAPI::getSetting('base', 'query_string_filters');
		if ($global_filters) {
			$global_filters = str_replace(' ', '', $global_filters);
			$global_filters = explode(',', $global_filters);
			$filters = array_merge($global_filters, $filters);
		}
			
		// OWA specific params to filter
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'source');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'medium');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'campaign');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'ad');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'ad_type');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'overlay');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').'state');
		array_push($filters, owa_coreAPI::getSetting('base', 'ns').owa_coreAPI::getSetting('base', 'feed_subscription_param'));
		
		//print_r($filters);

		// remove jsessionid
		$url = preg_replace('`;jsessionid=[^?]*(?=\?|$)`i', '', $url);
		// remove matches to URL parameter filter (supporting regular expressions)
		foreach ($filters as $filter => $value) {
			$pattern = '`&' . $value . '(=[^&]*)?(?=&|$)|(?<=\?)' . $value . '(=[^&]*)?(&|$)`i';
			$url = preg_replace($pattern, '', $url);
		}
		// remove dangling '?'
		$url = rtrim($url, '?');
		
		//check and remove default page
		$default_page = $site->getSiteSetting( 'default_page' );
		
		if ($default_page) {
		
			$default_length = strlen($default_page);
			
			if ($default_length) {
				
				//test for string
				$default_test = substr($url, 0 - $default_length, $default_length);
				if ($default_test === $default_page) {
					$url = substr($url, 0, 0 - $default_length);
				}
			}
		}
				
		// check and remove trailing slash
		if (substr($url, -1) === '/') {
			
			$url = substr($url, 0, -1);
		}

		$rewrite_rules_string = $site->getSiteSetting( 'rewrite_rules' );
		if ( $rewrite_rules_string ) {
			$rewrite_rules_string = trim($rewrite_rules_string);
			if ( !empty($rewrite_rules_string) ) {
				$rewrite_rule_lines = explode(',', $rewrite_rules_string);
				foreach ($rewrite_rule_lines as $rewrite_rule_line) {
					$rewrite_rule = explode('->', $rewrite_rule_line, 2);
					if (count($rewrite_rule) === 2) {
						$url = preg_replace('`' . trim($rewrite_rule[0]) . '`i', trim($rewrite_rule[1]), $url);
					}
				}
			}
		}

		// check for domain aliases
		$das = $site->getSiteSetting( 'domain_aliases' );
		
		if ( $das ) {
			
			$site_domain = $site->getDomainName();
			
			if ( ! strpos( $url, '://'. $site_domain ) ) {
			
				$das = explode(',', $das);
				
				foreach ($das as $da) {
					owa_coreAPI::debug("Checking URL for domain alias: $da");
					$da = trim($da);
					if ( strpos( $url, $da ) ) {
						$url = str_replace($da, $site_domain, $url);
						break;
					}
				}
			}
		}
		
     	return $url;
		
	}
	
	static function utfEncodeProperty( $string, $event ) {
		
		return owa_lib::utf8Encode( trim( $string ) );
	}
	
	/**
	 * Resolve hostname from IP address
	 * 
	 * @access public
	 */
	static function resolveFullHost( $full_host, $event ) {
		
		// See if host is already resolved
		if ( $event->get('REMOTE_HOST') === '(not set)'
			 && $event->get( 'ip_address' ) 
			 && owa_coreAPI::getSetting('base', 'resolve_hosts') 
		) {
			
			// Do the host lookup
			$ip_address = $event->get( 'ip_address' );
			
			// Do the host lookup
			
			if ( ! strpos( $ip_address, '.' ) ) {
				
				 $result = @dns_get_record($host_name,DNS_AAAA);
				 
				 if ( is_array( $result ) && isset( $result[0] ) && isset( $result[0]['host'] ) ) {
					 
					 $remote_host = $result[0]['host'];
				 }
				 
			} else {
			
				$remote_host = @gethostbyaddr( $ip_address );
			}
			owa_coreAPI::debug(print_r($remote_host, true));
			if ( $remote_host 
				 && $remote_host != $ip_address 
				 && $remote_host != 'unknown' 
			) {
				
				return $remote_host;	
			}
		} else {
			
			return $event->get('REMOTE_HOST'); 
		}
	}
	
	static function getHostDomain( $host, $event ) {
		
		$fullhost = $event->get( 'full_host' );
					
		if ( $fullhost ) {
			
			// Sometimes gethostbyaddr returns 'unknown' or the IP address if it can't resolve the host
			if ($fullhost === 'localhost') {
			
				$host = 'localhost';
				
			} else {
			
				// lookup the registered domain using the Public Suffix List.
				$host = owa_coreAPI::getRegisteredDomain( $fullhost );
				owa_coreAPI::debug("Registered domain is: $host");
			}	
			
			return $host;	
		}
	}
	
	static function resolveBrowserType( $browser_type, $event ) {
		
		$service = owa_coreAPI::serviceSingleton();
		
		$bcap = $service->getBrowscap();
		
		return $bcap->getUaFamily();
	}
	
	static function isBrowser( $is_browser , $event ) {
		
		if ( $event->get( 'browser_type' ) ) {
			
			return true;
		}
	}
	
	static function resolveBrowserVersion( $version, $event ) {
		
		$service = owa_coreAPI::serviceSingleton();
		
		$bcap = $service->getBrowscap();
		
		return $bcap->getUaVersion();
	}
	
	static function isRobot ( $is_robot, $event ) {
		
		$service = owa_coreAPI::serviceSingleton();
		
		$bcap = $service->getBrowscap();
		
		return $bcap->isRobot();
	}
	
	static function resolveOs ( $os, $event ) {
		
		$service = owa_coreAPI::serviceSingleton();
		
		$bcap = $service->getBrowscap();
		
		return $bcap->getOsFamily();
	
	}
	
	static function resolveEntryPage( $is_entry_page, $event ) {
		
		if ( $event->get( 'is_new_session' ) ) {
			
			return true;	
		}
	}
	
	static function resolveCountry ( $country, $event ) {
		
		if ( ! $country ) {
			
			$location = owa_coreAPI::getGeolocationFromIpAddress( $event->get( 'ip_address' ) );
			
			return $location->getCountry();
		}
	}
	
	static function resolveCity ( $city, $event ) {
		
		if ( ! $city ) {
			
			$location = owa_coreAPI::getGeolocationFromIpAddress( $event->get( 'ip_address' ) );
			
			return $location->getCity();
		}
	}
	
	static function resolveLatitude ( $latitude, $event ) {
		
		if ( ! $latitude ) {
			
			$location = owa_coreAPI::getGeolocationFromIpAddress( $event->get( 'ip_address' ) );
			
			return $location->getLatitude();
		}
	}
	
	static function resolveLongitude ( $longitude, $event ) {
		
		if ( ! $longitude ) {
			
			$location = owa_coreAPI::getGeolocationFromIpAddress( $event->get( 'ip_address' ) );
			
			return $location->getLongitude();
		}
	}
	
	static function resolveCountryCode ( $country_code, $event ) {
		
		if ( ! $country_code ) {
			
			$location = owa_coreAPI::getGeolocationFromIpAddress( $event->get( 'ip_address' ) );
			
			return $location->getCountryCode();
		}
	}
	
	static function resolveState ( $state, $event ) {
		
		if ( ! $state ) {
			
			$location = owa_coreAPI::getGeolocationFromIpAddress( $event->get( 'ip_address' ) );
			
			return $location->getState();
		}
	}
	
	static function lowercaseString ( $string, $event ) {
		
		return strtolower( trim( $string ) );
	}
	
	static function setPriorPage ( $prior_page, $event ) {
		
		if ( ! $prior_page ) {
		
			if ( $event->get( 'HTTP_REFERER' ) ) {
				// @todo is this parse done somewhere else already? source?	
				$referer_parse = owa_lib::parse_url( $event->get('HTTP_REFERER') );
				
				$http_host = $event->get( 'HTTP_HOST' );
	
				if ( isset($referer_parse['host'] ) && $referer_parse['host'] === $http_host ) {
					
					return $event->get('HTTP_REFERER');	
				}
			}
		}
	}
	
	static function setSearchTerms ( $search_terms, $event ) {
		
		if ( $search_terms && $search_terms != '(not set)' ) {
		
			return trim( strtolower( $search_terms ) );
		}
	}
	
	static function setUserName( $user_name, $event ) {
		
		// record and filter personally identifiable info (PII)		
		if ( owa_coreAPI::getSetting( 'base', 'log_visitor_pii' ) ) {
			
			// set user name if one does not already exist on event
			if ( ! $user_name && owa_coreAPI::getSetting( 'base', 'log_owa_user_names' ) ) {
			
				$cu = owa_coreAPI::getCurrentUser();
				
				return $cu->user->get( 'user_id' );
			}
		}
	}
	
	static function setEmailAddress ( $email_address, $event ) {
		
		if ( owa_coreAPI::getSetting( 'base', 'log_visitor_pii' ) ) {
		
			$cu = owa_coreAPI::getCurrentUser();
			
			return $cu->user->get( 'email_address' );
		}
		
	}
		
}

?>