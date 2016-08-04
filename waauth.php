<?php

/**
 *
 * This was completely based on the code from external auth module for Joomla and highly customised for the Audax 
 * Australia cycling club. It uses the WildApricot member ID number as the Joomla ID and we use the plugin users_same_email
 * as WA users may have no e-mail address.
 *
 * Also has code to add the user to a group or remove from a group based on membership status.
 *
 * It probably won't work straight up and will need customisation as it relies on certain variables our club uses
 * and certain groups in WildApricot we have setup. 
 */

// Check to ensure this file is included in Joomla!

defined('_JEXEC') or die();
jimport('joomla.event.plugin');

/**
 * External WildApricto Database Authentication Plugin.  Based on the example.php plugin in the Joomla! Core installation
 *
 * @package    Marcos.Joomla
 * @subpackage Plugins
 * @license    GNU/GPL
 */

class plgAuthenticationWaAuth extends JPlugin
{
    /**
     * Constructor
     *
     * For php4 compatability we must not use the __constructor as a constructor for plugins
     * because func_get_args ( void ) returns a copy of all passed arguments NOT references
     * This causes problems with cross-referencing necessary for the observer design pattern.
     *
     * @param    object    $subject    The object to observe
     * @param    array    $config        An array that holds the plugin configuration
     * @since    1.5
     */

    function plgAuthenticationWaAuth(& $subject, $config){
        parent::__construct($subject, $config);
                 $this->name = "plgAuthenticatewaauth";
    }

    /**
     * This method should handle any authentication and report back to the subject
     *
     * @access    public
     * @param    array    $credentials    Array holding the user credentials
     * @param    array    $options        Array of extra options
     * @param    object    $response        Authentication response object
     * @return    boolean
     * @since    1.5
     */

    function onUserAuthenticate( $credentials, $options, & $response )
    {
      $plugin =&JPluginHelper::getPlugin('authentication', 'waauth');
      $params = $this->params;
      // Include the JLog class.
      jimport('joomla.log.log');

      // Initialise a basic logger with no options (once only).
      JLog::addLogger(
        array (
        // Sets file name
        'text_file' => 'waauth.errors.php'
        ),
        // Sets messages of all log levels to be sent to the file
        JLog::ALL,
        array('plg_waauth')
      );
      // Check on backed logins
      if(stripos($options['entry_url'], 'administrator') ){
        if ( (strcasecmp($options['group'], 'Public Backend') == 1) || ($this->params->get('backend_login') == 0) ){
          $response->status = JAuthentication::STATUS_FAILURE;
          $response->error_message = "You are not allow to login here.";
          JLog::add('Attempting backend login, denied:' . $credentials['username'],  JLog::INFO, 'plg_waauth');
          return false;
        }
      }
    
      $wa_accountId = $this->params->get('wa_account');
      $wa_clientId = $this->params->get('wa_clientId');
      $wa_clientSecret = $this->params->get('wa_clientSecret');
      $wa_apiKey = $this->params->get('wa_apiKey');
      $grp_audax = $this->params->get('group_audax_member');                    /* Group to add members to */
      $grp_audax_nonmember = $this->params->get('group_audax_nonmember');       /* Group to add lapsed/expired members to */
      $grp_audax_rideorganiser = $this->params->get('grp_audax_rideorganiser'); /* Group to add members in "Ride Organiser" WA Group to */
      $grp_audax_homologations = $this->params->get('grp_audax_homologations'); /* Group to add members in "Homologation Admins" WA Group to */
      
      JLog::add('Validate credentials :' . $credentials['username'],  JLog::INFO, 'plg_waauth');
	    try {
	      $waApiClient = WaApiClient::getInstance( $wa_accountId, $wa_clientId, $wa_clientSecret );
		    $waApiClient->initTokenByContactCredentials($credentials['username'], $credentials['password']);
	    } catch (Exception $e) {
	      $response->status = JAuthentication::STATUS_FAILURE;
  	    $response->error_message = "Can't authenticate user: " . $e->getMessage();
  	    JFactory::getApplication()->enqueueMessage("Can't authenticate user:" . $e->getMessage());
        JLog::add('Validate credentials :' . $credentials['username'] . ' Result: ' . $e->getMessage() ,  JLog::INFO, 'plg_waauth');
  	    return false;
  	  }
	    try { 
        JLog::add('Validate credentials :' . $credentials['username'] . ' Result: OK',  JLog::INFO, 'plg_waauth');
		    $queryParams = array(
			    '$async' => 'false' // execute request synchronously
	      ); 
	   	  $url = $waApiClient->accountURL . '/contacts/me?' . http_build_query($queryParams);
	      $contact = $waApiClient->makeRequest($url);
	      $waApiClientApp = WaApiClient::getInstance( $wa_accountId, $wa_clientId, $wa_clientSecret );
	   	  $waApiClientApp->initTokenByApiKey($wa_apiKey); 
	      $url = $contact["Url"]; 
	  	  $contact = $waApiClientApp->makeRequest( $url ) ;
	    } catch (Exception $e) {
	      $response->status = JAuthentication::STATUS_FAILURE;
	      $response->error_message = "Can't get user details: " . $e->getMessage();
	      JFactory::getApplication()->enqueueMessage("Can't retrieve user details: " . $e->getMessage());
	      JLog::add('Get user details :' . $credentials['username'] . ' Result: ' . $e->getMessage() ,  JLog::INFO, 'plg_waauth');
	      return false;
	    }
	
    	// Build array of field values. This is just to try and make it easy to use the WA data 
    	foreach( $contact['FieldValues'] as $Field ) {
    	  if ( is_array($Field['Value']) ) {
    	    if ( isset( $Field['Value']['Label'] ) ) {
    	      $val = $Field['Value']['Label'];
          } else {
            $val = array();
            foreach( $Field['Value'] as $Value ) {
              array_push( $val, $Value['Label'] );
            }
          }
        } else {
          val = $Field['Value'];
    	  }
    	  $uservals[$Field['FieldName']] = $val;
      }
    	if ( isset($contact['MembershipLevel']) && is_array($contact['MembershipLevel']) && isset($contact['MembershipLevel']['Name']) ){
    	  $uservals['Membership Description'] = $contact['MembershipLevel']['Name']; 
    	}
    	if ( $uservals['Deceased?'] == 'Yes' or $uservals['Suspended member'] == '1') {
    	  $response->status = JAuthentication::STATUS_EXPIRED;
    	  JFactory::getApplication()->enqueueMessage("Account has expired, login not allowed.");
    	  return;
      }
      $dateDue = new DateTime($uservals['Renewal due']);
      $dateNow = new DateTime();
      $response->username = $uservals['Member ID'];
	    $response->fullname = $uservals['First name'] . " " . $uservals['Last name'];
	    $response->email = $uservals['e-Mail'];
	    $record = JUser::getInstance(); // Bring the user in line with the rest of the system
    	if ( $id = intval(JUserHelper::getUserId($response->username) ) ) {
    		$record->load($id);
    		$record->setParam('email', $response->email );
    		$record->setParam('name', $response->fullname ); 
    		$record->save();
    	} else {
    	  $record->set('username', $response->username );
    		$record->set('email', $response->email );
    		$record->set('name', $response->fullname );
    		$record->set('password_clear',  $credentials['password'] );
        $configUsers = JComponentHelper::getParams('com_users');
        $defaultUserGroup = $configUsers->get('new_usertype', 2);
    		$record->set('groups', array($defaultUserGroup));
    		$record->save();
    		$id = intval(JUserHelper::getUserId($response->username) );
    		$record->load($id);
    	}

      
    	if ( $uservals['Membership status'] != 'Lapsed' && (($uservals['Renewal due'] != null && $dateNow < $dateDue) || $uservals['Membership Description'] == 'Life Membership' ) ) { 
    	  /* Members are not Lapsed status, and have a future renewal date ( but don't check for Life Members */ 
        JUserHelper::addUserToGroup($id, $grp_audax);
    		JUserHelper::removeUserFromGroup($id, $grp_audax_nonmember);
    		if ( isset($uservals['Group participation']) && is_array($uservals['Group participation']) ){
    		  if ( in_array( 'Homologation Admin', $uservals['Group participation'] ) ) {
    		    JUserHelper::addUserToGroup($id, $grp_audax_homologations);
          } else {
            JUserHelper::removeUserFromGroup($id, $grp_audax_homologations);
          }
          if ( in_array( 'Ride Organisers', $uservals['Group participation'] ) ) {
            JUserHelper::addUserToGroup($id, $grp_audax_rideorganiser);
          } else {
    		    JUserHelper::removeUserFromGroup($id, $grp_audax_rideorganiser);
          } 
        } /* end if Group particpation... set WA groups */
      } else {
        / * Remove non-curren members from the member group + any other groups we've defined. Add them to a non-member group */
    		JUserHelper::removeUserFromGroup($id, $grp_audax);
    		JUserHelper::addUserToGroup($id, $grp_audax_nonmember);
    		JUserHelper::removeUserFromGroup($id, $grp_audax_rideorganiser); 
    		JUserHelper::removeUserFromGroup($id, $grp_audax_homologations);
    		JFactory::getApplication()->enqueueMessage("As you are flagged as not a current member, not all functionality will be available. <P><B>Renewal Due: " . $dateDue->format('d/M/Y') . '</B>' );
    	}
    	
    	// Force reload from database - this seems necessary, dunno why...
	    $user = JFactory::getUser($id);
	    $session = JFactory::getSession();
	    $session->set('user', new JUser($user->id));

      // This code just sets some profiel variables but is dependent upon the variable names in WA
    	$db2 = &JFactory::getDBO();
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")  
    	. ", 'profile.gender', " 
    	. $db2->quote(json_encode($uservals['Gender'])) . " )"; 
    	$db2->setQuery($query);
    	$db2->query();
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")  
    	. ", 'profile.address1', " 
    	. $db2->quote(json_encode($uservals['Address1'])) . " )"; 
    	$db2->setQuery($query);
    	$db2->query();
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")  
    	. ", 'profile.address2', " 
    	. $db2->quote(json_encode($uservals['Address2'])) . " )"; 
    	$db2->setQuery($query);
    	$db2->query();
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")  
    	. ", 'profile.city', " 
    	. $db2->quote(json_encode($uservals['Suburb'])) . " )"; 
    	$db2->setQuery($query);
    	$db2->query();
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")  
    	. ", 'profile.postal_code', " 
    	. $db2->quote(json_encode($uservals['Postcode'])) . " )"; 
    	$db2->setQuery($query);
    	$db2->query();
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")
    	. ", 'profile.country', "
    	. $db2->quote(json_encode($uservals['Country'])) . " )";
    	$db2->setQuery($query);
    	$db2->query();
    	if ( $uservals['Country'] == 'Australia' ) {
    		$state = $uservals['State'];
    	} else {
    		$state = $uservals['Non-Australian State'];
    	}
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")
    	. ", 'profile.region', "
    	. $db2->quote(json_encode($state)) . " )";
    	$db2->setQuery($query);
    	$db2->query(); 
    	$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
    	.  $user->get("id")
    	. ", 'profile.phone', "
    	. $db2->quote(json_encode($uservals['Phone Mobile'])) . " )";
    	$db2->setQuery($query);
    	$db2->query();
    	
    	// Setup the respose data for joomla
    	if (!$response->fullname)
    	  $response->fullname = '';
    	if (!$response->email)
    	  $response->email = $credentials['username'];
    	$response->name = $response->fullname;
      $response->status = JAuthentication::STATUS_SUCCESS;;
      return true;
    }
}


    /**
     * API helper class. You can copy whole class in your PHP application.
     */
    class WaApiClient
    {
       const AUTH_URL = 'https://oauth.wildapricot.org/auth/token';
             
       private $tokenScope = 'auto';
       private static $_instance;
       private $token = null;
       private $refresh_token = null;
       public $accountURL;
       private $clientId = null;	// arg
       private $clientSecret = null; 	// arg
       private $accountId = null;	// arg
       
       public function initTokenByContactCredentials($userName, $password, $scope = null)
       {
          if ($scope) {
             $this->tokenScope = $scope;
          }
          $this->token = $this->getAuthTokenByAdminCredentials($userName, $password);
          if (!$this->token) {
             throw new Exception('Unable to get authorization token.');
          }
       }
       public function initTokenByApiKey($apiKey, $scope = null)
       {
          if ($scope) {
             $this->tokenScope = $scope;
          }
          $this->token = $this->getAuthTokenByApiKey($apiKey);
          if (!$this->token) {
             throw new Exception('Unable to get authorization token.');
          }
       }
       // this function makes authenticated request to API
       // -----------------------
       // $url is an absolute URL
       // $verb is an optional parameter.
       // Use 'GET' to retrieve data,
       //     'POST' to create new record
       //     'PUT' to update existing record
       //     'DELETE' to remove record
       // $data is an optional parameter - data to sent to server. Pass this parameter with 'POST' or 'PUT' requests.
       // ------------------------
       // returns object decoded from response json
       public function makeRequest($url, $verb = 'GET', $data = null)
       {
          if (!$this->token) {
             throw new Exception('Access token is not initialized. Call initTokenByApiKey or initTokenByContactCredentials before performing requests.');
          }
          $ch = curl_init();
          $headers = array(
             'Authorization: Bearer ' . $this->token,
             'Content-Type: application/json'
          );
          curl_setopt($ch, CURLOPT_URL, $url);
          
          if ($data) {
             $jsonData = json_encode($data);
             curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
             $headers = array_merge($headers, array('Content-Length: '.strlen($jsonData)));
          }
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $verb);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

          $jsonResult = curl_exec($ch);
          if ($jsonResult === false) {
             throw new Exception(curl_errno($ch) . ': ' . curl_error($ch));
          }
 //         var_dump($jsonResult); // Uncomment line to debug response
          curl_close($ch);
          return json_decode($jsonResult, true);
       }
       private function getAuthTokenByAdminCredentials($login, $password)
       {
          if ($login == '') {
             throw new Exception('login is empty');
          }
          $data = sprintf("grant_type=%s&username=%s&password=%s&scope=%s", 'password', urlencode($login), urlencode($password), urlencode($this->tokenScope));
//          throw new Exception('Change clientId and clientSecret to values specific for your authorized application. For details see:  https://help.wildapricot.com/display/DOC/Authorizing+external+applications');
          $authorizationHeader = "Authorization: Basic " . base64_encode( $this->clientId . ":" . $this->clientSecret);
          return $this->getAuthToken($data, $authorizationHeader);
       }
       private function getAuthTokenByApiKey($apiKey)
       {
          $data = sprintf("grant_type=%s&scope=%s", 'client_credentials', $this->tokenScope);
          $authorizationHeader = "Authorization: Basic " . base64_encode("APIKEY:" . $apiKey);
          return $this->getAuthToken($data, $authorizationHeader);
       }
       private function getAuthToken($data, $authorizationHeader)
       {
          $ch = curl_init();
          $headers = array(
             $authorizationHeader,
             'Content-Length: ' . strlen($data)
          );
          curl_setopt($ch, CURLOPT_URL, WaApiClient::AUTH_URL);
          curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		  $response = curl_exec($ch);
		  if ($response === false) {
             throw new Exception(curl_errno($ch) . ': ' . curl_error($ch));
          }
         
		  
          $result = json_decode($response , true);
          curl_close($ch);
          if ( isset( $result['error_description'] ) ) {
          	throw new Exception( $result['error_description'] );
          }
          $this->accountURL = "https://api.wildapricot.org/v2/accounts/" . $result['Permissions'][0]['AccountId'];
          $this->refresh_token = $result['refresh_token'];
          return $result['access_token'];
       }
       public static function getInstance($accountId,$clientId,$clientSecret)
       {
          if (!is_object(self::$_instance)) {
             self::$_instance = new self($accountId,$clientId,$clientSecret);
          }
          return self::$_instance;
       }
       public final function __clone()
       {
          throw new Exception('It\'s impossible to clone singleton "' . __CLASS__ . '"!');
       }
       private function __construct($accountId,$clientId,$clientSecret)
       {
          if (!extension_loaded('curl')) {
             throw new Exception('cURL library is not loaded');
          }
 	  $this->accountId = $accountId;
 	  $this->clientId = $clientId;
 	  $this->clientSecret = $clientSecret;
          if ( $this->accountId != null ) 
          	$this->accountURL = "https://api.wildapricot.org/v2/accounts/" . $this->accountId;
       }
       public function __destruct()
       {
          $this->token = null;
       }
    }

?>
