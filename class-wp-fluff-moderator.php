<?php
    
    require_once 'class-fluff-moderator.php';
    require_once 'encode.php';
    
    class WP_Fluff_Moderator implements Fluff_Moderator {
        
    	/**
         * An array to hold all response data from requests including any errors
         *
         * @access protected
         * @var array
         * 
         * possible array indices
         * $response['unauthorised'] - set to true if the user is not authenticated during setup
         * $response['cannot_moderate'] - set to true during setup if user does not have moderation rights
         * $response['duplicate'] - set to true if the setup has previously run ok
         * $response['moderation_off'] - set to true if comment moderation has not been enabled in wordpress
         * $response['error'] - set by fluff_error() in the event of db problems, a string containing a meaningful error message
         * $response['fluff'] - the fluff token returned during a succesful setup, as a string
         * $response['user'] - the fluff user token returned during a successful setup, as a string
         * $response['digest_data'] - an array containing the fluff digest token, $response['digest_data']['token'] 
         * and timestamp, $response['digest_data']['time'] returned by the fluff_digest method, as a string
         * $response['unapproved_request'] - set to true if the digest token request process is not authenticated
         * $response['data'] - contains the unapproved comment data, as an array
         * $response['number'] - the number of unapproved comments
         * $response['success'] -  set to true if the approve, delete or edit methods execute sucessfully
         * $response['version'] - contains the version number 
         * 
         * the $response array is returned to the fluff_moderator.php calling methods and there 
         * encoded using the fluff_encode method before being printed to the browser, i.e. currently encoded as a json string
         */
        protected $response;
        
        /**
         * The wp super object
         *
         * @access protected
         * @var object
         */
        protected $wp;
        
        /**
         * The wordpress database object
         *
         * @access protected
         * @var object
         */
        protected $wpdb;
        
        /**
         * The version number of the plugin
         *
         * @access protected
         * @var integer
         */
        protected $version_number;
        
        /**
         * Constructor
         *
         * @access
         * @author Chris Bestwick
         * @param object $wp
         * @param object $wpdb
         */
        public function __construct($wp,$wpdb) {
            $this->wp = $wp;
            $this->wpdb = $wpdb;
            $this->response = array();
            $this->version_number = 1;
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_setup()
         */
        public function fluff_setup() {            
//          authenticate username and password
            $authentic_user = wp_authenticate( $this->wp->query_vars['dog'], $this->wp->query_vars['fish'] );
            
//          if the user's not authentic, set a message            
            if (false == is_a($authentic_user, 'WP_User')) {
                $this->response['unauthorised'] = true;
            } else {                                
//              first check comment moderation is turned on
                $moderation_on = $this->wpdb->get_var( "SELECT option_value 
                    FROM ".$this->wpdb->prefix."options 
                    WHERE option_name = 'comment_moderation'" );
                if ( false == $moderation_on ) {
                    $this->response['moderation_off'] = true; 
                    return $this->response;
                }  

//              get the user id for the username
                $user_id = $authentic_user->data->ID;  

//              check whether the user has the right to moderate comments (they need a wp_user_level of 7 or 10 to do so)
                $user_level = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT meta_value 
            	FROM ".$this->wpdb->prefix."usermeta WHERE user_id = %d AND meta_key = 'wp_user_level'", $user_id ) );

                if ( false == ($user_level === '7' || $user_level === '10') ) {
                    $this->response['cannot_moderate'] = true; 
                    return $this->response;
                }
            
//              check whether the plugin has already been set up by trying to get the fluff token
                $token_check = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT meta_value 
            	FROM ".$this->wpdb->prefix."usermeta WHERE user_id = %d AND meta_key = 'fluff_token'", $user_id ) );

//              if there is a token, set an error message and return            
                if ( false == empty($token_check)) {
                    $this->response['duplicate'] = true; 
                    return $this->response;  
                }                      

//              generate fluff token and fluff user token and store them in the db
                $fluff_token = $this->random_string(128);
                $fluff_user_token = $this->random_string(128);
            
                if( true == $this->wpdb->insert( $this->wpdb->prefix."usermeta", array(
                    'user_id' => $user_id, 
                    'meta_key' => 'fluff_token', 
                    'meta_value' => $fluff_token,), array(
                    '%s',
                    '%s'
                    )
                    ) ) {
                    $this->response['fluff'] = $fluff_token;
                } else {
                    $this->fluff_error('db setup error');
                    return $this->response;
                }
    
                if( true == $this->wpdb->insert( $this->wpdb->prefix."usermeta", array(
                    'user_id' => $user_id, 
                    'meta_key' => 'fluff_user_token', 
                    'meta_value' => $fluff_user_token,), array(
                    '%s',
                    '%s'
                    )
                    ) ) {
                    $this->response['user'] = $fluff_user_token;
                } else {
                    $this->fluff_error('db setup error');
                    return $this->response;
                }
            }
            
            return $this->response;         
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_digest()
         */
        public function fluff_digest() {
//          generate the digest code using custom function below 
            $digest = $this->random_string(128);
                                
//          store it in an array, with a timestamp, then serialize
            $digest_created = time(); 
            $digest_data = array();
            $digest_data['token'] = $digest;
            $digest_data['time'] = $digest_created;
            $serialised_digest_data = serialize( $digest_data );
            
//          get the user id based on the fluff user token            
            $user_id = $this->wpdb->get_var( $this->wpdb->prepare( 
	            "SELECT user_id 
				FROM ".$this->wpdb->prefix."usermeta 
				WHERE meta_key = 'fluff_user_token'
				AND meta_value = %s", 
	            $this->wp->query_vars['local-user']
                ) );
            
//          set an error if the query failed
            if ( false === $user_id ) {
                $this->fluff_error('db digest error');
                return $this->response;
            }   

//          check for any orphaned digest tokens left from a previously failed authentication attempt
            $result = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT meta_value 
                FROM ".$this->wpdb->prefix."usermeta 
                WHERE user_id = %d 
                AND meta_key = 'fluff_digest_token'", $user_id ) );
                                          
//          if there's a db error      
            if (false === $result )
            {
                $this->fluff_error('db digest token check error');
                return $this->response;
            }  

//          if there are tokens, delete them 
            if (true == $result)
            {           
                $delete = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM ".$this->wpdb->prefix."usermeta 
                	WHERE user_id = %d 
                    AND meta_key = 'fluff_digest_token'", $user_id ) ); 

//              set an error if the query failed
                if ( false == $delete ) {
                    $this->fluff_error('db digest delete error');
                    return $this->response;
                } 
            }          

//          save the serialized digest data to the db      
            if( true == $this->wpdb->insert( $this->wpdb->prefix."usermeta", array(
                'user_id' => $user_id, 
                'meta_key' => 'fluff_digest_token', 
                'meta_value' => $serialised_digest_data,), array(
                '%s',
                '%s'
                )
                ) ) {
                $this->response['digest_data'] = $digest_data;  
                return $this->response;
            } else {
                $this->fluff_error('db digest error');
                return $this->response;
            } 
        }
                
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_authenticate()
         * based on ircmaxell's post in http://stackoverflow.com/questions/5373016/php-passing-data-from-a-site-to-another-site-securely
         */
        public function fluff_authenticate() {
//      parse the auth-field query variable into user token, hash and digest token        
        $auth_field = $this->wp->query_vars['auth-field'];        
        list ( $user, $hash, $token ) = explode( ':', $auth_field );
                        
//      get the user id based on the fluff user token stored in $user
        $user_id = $this->wpdb->get_var( $this->wpdb->prepare ("SELECT user_id 
            FROM ".$this->wpdb->prefix."usermeta 
            WHERE meta_key = 'fluff_user_token' 
            AND meta_value = %s", $user ) );
                
//      set an error if the query failed
        if ( false === $user_id ) {
            $this->fluff_error('db authenticate request error');
        } else {        
//          get the fluff_token from the db
            $fluff_token = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT meta_value 
                FROM ".$this->wpdb->prefix."usermeta 
                WHERE user_id = %d 
                AND meta_key = 'fluff_token'", $user_id ) );
            
//          set an error if the query failed
            if ( false === $fluff_token ) {
                $this->fluff_error('db authenticate request error');
                return $this->response;
            } else {        
        
//              get the digest data from the db
                $digest_data = $this->wpdb->get_var( $this->wpdb->prepare( "SELECT meta_value 
                    FROM ".$this->wpdb->prefix."usermeta 
                    WHERE user_id = %d 
                    AND meta_key = 'fluff_digest_token'", $user_id ) );
                
//              set an error if the query failed
                if ( false === $digest_data ) {
                    $this->fluff_error('db authenticate request error');
                    return $this->response;
                } 
                
                $unserialized_digest_data = unserialize( $digest_data );
            }
        }
        
//      delete the digest code from the db whether fluffAuthenticate() succeeds or fails; it's one-time use only 
//      (if this fails, any new digest request takes care of any orphaned digest tokens)   
        $delete = $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM ".$this->wpdb->prefix."usermeta 
            WHERE user_id = %d 
            AND meta_key = 'fluff_digest_token'", $user_id ) ); 

//      set an error if the query failed
        if ( false === $delete ) {
            $this->fluff_error('db authenticate request error');
        }
        
//      if we got both the tokens, compare the digest token with the one sent in the cURL request, and check it's not too old 
        if ( true == isset( $fluff_token ) && true == isset( $digest_data ) ) {
            $token_life_secs = 20;
            
            if ( ( $unserialized_digest_data['token'] == $token ) && ( time() - $unserialized_digest_data['time'] > $token_life_secs == false ) ) {
//              if all's ok, do this final check
                $stub = $user . ':' . $fluff_token;
                if ( $hash == hash_hmac( 'sha256', $stub, $token ) ) {
//                  authenticated user
                    return true;
                } else {
                    $this->response['unapproved_request'] = true;               
                }           
            }
        }  
        
        return $this->response;              
    }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_poll()
         */
        public function fluff_poll() {                          
//          get the unapproved comments and the title of the posts they relate to
            $results = $this->wpdb->get_results( "SELECT ".$this->wpdb->prefix."comments.comment_ID, ".$this->wpdb->prefix."comments.comment_author, ".$this->wpdb->prefix."comments.comment_date, ".$this->wpdb->prefix."comments.comment_content, ".$this->wpdb->prefix."posts.ID, ".$this->wpdb->prefix."posts.post_title 
            	FROM ".$this->wpdb->prefix."comments 
            	INNER JOIN ".$this->wpdb->prefix."posts 
            	ON ".$this->wpdb->prefix."comments.comment_post_ID = ".$this->wpdb->prefix."posts.ID 
            	AND ".$this->wpdb->prefix."comments.comment_approved = 0", ARRAY_A );
            
//          set an error if the query failed
            if ( false === $results ) {
                $this->fluff_error('db polling error');
                return $this->response;
            }            
                
            if ( $this->wpdb->num_rows > 0) {                
//              standardise the array keys for the comment data
                $standard_row = array();
                foreach ($results as $row) {
                    $standard_row['id'] = $row['comment_ID'];
                    $standard_row['author'] = \Encoding\encodeString($row['comment_author']);
                    $standard_row['date'] = $row['comment_date'];
                    $standard_row['content'] = \Encoding\encodeString($row['comment_content']);
                    $standard_row['post_title'] = \Encoding\encodeString($row['post_title']);
                                        
                    $this->response['data'][] = $standard_row;
                }                                 
            }
            
            $this->response['number'] = $this->wpdb->num_rows;
            return $this->response;
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_approve()
         */
        public function fluff_approve() {
            if ( true == isset( $this->wp->query_vars['comment-id']) ) {
//              approve the comment
                $comment_ID = $this->wp->query_vars['comment-id'];
                    
                if( true == $this->wpdb->update( $this->wpdb->prefix."comments", array(
                'comment_approved' => '1',
                ), array(
                'comment_ID' => $comment_ID,
                ) ) ) {
                    $this->response['success'] = true;     
                } else {
                    $this->fluff_error('db approve error');
                } 

                return $this->response;
            }
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_delete()
         */
        public function fluff_delete() {
            if ( true == isset( $this->wp->query_vars['comment-id'] ) ) {
//              delete the comment
                $comment_ID = $this->wp->query_vars['comment-id'];
                        
                if ( true == $this->wpdb->query( $this->wpdb->prepare( "DELETE FROM ".$this->wpdb->prefix."comments 
                    WHERE comment_ID = %d", $comment_ID ) ) ) { 
                    $this->response['success'] = true;     
                } else {
                    $this->fluff_error('db delete error');
                } 
    
                return $this->response;           
            }
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_edit()
         */
        public function fluff_edit() {
            if ( true == isset( $this->wp->query_vars['comment-id'] ) && isset( $this->wp->query_vars['comment-content']) ) {
//              edit the comment (and approve it too)
                $comment_ID = $this->wp->query_vars['comment-id'];
//              wp->query_vars adds slashes!!, so get rid of them               
                $comment_content = stripslashes( sanitize_text_field( $this->wp->query_vars['comment-content'] ) );
                                                    
                if( true == $this->wpdb->update( $this->wpdb->prefix."comments", array(
                'comment_approved' => '1', 'comment_content' => $comment_content,
                ), array(
                'comment_ID' => $comment_ID,
                ) ) ) {
                    $this->response['success'] = true;     
                } else {
                    $this->fluff_error('db edit error');
                } 

                return $this->response;         
            }
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_version()
         */
        public function fluff_version() {
            $this->response['version'] = $this->version_number;
            return $this->response; 
        }
        
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_error()
         */
        public function fluff_error($message) {
            $this->response['error'] = $message;
            return $this->response;        
        }
    
        /* (non-PHPdoc)
         * @see Fluff_Moderator::fluff_encode()
         */
        public function fluff_encode($response) {
            return json_encode($response);
        } 
        
        /**
         * Generate random string
         *
         * @access private
         * @author Rathienth Baskaran, http://stackoverflow.com/questions/1846202/php-how-to-generate-a-random-unique-alphanumeric-string
         * @param integer $length
         */    
        private function random_string( $length ) {
            $key = '';
            $keys = array_merge( range(0, 9), range('a', 'z') );
        
            for ( $i = 0; $i < $length; $i++ ) {
                $key .= $keys[array_rand($keys)];
            }
        
            return $key;
        }   
    }