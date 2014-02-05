<?php

    interface Fluff_Moderator {
        /**
         * Constructor
         *
         * @access public
         * @author Chris Bestwick
         */
        public function __construct($wp, $wpdb);
        
        /**
     	* Sets up the plugin after initial installation.
     	* 
     	* Creates any necessary db tables and values and returns an array of tokens on success or errors on failure.
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_setup();
        
        /**
     	* Creates a digest token.
     	* 
     	* Returns an array of digest token data on success or errors on failure.
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_digest();
        
        /**
     	* Authenticates a request using digest token 
     	* 
     	* Returns an array containing a boolean true on success or errors on failure
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
    	* 
     	*/
        public function fluff_authenticate();
        
       /**
     	* Gets unapproved comments
     	* 
     	* Returns an array containing comment data on success or errors on failure
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_poll();
        
        /**
     	* Approves a comment
     	* 
     	* Returns an array containing a boolean true on success or errors on failure
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_approve();
        
        /**
     	* Deletes a comment
     	* 
     	* Returns an array containing a boolean true on success or errors on failure
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_delete();
        
        /**
     	* Edits a comment
     	* 
     	* Returns an array containing a boolean true on success or errors on failure
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_edit();
        
        /**
     	* Returns the module version
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_version();
        
        /**
     	* Returns an error
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return array
     	*/
        public function fluff_error($message);
    
        /**
     	* Returns a json encoded string
     	*
     	* @access public
     	* @author Chris Bestwick
     	* @return string
     	*/
        public function fluff_encode($response);
    }