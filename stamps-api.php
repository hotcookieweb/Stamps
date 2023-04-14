<?php
/*
 * Created: 3/28/23
 * Author: Anton Roug
 * see https: //developer.stamps.com/rest-api/reference/serav1.html#tag/auth
 * Name: stamps-api
 * description: PHP implement of stamps.com REST API
 * version: 1.1
 * category: Class
 */
Class Stamps_API {
  public function name() {
    printf ("Stamps API");
  }
  /**
 * @var stamps_api - the single instance of the class
 * @since 1.0
 */
 protected static $_instance = null;

  /**
  * Main yoursite_doordash Instance
  *
  * Ensures only one instance of yoursite_doordash is loaded or can be loaded.
  *
  * @static
  * @see
  * @return stamps_api - Main instance
  * @since 1.0

  */
  public static function instance() {
    if ( is_null( self::$_instance ) ) {
      self::$_instance = new self();
    }
    return self::$_instance;
  }
  /**
  * Cloning is forbidden.
  *
  * @since 1.0
  */
  public function __clone() {
     _doing_it_wrong( __FILE__, __( 'Cloning this object is forbidden.', 'wc-error' ), '1.0' );
  }

  /**
  * Unserializing instances of this class is forbidden.
  *
  * @since 1.0
  */
  public function __wakeup() {
   _doing_it_wrong( __FILE__, __( 'Unserializing instances of this class is forbidden.', 'wc-error' ), '1.0' );
  }

  public function __construct() {
    if (get_home_url()=='https://yoursite.com') {
      $this->stampsenv = 'production';
      $this->authhost = 'https://signin.stampsendicia.com';
      $this->signinurl = 'https://signin.stampsendicia.com/authorize';
      $this->tokenurl = 'https://signin.stampsendicia.com/oauth/token';
      $this->seraurl = 'https://api.stampsendicia.com/sera';
      $this->client_id = 'your client id';
      $this->client_secret = 'your client secret';
      $this->redirect_uri = 'https://yoursite.com/redirect_uri';
    }
    else {
      $this->stampsenv = 'sandbox';
      $this->authhost = 'https://signin.testing.stampsendicia.com';
      $this->signinurl = 'https://signin.testing.stampsendicia.com/authorize';
      $this->tokenurl = 'https://signin.testing.stampsendicia.com/oauth/token';
      $this->seraurl = 'https://api.testing.stampsendicia.com/sera';
      $this->client_id = 'your client id';
      $this->client_secret = 'your client secret';
      if (get_home_url()=='https://staging.yoursite.com') {
        $this->redirect_uri = 'https://staging.yoursite.com/redirect_uri';      
      }
      else {
        $this->redirect_uri = 'https://yoursite.local/redirect_uri';      
      }
    }
  }
  

  public function validate_address($data) {
    $access_token = $this->get_token();
    if (is_array($access_token)) { // indicates error on get_token call
      return ($access_token);
    }

    $header = array(
      "Content-type: application/json",
      "Authorization: Bearer " . $access_token
    );

    return $this->do_curl(
      'POST',
      $this->seraurl . "/v1/addresses/validate",
      $header,
      json_encode(array($data['to_address']))
    );
  }

  public function get_rates($data) {
    $access_token = $this->get_token();
    if (is_array($access_token)) { // indicates error on get_token call
      return ($access_token);
    }

    $header = array(
      "Content-type: application/json",
      "Authorization: Bearer " . $access_token
    );

    $result = $this->do_curl(
      'POST',
      $this->seraurl . "/v1/rates?carriers=" . $data['carrier'],
      $header,
      json_encode($data)
      
    );
    return $result;
  }

  public function create_label ($data) {
    $access_token = $this->get_token();
    if (is_array($access_token)) { // indicates error on get_token call
      return ($access_token);
    }

    $header = array(
      "Content-type: application/json",
      "Authorization: Bearer " . $access_token,
      "Idempotency-Key: " . wp_generate_uuid4() //wordpress function
    );
    $result = $this->do_curl(
      'POST',
      $this->seraurl . "/v1/labels",
      $header,
      json_encode($data)
    );
    return $result;
  }

  public function void_label ($data) {
    $access_token = $this->get_token();
    if (is_array($access_token)) { // indicates error on get_token call
      return ($access_token);
    }
    $header = array(
      "Authorization: Bearer " . $access_token,
      "Content-Length: 0"
    );
    $result = $this->do_curl(
      'PUT',
      $this->seraurl . "/v1/labels/" . $data['label_id'] . "/void",
      $header
    );
    return $result;
  }

  public function track_package ($data) {
    $access_token = $this->get_token();
    if (is_array($access_token)) { // indicates error on get_token call
      return ($access_token);
    }
    $header = array(
      "Content-type: application/json",
      "Authorization: Bearer " . $access_token
    );
    $result = $this->do_curl(
      'GET',
      $this->seraurl . "/v1/tracking?carrier=" . $data['carrier'] . "&tracking_number=" . $data['tracking_number'],
      $header
    );
    return $result;
  }

  public function add_funds ($data) {
    $access_token = $this->get_token();
    if (is_array($access_token)) { // indicates error on get_token call
      return ($access_token);
    }

    $header = array(
      "Content-type: application/json",
      "Authorization: Bearer " . $access_token,
      "Idempotency-Key: " . wp_generate_uuid4() //wordpress function
    );

    $result = $this->do_curl(
      'POST',
      $this->seraurl . "/v1/balance/add-funds",
      $header,
      json_encode($data)
    );
    return $result;
  }

  public function get_token ($code = null) { // called with code as a result of redirect_uri call back
    if (!empty($code)) {
      $token['code'] = $code;
      $token['refresh_token'] = $code;
      $body = 'grant_type=authorization_code&client_id=' . $this->client_id . '&client_secret=' . $this->client_secret . '&code=' . $code . '&redirect_uri=' . urlencode($this->redirect_uri);
    }
    else {
      $token = get_option('stamps_token'); //wordpress
      if ($token) {
        if ($token['expires_in'] >= time()) {
          return $token['access_token'];
        }
      }
      else {
        $redirect_url = $this->signinurl . '?client_id=' . $this->client_id . '&response_type=code&redirect_uri=' . urlencode($this->redirect_uri);
        $return['status'] = 302;
        $return['errors'][0]['error_code'] = 302;
        $return['errors'][0]['error_message'] = "Stamps token setup, complete stamps login below and retry";
        $return['errors'][1]['error_code']  = "url";
        $return['errors'][1]['error_message']  = '<a onclick="window.open(\'' . $redirect_url . '\', \'popup\', \'location=0,width=500,height=400,left=500,top=55\'); return false;">Stamps login</a>'?>
        <?php return $return; 
      }
      $body = 'grant_type=refresh_token&client_id=' . $this->client_id . '&client_secret=' . $this->client_secret . '&refresh_token=' . $token['refresh_token'];
    }

    /* refresh access token */
    $header = array(
      "Content-type: application/x-www-form-urlencoded"
    );

    $result = $this->do_curl(
      'POST',
      $this->tokenurl,
      $header,
      $body
    );

    switch ($result['status']) {
      case '200':
        $token['access_token'] = $result['access_token'];
        if (isset($result['refresh_token'])) {
          $token['refresh_token'] = $result['refresh_token'];
        }
        $token['expires_in'] = $result['expires_in'] + time() - 1;
        update_option('stamps_token',$token); //wordpress
        return ($token['access_token']);

      default:
        return $result;
    }
  }

  public function do_curl($type, $url, $request_header = null, $request_body = null ) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type );
    if ($request_header) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, $request_header);
    }
    if($request_body) {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    if ($result == '0') {
      $result_array['status'] = 0;
      $result_array['errors'][0]['error_code'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $result_array['errors'][0]['error_message'] = curl_error($ch);
    }
    else {
      $result_array = json_decode($result, true);
      $result_array['status'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    }
    $result_array['curl_url'] = $url;
    if($request_body) {
      $result_array['curl_body'] = $request_body;
    }

    curl_close($ch);
    return($result_array);
  }
}
