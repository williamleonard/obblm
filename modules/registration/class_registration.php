<?php

/*
 *  Copyright (c) William Leonard <email protected> 2009. All Rights Reserved.
 *
 *
 *  This file is part of OBBLM.
 *
 *  OBBLM is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  OBBLM is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once('header.php');
 
class Registration
{
    /***************
     * Properties 
     ***************/

    //User table values
    public $username        = '';
    public $password        = '';
    public $email           = '';
    public $error           = '';
    
    /***************
     * Methods 
     ***************/
    
    function __construct($username, $password, $email) {

        $this->username = $username;
        $this->password = $password;
        $this->email = $email;

        // Check to see if coach name already exists.
        if ( !$this->chk_username() || !$this->chk_password() || !$this->chk_email() )
        {
            return false;  //Use ->error to display the error message to the user.
        }

        $this->create();
        $this->sendemail();

    }

    public function chk_username() {

        $status = true;
        $min_length = 3;

        if ( get_alt_col(USERTABLE, USERNAME, $this->username, USERNAME) || strlen($this->username) < $min_length )
        {
            $this->error = USERNAME_ERROR;
            $status = false;
        }

        return $status;

    }

    public function chk_password() {

        //Will add regexp check later
        $status = true;
        $min_length = 3;

        if ( strlen($this->username) < $min_length )
        {
            $this->error = PASSWORD_ERROR;
            $status = false;
        }
        else $this->password = md5( $this->password );

        return $status;

    }

    public function chk_email() {

        #'/^([.0-9a-z_-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i' : 
        #'/^([*+!.&#$�\'\\%\/0-9a-z^_`{}=?~:-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i' 

        $status = true;
        list($emailuser,$domain) = split("@",$this->email);
        //I got the regular expression from http://www.regular-expressions.info/email.html - Will
        $emailexp = "/^([.0-9a-z_-]+)@(([0-9a-z-]+\.)+[0-9a-z]{2,4})$/i";

        if ( !preg_match($emailexp, $this->email) ) $status = false;

        if ( !getmxrr($domain, $mxrecords) || !$status )
        {
            $this->error = EMAIL_ERROR;
            $status = false;
        }

        return $status;

    }

    public function create() {

        $status = true;
        $query = sprintf( "INSERT INTO %s ( %s, %s, %s, %s ) VALUES ( '%s', '%s', '%s', %d )",
                 USERTABLE,
                 USERNAME, PASSWORD, EMAIL, ACTIVATION,
                 mysql_real_escape_string($this->username), $this->password, mysql_real_escape_string($this->email), NOT_ACTIVATED );

        $results = mysql_query($query);
        if ( !results )
        {
            $status = false;
            $this->error = mysql_error();
        }
                            
        return $status;

    }

    public function sendemail() {

        $status = true;
        $to      = $this->email;
        $subject = 'New user registration';
        $message = "You have received a new registration for user: ".$this->username." email: ".$this->email.".";
        $headers = 'From: webmaster@stuntyleeg.com' . "\r\n" .
                   'Reply-To: noreply@stuntyleeg.com' . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();

        $mailed = mail($to, $subject, $message, $headers);

        if ( !$mailed ) $status = false;

        return $status;

    }

    /***************
     * Statics
     ***************/
     
    private static function form() {
        
        /**
         * Creates a registration form.
         *
         * 
         **/

        $form = "
        <form method='POST' action='register.php'>
            <div class='adminBox'>
                <div class='boxTitle3'>
                    Register
                </div>
                <div class='boxBody'>
                    Username :<br> <input type='text' name='new_name' size='20' maxlength='50'><br><br>
                    eMail :<br> <input type='text' name='new_mail' size='20' maxlength='129'><br><br>
                    Password :<br> <input type='password' name='new_passwd' size='20' maxlength='50'><br><br>
                    <br><br>
                    <input type='submit' name='button' value='Create user'>
                </div>
            </div>
        ";

        return $form;
    }
    
    private static function submitForm($username, $password, $email) {

        $register = new Registration($username, $password, $email);
        Print "If you  do not see an error message ,than the registration was successful";
        Print "<br>{$register->error}<br>";

    }
    
    public static function main() {
        
        // Module registered main function.
    
        #$username = 'funnyfingers1';
        #$password = 'testtest';
        #$email = 'funnyfingers@hotmail.com';

        if ( isset($_POST['new_name']) && isset($_POST['new_mail']) && isset($_POST['new_passwd']) )
        {

            $username = $_POST['new_name'];
            $password = $_POST['new_passwd'];
            $email = $_POST['new_mail'];
            submitForm($username, $password, $email);

        }
        else
        {

            Print "<html><body>";
            Print Registration::form();
            Print "</body></html>";

        }
    }
}

?>
