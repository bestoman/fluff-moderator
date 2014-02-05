<?php

    /*
	Plugin Name: Fluff Moderator
	Description: Moderates comments remotely.
	Version: 1.0
	Author: Chris Bestwick
	License: GPL2
	*/

//  Everything happens here - polling the db for unapproved comments, and approving, deleting and editing those comments

    require_once 'class-wp-fluff-moderator.php';

//  set up the routing so we can access this file through wordpress setup, rather than directly
    function fluff_moderator_parse_request( $wp ) {   
            
//      process requests with "fluff-moderator" in the POST query string
        if ( true == isset ( $wp->query_vars['fluff-moderator']) ) { 

            global $wpdb;

//          instantiate a moderator object
            $fluff_moderator = new WP_Fluff_Moderator($wp, $wpdb);
                                    
//          depending on what operation is called in the query, take the relevant action           
            switch( $wp->query_vars['fluff-moderator'] ) { 
                
                case 'setup':
                    $setup = $fluff_moderator->fluff_setup();
                    print $fluff_moderator->fluff_encode( $setup );
                    exit;
                    
                case 'digest':
                    $digest_data = $fluff_moderator->fluff_digest();
                    print $fluff_moderator->fluff_encode( $digest_data );           
                    exit;                                                
                
                case 'poll': 
//                  authenticate first
                    $response = $fluff_moderator->fluff_authenticate();                
                    if ( true === $response ) {
//                      if the request is authentic, poll                    
                        $response = $fluff_moderator->fluff_poll();    
                    }
//                  print the results of the poll, or any error                
                    print $fluff_moderator->fluff_encode($response);
                    exit;
                                   
                case 'approve':
//                  authenticate first
                    $response = $fluff_moderator->fluff_authenticate();                
                    if ( true === $response ) {
//                      if the request is authentic, approve                    
                        $response = $fluff_moderator->fluff_approve();    
                    }
//                  print the results of the approve method, or any error                
                    print $fluff_moderator->fluff_encode($response);
                    exit;
                
                case 'delete':
//                  authenticate first
                    $response = $fluff_moderator->fluff_authenticate();                
                    if ( true === $response ) {
//                      if the request is authentic, delete                    
                        $response = $fluff_moderator->fluff_delete();    
                    }
//                  print the results of the delete method, or any error                
                    print $fluff_moderator->fluff_encode($response);
                    exit;
                
                case 'edit':
//                  authenticate first
                    $response = $fluff_moderator->fluff_authenticate();                
                    if ( true === $response ) {
//                      if the request is authentic, edit                    
                        $response = $fluff_moderator->fluff_edit();    
                    }
//                  print the results of the edit method, or any error                
                    print $fluff_moderator->fluff_encode($response);
                    exit;
                
                case 'version':
//                  authenticate first
                    $response = $fluff_moderator->fluff_authenticate();                
                    if ( true === $response ) {
//                      if the request is authentic, get the version                    
                        $response = $fluff_moderator->fluff_version();    
                    }
//                  print the results of the version method, or any error                
                    print $fluff_moderator->fluff_encode($response);
                    exit;
            } 
        }
    }
      
//  set up the action and filter so the query routing works    
    add_action('parse_request', 'fluff_moderator_parse_request');
    
    function fluff_moderator_query_vars( $vars ) {
        $vars[] = 'fluff-moderator';
        $vars[] = 'comment-id';
        $vars[] = 'comment-content';
        $vars[] = 'local-user';
        $vars[] = 'auth-field';
        $vars[] = 'setup';
//      some cunning obfuscation of username and password queries        
        $vars[] = 'dog';
        $vars[] = 'fish';
        return $vars;
    }
    add_filter( 'query_vars', 'fluff_moderator_query_vars' );