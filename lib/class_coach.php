<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2007-2010. All Rights Reserved.
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

define('LOGIN_COOKIE_COACHID', 'obblmuserid');
define('LOGIN_COOKIE_PASSWD', 'obblmpasswd');

// Privilege rings (ie. coach access level)
# Global:
define('T_RING_GLOBAL_ADMIN', 10); // Site admins
define('T_RING_GLOBAL_NONE',  0); // No global rights
# Local (league)
define('T_RING_LOCAL_COMMISH', 10); // League commissioner
define('T_RING_LOCAL_COMMISH', 2); // Regualr coach.

define('RING_SYS',   0); // Admins
define('RING_COM',   1); // Commissioners.
define('RING_COACH', 2); // Coach/ordinary user

define('T_COACH_NO_ASSOC_LID', 0); // Value of coaches associative league ID if the coach is NOT assigned to any league.

class Coach
{
    /***************
     * Properties 
     ***************/
    
    // MySQL stored information
    public $coach_id    = 0;
    public $name        = '';
    public $realname    = '';
    public $passwd      = '';
    public $mail        = '';
    public $phone       = '';
    public $ring        = 0; // Privilege ring (ie. coach access level).
    public $retired     = false;
    public $settings    = array();

    // Shortcut for compabillity issues.
    public $admin       = false;
    
    /***************
     * Methods 
     ***************/
    
    function __construct($coach_id) {
    
        // MySQL stored information
        $this->coach_id = $coach_id;
        $this->setStats(false,false,false);

        $this->ring = (int) $this->ring;
        $this->admin = ($this->ring === RING_SYS);
        if (empty($this->mail)) $this->mail = '';           # Re-define as empty string, and not numeric zero.
        if (empty($this->phone)) $this->phone = '';         # Re-define as empty string, and not numeric zero.
        if (empty($this->realname)) $this->realname = '';   # Re-define as empty string, and not numeric zero.
        
       
        // Coach's site settings.
        $this->settings = array(); // Is overwriten to type = string when loading MySQL data into this object.
        foreach (get_list('coaches', 'coach_id', $this->coach_id, 'settings') as $set) {
            list($key, $val) = explode('=', $set);
            $this->settings[$key] = $val;
        }
        global $settings;
        $init = array('theme' => 1, 'lang' => $settings['lang']); // Setting values which must be initialized if not stored/saved in mysql.
        foreach ($init as $key => $val) {
            if (!array_key_exists($key, $this->settings) || !isset($this->settings[$key]))
                $this->settings[$key] = $val;
        }
    }
    
    public function setStats($node, $node_id, $set_avg = false)
    {
        foreach (Stats::getAllStats(T_OBJ_COACH, $this->coach_id, $node, $node_id, $set_avg) as $key => $val) {
            $this->$key = $val;
        }
        return true;
    }
    
    public function setSetting($key, $val) {
        
        $this->settings[$key] = $val;
        $settings = array();
        foreach ($this->settings as $key => $val) {
            $settings[] = implode('=', array($key, $val));
        }
        
        return set_list('coaches', 'coach_id', $this->coach_id, 'settings', $settings);
    }

    public function getTeams() {

        /**
         * Returns an array of team objects for those teams owned by this coach.
         **/
    
        $teams = array();
        
        $result = mysql_query("SELECT team_id FROM teams WHERE owned_by_coach_id = $this->coach_id ORDER BY name ASC");
        if ($result && mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($teams, new Team($row['team_id']));
            }
        }
        
        return $teams;
    }    

    public function getWonTours() {

        // Returns an array of tournament objects for those tournaments the coach's teams have won.
        $tours = array();
        $result = mysql_query("SELECT tour_id FROM tours,teams WHERE winner = team_id AND owned_by_coach_id = $this->coach_id");
        while ($row = mysql_fetch_assoc($result)) {
            array_push($tours, new Tour($row['tour_id']));
        }
        return $tours;
    }

    public function isDeletable() {
        
        $status = true;
        
        foreach ($this->getTeams() as $t) {
            $status &= $t->isDeletable();
        }
        
        return $status;
    }
    
    public function delete() {

        $status = true;
        if ($this->isDeletable()) {
            
            foreach ($this->getTeams() as $t) {
                $status &= $t->delete();
            }            

            $status &= mysql_query("DELETE FROM coaches WHERE coach_id = ".$this->coach_id);
        }
        else {
            $status = false;
        }
        
        return $status;
    }
    
    public function setRetired($bool) {
        return mysql_query("UPDATE coaches SET retired = ".(($bool) ? 1 : 0)." WHERE coach_id = $this->coach_id");
    }

    public function setRing($level) {
        if (!in_array($level, array(RING_SYS, RING_COM, RING_COACH))) {return false;}
        $this->ring = $level;
        return mysql_query("UPDATE coaches SET ring = $level WHERE coach_id = $this->coach_id");
    }

    public function setPasswd($passwd) {
        $query = "UPDATE coaches SET passwd = '".md5($passwd)."' WHERE coach_id = $this->coach_id";
        return (mysql_query($query) && ($this->passwd = md5($passwd)));
    }

    public function setName($name) {
        if (!isset($name) || empty($name) || get_alt_col('coaches', 'name', $name, 'coach_id')) {return false;} // Don't allow duplicates.
        $query = "UPDATE coaches SET name = '".mysql_real_escape_string($name)."' WHERE coach_id = $this->coach_id";
        return (mysql_query($query) && ($this->name = $name) && SQLTriggers::run(T_SQLTRIG_COACH_UPDATE_CHILD_RELS, array('id' => $this->coach_id, 'obj' => $this)));
    }

    public function setMail($mail) {
        $query = "UPDATE coaches SET mail = '".mysql_real_escape_string($mail)."' WHERE coach_id = $this->coach_id";
        return (mysql_query($query) && ($this->mail = $mail));
    }

    public function setPhone($phnr) {
        $query = "UPDATE coaches SET phone = '".mysql_real_escape_string($phnr)."' WHERE coach_id = $this->coach_id";
        return (mysql_query($query) && ($this->phone = $phnr));
    }

    public function setRealName($rname) {
        $query = "UPDATE coaches SET realname = '".mysql_real_escape_string($rname)."' WHERE coach_id = $this->coach_id";
        return (mysql_query($query) && ($this->realname = $rname));
    }

    public function setLid($lid) {
        $this->f_lid = $lid;
        $query = "UPDATE coaches SET f_lid = $lid WHERE coach_id = $this->coach_id";
        return mysql_query($query);
    }

    public function isInMatch($match_id) {
    
        /**
         * Returns the boolean evaluation of a coach's participation in a specific match.
         **/
    
        $result = mysql_query("SELECT team1_id, team2_id FROM matches WHERE match_id = $match_id");
        $row    = mysql_fetch_assoc($result);
        $coach_id1 = get_alt_col('teams', 'team_id', $row['team1_id'], 'owned_by_coach_id');
        $coach_id2 = get_alt_col('teams', 'team_id', $row['team2_id'], 'owned_by_coach_id');

        return ($this->coach_id == $coach_id1 || $this->coach_id == $coach_id2);
    }
    
    public function saveText($str) {
        
        $desc = new ObjDescriptions(T_TEXT_COACH, $this->coach_id);
        return $desc->save($str);
    }

    public function getText() {

        $desc = new ObjDescriptions(T_TEXT_COACH, $this->coach_id);
        return $desc->txt;
    }
    
    public function savePic($name = false) {
        $img = new ImageSubSys(IMGTYPE_COACH, $this->coach_id);
        list($retstatus, $error) = $img->save($name);
        return $retstatus;
    }
    
    /***************
     * Statics
     ***************/

    const NODE_STRUCT__TREE = 1;
    const NODE_STRUCT__FLAT = 2;
    public static function allowedNodeAccess($NODE_SRUCT, $cid, $extraFields = array())
    {
        $GLOBAL_VIEW = (!$cid || (int) get_alt_col('coaches', 'coach_id', $cid, 'ring') > T_RING_GLOBAL_NONE);
        
        $properFields = array();
        $extraFields[T_NODE_LEAGUE]['name']     = 'lname';
        $extraFields[T_NODE_DIVISION]['name']   = 'dname';
        $extraFields[T_NODE_TOURNAMENT]['name'] = 'tname';
        foreach ($extraFields as $node => $fields) {
            switch ($node) {
                case T_NODE_LEAGUE:     $tbl = 'l'; break;
                case T_NODE_DIVISION:   $tbl = 'd'; break;
                case T_NODE_TOURNAMENT: $tbl = 't'; break;
            }
            foreach ($fields as $ref => $name) {
                $properFields[] = "$tbl.$ref AS '$name'";
            }
        }
        $properFields[] = "m.ring AS 'ring'";
        $query = "SELECT l.lid AS 'lid', d.did AS 'did', t.tour_id AS 'trid',".implode(',',$properFields)."
            FROM leagues AS l LEFT JOIN divisions AS d ON d.f_lid = l.lid LEFT JOIN tours AS t ON t.f_did = d.did ".
            ((!$GLOBAL_VIEW) ? ", memberships AS m WHERE m.lid = l.lid AND m.cid = $cid" : '');
        $result = mysql_query($query);
        
        switch ($NODE_SRUCT)
        {
            case self::NODE_STRUCT__TREE:
                $struct = array();
                while ($r = mysql_fetch_object($result)) {
                    $struct[$r->lid][$r->did][$r->trid]['desc'] = array_intersect_key((array) $r, array_fill_keys(array_values($extraFields[T_NODE_TOURNAMENT]),null));
                    $struct[$r->lid][$r->did]['desc']           = array_intersect_key((array) $r, array_fill_keys(array_values($extraFields[T_NODE_DIVISION]),null));
                    $struct[$r->lid]['desc']                    = array_intersect_key((array) $r, array_fill_keys(array_values($extraFields[T_NODE_LEAGUE]),null));
                    $struct[$r->lid]['desc']['ring'] = $r->ring;
                }            
                return $struct;
                
            case self::NODE_STRUCT__FLAT:
                $leagues = $divisions = $tours = array();
                while ($r = mysql_fetch_object($result)) {
                    $tours[$r->trid]    = array_intersect_key((array) $r, array_fill_keys(array_values($extraFields[T_NODE_TOURNAMENT]),null));
                    $divisions[$r->did] = array_intersect_key((array) $r, array_fill_keys(array_values($extraFields[T_NODE_DIVISION]),null));
                    $leagues[$r->lid]   = array_intersect_key((array) $r, array_fill_keys(array_values($extraFields[T_NODE_LEAGUE]),null));
                    $leagues[$r->lid]['ring'] = $r->ring;
                }
                return array($leagues,$divisions,$tours);
                
            default:
                return false;
        }
    }

    public static function getCoaches() {
    
        /**
         * Returns an array of all coach objects.
         **/
         
        $coaches = array();
        
        $query  = "SELECT coach_id FROM coaches";
        $result = mysql_query($query);
        if ($result && mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($coaches, new Coach($row['coach_id']));
            }
        }
                    
        return $coaches;
    }
    
    public static function login($coach, $passwd, $setCookie = false) {
        // $coach may be cid or coach name.

        if (!is_numeric($coach))
            $coach = get_alt_col('coaches', 'name', $coach, 'coach_id');

        if (self::checkPasswd($coach, $passwd) && !get_alt_col('coaches', 'coach_id', $coach, 'retired')) {
            self::_setSession($coach);
            if ($setCookie) {self::_setCookie($coach);}
            return true;
        }
        else {
            self::_delSession();
            self::_delCookies();
            return false;
        }
    }
    
    public static function cookieLogin() {

        return (
            !isset($_SESSION['logged_in']) && 
            isset($_COOKIE[LOGIN_COOKIE_COACHID]) && 
            isset($_COOKIE[LOGIN_COOKIE_PASSWD]) && 
            !get_alt_col('coaches', 'coach_id', $_COOKIE[LOGIN_COOKIE_COACHID], 'retired') && # Is not retired?
            self::checkPasswd($_COOKIE[LOGIN_COOKIE_COACHID], $_COOKIE[LOGIN_COOKIE_PASSWD], false) &&
            self::_setSession($_COOKIE[LOGIN_COOKIE_COACHID])
        );
    }
    
    public static function logout() {
        self::_delSession();
        self::_delCookies();
        return true;
    }
    
    public static function checkPasswd($cid, $passwd, $MD5 = true) {
        return (get_alt_col('coaches', 'coach_id', $cid, 'passwd') == ($MD5 ? md5($passwd) : $passwd));
    }
    
    protected static function _setSession($cid) {
        $_SESSION['logged_in'] = true;
        $_SESSION['coach']     = get_alt_col('coaches', 'coach_id', $cid, 'name');
        $_SESSION['coach_id']  = $cid;
        return true;
    }
    
    protected static function _delSession() {
        session_unset();
        session_destroy();
        return true;
    }

    protected static function _setCookie($cid) {
        $expire=time()+60*60*24*30;
        setcookie(LOGIN_COOKIE_COACHID, $cid, $expire);
        setcookie(LOGIN_COOKIE_PASSWD, get_alt_col('coaches', 'coach_id', $cid, 'passwd'), $expire);
        return true;
    }    
   
    protected static function _delCookies() {
        setcookie(LOGIN_COOKIE_COACHID, '', time()-3600);
        setcookie(LOGIN_COOKIE_PASSWD, '', time()-3600);
        return true;
    }
    
    public static function create(array $input) {
        
        /**
         * Creates a new coach.
         *
         * Input: name, realname, passwd, mail, phone, ring, f_lid, settings
         **/

        if (empty($input['name']) || empty($input['passwd']) || get_alt_col('coaches', 'name', $input['name'], 'coach_id')) # Name exists already?
            return false;

        $query = "INSERT INTO coaches (name, realname, passwd, mail, phone, ring, f_lid, settings) 
                    VALUES ('" . mysql_real_escape_string($input['name']) . "',
                            '" . mysql_real_escape_string($input['realname']) . "', 
                            '" . md5($input['passwd']) . "', 
                            '" . mysql_real_escape_string($input['mail']) . "', 
                            '" . mysql_real_escape_string($input['phone']) . "', 
                            " . $input['ring'].",
                            " . $input['f_lid'].",
                            '".array_strpack_assoc('%k=%v', $input['settings'], ',')."')";

        return mysql_query($query);
    }
}
?>
