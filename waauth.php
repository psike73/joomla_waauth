<?php

/**
 * @version    $Id: extauth.php 6666 2013-03-29 12:00:00 Marcos $
 * @package    Marcos.Joomla
 * @subpackage Plugins
 * @license    GNU/GPL
 */

// Check to ensure this file is included in Joomla!

defined('_JEXEC') or die();
jimport('joomla.event.plugin');
/**
 * External MySQL Database Authentication Plugin.  Based on the example.php plugin in the Joomla! Core installation
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
   		array(
      			'text_file' => 'waauth.errors.php'
   		),
   		JLog::ALL,
   		array('plg_waauth')
	);

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
	$grp_audax = $this->params->get('group_audax_member');
	$grp_audax_nonmember = $this->params->get('group_audax_nonmember');
        $status_profile_variable = trim($this->params->get('status_profile_variable'));
        $status_profile_member = $this->params->get('status_profile_member');
        $status_profile_nonmember = $this->params->get('status_profile_nonmember');
        $copy_profile_variables = $this->params->get('copy_profile_variables');
        $certificate_path = trim($this->params->get('certificate_path'));
        if ( $certificate_path === '' ) { 
        	$certificate_path = null;
        }
        $status_profile_variable_value = null;

	JLog::add('Validate credentials :' . $credentials['username'],  JLog::INFO, 'plg_waauth');
	try { 
		$waApiClient = WaApiClient::getInstance( $wa_accountId, $wa_clientId, $wa_clientSecret, $certificate_path );
		$waApiClient->initTokenByContactCredentials($credentials['username'], $credentials['password']);
	} catch (Exception $e) {
		$response->status = JAuthentication::STATUS_FAILURE;
		$response->error_message = "Can't authenticate user: " . $e->getMessage();
		JFactory::getApplication()->enqueueMessage("Can't authenticate user:" . $e->getMessage());
		JLog::add('Failed login:' . $credentials['username'] . ' Result: ' . $e->getMessage() ,  JLog::INFO, 'plg_waauth');
		return false;
	}
	try { 
		JLog::add('Validate credentials :' . $credentials['username'] . ' Result: OK',  JLog::INFO, 'plg_waauth');
		$queryParams = array(
			'$async' => 'false' 
	       	); 
		$url = $waApiClient->accountURL . '/contacts/me?' . http_build_query($queryParams);
		$contact = $waApiClient->makeRequest($url);
		$waApiClientApp = WaApiClient::getInstance( $wa_accountId, $wa_clientId, $wa_clientSecret, $certificate_path );
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
	
	// Build array of field values.
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
			$val = $Field['Value'];
		}
		$uservals[$Field['FieldName']] = $val;
	}
	if ( isset($contact['MembershipLevel']) && is_array($contact['MembershipLevel']) && isset($contact['MembershipLevel']['Name']) ){
		$uservals['Membership Description'] = $contact['MembershipLevel']['Name']; 
	}

	$uservals['Id'] = (string)$contact['Id'];
	$uservals['FirstName'] =  $contact['FirstName'];
	$uservals['LastName'] =  $contact['LastName'];
	$uservals['Email'] =  $contact['Email'];

	if ( $uservals['Deceased?'] == 'Yes' or $uservals['Suspended member'] == '1') {
		$response->status = JAuthentication::STATUS_EXPIRED;
		JFactory::getApplication()->enqueueMessage("Account has expired, login not allowed.");
		return false;
	}
	$dateDue = new DateTime($uservals['Renewal due']);
	$dateNow = new DateTime();

	// JFactory::getApplication()->enqueueMessage("Your member number is " .  $uservals['Member ID']);
	$response->username = (string)$uservals['Id'];
	$response->fullname = $contact['FirstName'] . " " . $contact['LastName'];
	$response->email = $contact['Email'];
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
		if ( ! $record->save() ) { 
			JFactory::getApplication()->enqueueMessage("Failed to create account: " .JText::_( $record->getError()));
			$response->status = JAuthentication::STATUS_EXPIRED;
			return false;
		}
		$id = intval(JUserHelper::getUserId($response->username) );
		$record->load($id);
	}
	
	$list_groups 	 = json_decode( $params->get('groups_templates'),true);
	$list_lifemembers = json_decode( $params->get('lifemembers_templates'),true);
	if ( $uservals['Membership status'] != 'Lapsed' && (($uservals['Renewal due'] != null && $dateNow < $dateDue) || in_array($uservals['Membership Description'], $list_lifemembers['lifemember_level']) ) ) { 
		JUserHelper::addUserToGroup($id, $grp_audax);
		JUserHelper::removeUserFromGroup($id, $grp_audax_nonmember);
		if ( $status_profile_variable != '' ) { 
			$status_profile_variable_value = $status_profile_member;
		}

		if ( isset($uservals['Group participation']) && is_array($uservals['Group participation']) ){
			foreach( $list_groups['grp_wildapricot'] as $list_groups_idx => $list_group ) {
				if ( in_array( $list_group, $uservals['Group participation'] ) ) {
					JUserHelper::addUserToGroup($id, $list_groups['grp_joomla'][$list_groups_idx]);
				} else {
					JUserHelper::removeUserFromGroup($id, $list_groups['grp_joomla'][$list_groups_idx]);
				}
			}
		}
	} else {
		JUserHelper::removeUserFromGroup($id, $grp_audax);
		JUserHelper::addUserToGroup($id, $grp_audax_nonmember);
		foreach( $list_groups['grp_wildapricot'] as $list_groups_idx => $list_group ) {                               
			JUserHelper::removeUserFromGroup($id, $list_groups['grp_joomla'][$list_groups_idx]);
		}
		if ( $status_profile_variable != '' ) { 
			$status_profile_variable_value = $status_profile_member;
		}
		JFactory::getApplication()->enqueueMessage("As you are flagged as not a current Audax member, not all functionality will be available. <p>You can <a href=http://membership.audax.org.au/>renew your membership or join</a> online to obtain full access, or make payment to complete your membership application or renewal.<P><B>Renewal Due: " . $dateDue->format('d/M/Y') . '</B>' );
	} 
	// Force reload from database
	$user = JFactory::getUser($id);
	$session = JFactory::getSession();
	$session->set('user', new JUser($user->id));
                  
        if ( $copy_profile_variables == 1 ) { 
		$list_fields = json_decode( $params->get('fields_templates'),true);
		if ( is_array($list_fields) && is_array($list_fields['fld_wildapricot']) ) { 
			$db2 = &JFactory::getDBO();
			foreach( $list_fields['fld_wildapricot'] as $list_fields_idx => $list_field ) {                               
				if ( $uservals[$list_field] != null ) { 
					$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
					.  $user->get("id")  
					. ", 'profile." . $list_fields['fld_joomla_profile'][$list_fields_idx] . "' , " 
					. $db2->quote(json_encode($uservals[$list_field])) . " )"; 
					$db2->setQuery($query);
					$db2->query();
				}
			}
		}
		if ( $status_profile_variable != '' ) {
			$query = "REPLACE INTO #__user_profiles ( user_id, profile_key, profile_value ) VALUES ( "
			.  $user->get("id")  
			. ", 'profile.$status_profile_variable', " 
				. $db2->quote(json_encode($status_profile_variable_value)) . " )";
			$db2->setQuery($query);
			$db2->query();
		}
	}

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
       private $certificatePath = null;
       
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
          if ( $this->certificatePath != null ) {
	    curl_setopt($ch, CURLOPT_CAINFO, $this->certificatePath );//'E:/hshome/audax/cacert.pem');
	  }
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

          if ( $this->certificatePath != null ) {
	    curl_setopt($ch, CURLOPT_CAINFO, $this->certificatePath );//'E:/hshome/audax/cacert.pem');
	  }

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
       public static function getInstance($accountId,$clientId,$clientSecret,$certificatePath)
       {
          if (!is_object(self::$_instance)) {
             self::$_instance = new self($accountId,$clientId,$clientSecret,$certificatePath);
          }
          return self::$_instance;
       }
       public final function __clone()
       {
          throw new Exception('It\'s impossible to clone singleton "' . __CLASS__ . '"!');
       }
       private function __construct($accountId,$clientId,$clientSecret,$certificatePath)
       {
          if (!extension_loaded('curl')) {
             throw new Exception('cURL library is not loaded');
          }
 	  $this->accountId = $accountId;
 	  $this->clientId = $clientId;
 	  $this->clientSecret = $clientSecret;
 	  $this->certificatePath = $certificatePath;
          if ( $this->accountId != null ) 
          	$this->accountURL = "https://api.wildapricot.org/v2/accounts/" . $this->accountId;
       }
       public function __destruct()
       {
          $this->token = null;
       }
    }

?>
