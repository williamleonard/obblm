<?php

/*
 *  Copyright (c) Niels Orsleff Justesen <njustesen@gmail.com> and Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2007-2010. All Rights Reserved.
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

// Round types
define('RT_FINAL', 255);
define('RT_3RD_PLAYOFF', 254); # 3rd place playoff: The two knock-out matches between the final four teams with the winners progressing to the grand final. The losers are knocked-out, though take part in a third place play-off.
define('RT_SEMI', 253); # Semi-finals.
define('RT_QUARTER', 252); # Quarter-finals.
define('RT_ROUND16', 251); # Round of 16.

// Reserved (non-real) matches:
define('T_IMPORT_MID', -1);

// Player match data fields:
$T_PMD_RELS_OBJ = array('f_player_id','f_team_id','f_coach_id','f_race_id',);
$T_PMD_RELS_NODE = array('f_match_id','f_tour_id','f_did','f_lid',);
$T_PMD_RELS = array_merge($T_PMD_RELS_OBJ,$T_PMD_RELS_NODE);
$T_PMD_ACH = array('mvp','cp','td','intcpt','bh','si','ki',);
$T_PMD_IR = array('ir_d1','ir_d2',);
$T_PMD_INJ = array('inj','agn1','agn2',);
$T_PMD_OTHER = array('mg',);
$T_PMD = array_merge($T_PMD_RELS, $T_PMD_ACH, $T_PMD_IR, $T_PMD_INJ, $T_PMD_OTHER);

// Injury/status constants:
define('NONE',  1);
define('MNG',   2);
define('NI',    3);
define('MA',    4);
define('AV',    5);
define('AG',    6);
define('ST',    7);
define('DEAD',  8);
#define('SOLD',  9); Deprecated. This is NOT a match status!

// These are the values allowed in the $T_PMD_INJ fields (aging fields have restrictions, though).
$T_INJS = array(
    NONE => 'NONE',
    MNG  => 'MNG',
    NI   => 'NI',
    MA   => 'MA',
    AV   => 'AV',
    AG   => 'AG',
    ST   => 'ST',
    DEAD => 'DEAD',
#    SOLD => 'SOLD', Deprecated. This is NOT a match status!
);

class Match
{
    /***************
     * Properties 
     ***************/
    
    // MySQL stored fields
    # See $core_tables entry.
    
    // Other
    public $team1_name  = '';
    public $team2_name  = '';
    public $is_played   = false;
    public $is_draw     = false;
    public $winner      = 0; # Team ID
    
    /***************
     * Methods 
     ***************/
    
    function __construct($match_id) {

        // MySQL stored information
        $result = mysql_query("SELECT * FROM matches WHERE match_id = $match_id");
        if (mysql_num_rows($result) == 0)
            return null;
        $row = mysql_fetch_assoc($result);
        foreach ($row as $col => $val) {
            $this->$col = ($val) ? $val : 0;
        }
        $this->locked = (bool) $this->locked;
        $this->is_played = !empty($this->date_played);

        // Make class string properties = empty strings, and not zero's.
        foreach (array('date_created', 'date_played', 'date_modified') as $field) {
            if (empty($this->$field))
                $this->$field = '';
        }
    
        // Other
        $this->team1_name = get_alt_col('teams', 'team_id', $this->team1_id, 'name');
        $this->team2_name = get_alt_col('teams', 'team_id', $this->team2_id, 'name');

        // Determine winner's team ID.
        if ($this->team1_score > $this->team2_score) {
            $this->winner = $this->team1_id;
        }
        elseif ($this->team1_score < $this->team2_score) {
            $this->winner = $this->team2_id;
        }
        else {
            $this->winner = 0;
            $this->is_draw = true;
        }
    }

    public function setLocked($lock) {
        $this->locked = (bool) $lock;
        return mysql_query("UPDATE matches SET locked = ".(($lock) ? 1 : 0)." WHERE match_id = $this->match_id");
    }

    public function delete() {
    
        /**
         * Deletes this match (ignoring consequences).
         **/
    
        // Delete match entry and match data.
        $q = array();
        $q[] = "DELETE FROM matches       WHERE match_id = $this->match_id";
        $q[] = "DELETE FROM match_data    WHERE f_match_id = $this->match_id";
        $q[] = "DELETE FROM match_data_es WHERE f_mid = $this->match_id";
        $status = true;
        foreach ($q as $query) {
            $status &= mysql_query($query);
        }
        
        // Subtract team treasury.
        $t1 = new Team($this->team1_id);
        $t2 = new Team($this->team2_id);
        $status &= $t1->dtreasury(-1*$this->income1) && $t2->dtreasury(-1*$this->income2);
        
        // Run triggers.
        SQLTriggers::run(T_SQLTRIG_MATCH_DEL, array('mid' => $this->match_id, 'trid' => $this->f_tour_id, 'tid1' => $this->team1_id, 'tid2' => $this->team2_id));
        Module::runTriggers(T_TRIGGER_MATCH_DELETE, array($this->match_id));
        
        return $status;
    }

    public function reset() {

        /**
         * Clears all match data resetting the match to its initial not-yet-played-state.
         **/
        
        $q = array();
        $q[] = "DELETE FROM match_data    WHERE f_match_id = $this->match_id";
        $q[] = "DELETE FROM match_data_es WHERE f_mid = $this->match_id";
        $q[] = "UPDATE matches SET 
            date_played = NULL, date_modified = NULL, 
            team1_score = NULL, team2_score = NULL,
            smp1 = 0, smp2 = 0, 
            tcas1 = 0, tcas2 = 0, 
            fame1 = 0, fame2 = 0, 
            tv1 = 0, tv2 = 0, 
            income1 = NULL, income2 = NULL,
            ffactor1 = NULL, ffactor2 = NULL, 
            fans = 0, gate = NULL, stadium = NULL, submitter_id = NULL, locked = NULL
            WHERE match_id = $this->match_id";
            
        $status = true;
        foreach ($q as $qry) {
            $status &= mysql_query($qry);
        }
        
        // Reset team treasuries
        $t1 = new Team($this->team1_id);
        $t2 = new Team($this->team2_id);
        $t1->dtreasury(-1*$this->income1);
        $t2->dtreasury(-1*$this->income2);
        
        // Run triggers
        SQLTriggers::run(T_SQLTRIG_MATCH_DEL, array('mid' => $this->match_id, 'trid' => $this->f_tour_id, 'tid1' => $this->team1_id, 'tid2' => $this->team2_id));
        Module::runTriggers(T_TRIGGER_MATCH_RESET, array($this->match_id));
        
        return $status;
    }

    public function update(array $input) {
 
        /* 
            Updates general match data. 
            
            $input must contain the keys defined in $core_tables, with the exception of the $filter contents below.
        */

        // Verify input
        global $core_tables;
        $filter = array('match_id','round','f_tour_id','locked','date_played','date_modified','date_created','team1_id','team2_id',);
        $EXPECTED = array_diff(array_keys($core_tables['matches']), $filter); sort($EXPECTED);
        $PASSED = array_keys($input); sort($PASSED);
        if ($PASSED !== $EXPECTED)
            return false;
            
        // Input check.
        if ($this->locked || !get_alt_col('coaches', 'coach_id', $input['submitter_id'], 'coach_id')) # If invalid submitter ID (coach ID) then quit.
            return false;

        // Determine if team fan-factors are within the "> 0" limit. If not, don't save the negative fan-factor.
        $team1 = new Team($this->team1_id);
        $team2 = new Team($this->team2_id);
        if ($team1->rg_ff - $this->ffactor1 + $input['ffactor1'] < 0) $input['ffactor1'] = $this->ffactor1;
        if ($team2->rg_ff - $this->ffactor2 + $input['ffactor2'] < 0) $input['ffactor2'] = $this->ffactor2;

        // Entry corrections
        $input['date_played'] = ($this->is_played) ? 'date_played' : 'NOW()';
        $input['date_modified'] = 'NOW()';

        // Update match entry.
        $query = "UPDATE matches SET ".array_strpack_assoc('%k = %v',$input,',')." WHERE match_id = $this->match_id";
        if (!mysql_query($query))
            return false;
            
        // Update team treasury
        $team1->dtreasury($input['income1'] - $this->income1);
        $team2->dtreasury($input['income2'] - $this->income2);
        
        return true;
    }

    public function entry($pid, array $input, $ES = array()) {
        return self::_entry($this->match_id, $pid, $input, $ES, false);
    }
    
    public function getPlayerEntry($pid) {
        /**
         * Returns array holding the match data entry from a specific match for this player.
         **/
        global $T_PMD_ACH, $T_PMD_IR, $T_PMD_INJ;
        $T_PMD_ACH_IR = array_merge($T_PMD_ACH, $T_PMD_IR);
        $fields = array_merge(array_fill_keys($T_PMD_ACH_IR, 0), array_fill_keys($T_PMD_INJ, NONE));
        $query  = "SELECT ".implode(',',array_keys($fields))." FROM match_data WHERE f_match_id = $this->match_id AND f_player_id = $pid";
        $result = mysql_query($query);
        return (mysql_num_rows($result) > 0) ? mysql_fetch_assoc($result) : array();
    }
    
    // ALWAYS run this when finished (AFTER!!!) submitting ALL match data.
    public function finalizeMatchSubmit()
    {
        // Run triggers.
        SQLTriggers::run(T_SQLTRIG_MATCH_UPD, array('mid' => $this->match_id, 'trid' => $this->f_tour_id, 'tid1' => $this->team1_id, 'tid2' => $this->team2_id, 'played' => (int) $this->is_played));
        Module::runTriggers(T_TRIGGER_MATCH_SAVE, array($this->match_id));
        return true;
    }
    
    public function saveText($str) {
        
        $txt = new MatchSummary($this->match_id);
        return $txt->save($str);
    }

    public function getText() {

        $txt = new MatchSummary($this->match_id);
        return $txt->txt;
    }
    
    /***************
     * Statics
     ***************/

    public static function ImportEntry ($pid, array $input) {
        $status = (bool) mysql_query("REPLACE INTO matches (match_id, team1_id,  team2_id, round, f_tour_id, date_created, date_played)
            VALUES (".T_IMPORT_MID.", 0, 0, 0, 0, 0, 0)");
        return $status && self::_entry(null, $pid, $input, array(), true);
    }

    private static function _entry($mid, $pid, array $input, $ES = array(), $IMPORT = false) {
    
        /**
         * Updates match data of player.
         *
         *  When saving mercs pass the extra input fields: team_id, nr, skills
         *  When saving stars pass the extra input fields: team_id
         *
         **/

        if ($IMPORT) {
            // Statuses
            $LOCKED = $PLAYED = false;
            // Node IDs
            $mid = T_IMPORT_MID;
            $input['f_tour_id'] = $input['f_did'] = $input['f_lid'] = 0;
        } 
        else {
            // Statuses
            $result = mysql_query("SELECT locked, IF(date_played IS NULL OR date_played = '', FALSE, TRUE) AS 'played' FROM matches WHERE match_id = $mid");
            list($LOCKED, $PLAYED) = mysql_fetch_array($result);
            // Node IDs
            $query = "SELECT tour_id AS 'f_tour_id', did AS 'f_did', f_lid AS 'f_lid' FROM matches,tours,divisions WHERE matches.f_tour_id = tours.tour_id AND tours.f_did = divisions.did AND matches.match_id = $mid";
            $result = mysql_query($query);
            $input = array_merge($input, mysql_fetch_assoc($result));
        }

        /* 
            Relation IDs
        */
        $rels = array();
        switch ($pid) 
        {
            case ($pid > 0): # Ordinary player?
                $query = "SELECT owned_by_team_id AS 'f_team_id', f_cid AS 'f_coach_id', f_rid AS 'f_race_id' FROM players WHERE player_id = $pid";
                $result = mysql_query($query);            
                $rels = mysql_fetch_assoc($result);
                $tid = $rels['f_team_id'];
                break;
                
            case ($pid <= ID_STARS_BEGIN || $pid == ID_MERCS): # Star player or Mercenary?
                $tid = $input['team_id']; unset($input['team_id']);
                $query = "SELECT owned_by_coach_id AS 'f_coach_id', f_race_id AS 'f_race_id' FROM teams WHERE team_id = $tid";
                $result = mysql_query($query);            
                $rels = mysql_fetch_assoc($result);
                
                /* Special $input field processing. */
                switch ($pid) 
                {
                    case ($pid <= ID_STARS_BEGIN): # Star player?
                        // Star match_data should not be counted/considered as race stats when a team of a given race hires the star.
                        $rels['f_race_id'] = 'NULL';
                        break;
                    case ID_MERCS: # Mercenary?
                        // Mercs use the injs/agn fields differently from ordinary players. 
                        // Nr:      #Merc hired by that team. 
                        // Skills:  Extra skill bought count for the merc.
                        $input['inj'] = $input['nr']; unset($input['nr']);
                        $input['agn1'] = $input['skills']; unset($input['skills']);
                        $input['agn2'] = NONE;
                        break;
                }
                break;
        }
        $input = array_merge($input, $rels);

        /* 
            Other match data
        */
        $input['mg'] = $MG = (int) (Player::getPlayerStatus($pid,$mid) == MNG); // Missed (this) Game (ie. had a MNG from previous match)?
        $input['f_player_id'] = $pid;
        $input['f_match_id'] = $mid;
        
        /* 
            Verify input
        */
        global $T_PMD;
        $EXPECTED = $T_PMD; # We will be modifying (sorting) the contents, therefore we make a copy.
        sort($EXPECTED);
        ksort($input);
        if (array_keys($input) !== $EXPECTED)
            return false;
            
        /* 
            Post/pre match fixes
            
            Before we write player's match data, we need to check if player's status was...
                - Set to DEAD? In which case we must delete all the player's match data from matches played after this match (if any played).
                - Set to MNG? In which case we must zero set the player's match data from match played after this match (if this match is not the latest).
        */
        $status = true;
        
        if ($PLAYED) { # Must be played to have a date to compare with.
            if ($input['inj'] == DEAD) {
                $query = "DELETE FROM match_data USING match_data INNER JOIN matches 
                    WHERE match_data.f_match_id = matches.match_id AND f_player_id = $pid AND date_played > (SELECT date_played FROM matches WHERE match_id = $mid)";
                $status &= mysql_query($query);

            }
            elseif ($input['inj'] != NONE) { # Player has MNG status.
                global $T_PMD_ACH, $T_PMD_IR, $T_PMD_INJ;
                $status &= mysql_query("UPDATE match_data SET ".
                    array_strpack('%s = 0', array_merge($T_PMD_ACH, $T_PMD_IR), ',').','.
                    array_strpack('%s = '.NONE, $T_PMD_INJ, ',')."
                    mg = TRUE                
                    WHERE f_player_id = $pid AND f_match_id = (
                        SELECT match_id FROM matches, match_data WHERE 
                        match_data.f_match_id = matches.match_id AND 
                        date_played IS NOT NULL AND 
                        date_played > (SELECT date_played FROM matches WHERE match_id = $mid) AND 
                        f_player_id = $pid 
                        ORDER BY date_played ASC LIMIT 1)");
            }
        }
        
        /* 
            Injury corrections
        */
        if ($pid > 0) {
            $INJS = array('ma' => 0, 'av' => 0, 'ag' => 0, 'st' => 0, 'inj' => NONE, 'agn1' => NONE, 'agn2' => NONE);
            if ($PLAYED) {
                $MA = MA; $AV = AV; $AG = AG; $ST = ST; # Shortcuts.
                $query = "SELECT 
                        IF(inj=$MA,1,0)+IF(agn1=$MA,1,0)+IF(agn2=$MA,1,0) AS 'ma',
                        IF(inj=$AG,1,0)+IF(agn1=$AG,1,0)+IF(agn2=$AG,1,0) AS 'ag',
                        IF(inj=$AV,1,0)+IF(agn1=$AV,1,0)+IF(agn2=$AV,1,0) AS 'av',
                        IF(inj=$ST,1,0)+IF(agn1=$ST,1,0)+IF(agn2=$ST,1,0) AS 'st'
                    FROM match_data WHERE f_player_id = $pid AND f_match_id = $mid";
                $result = mysql_query($query);
                $INJS = mysql_fetch_assoc($result);
            }

            global $CHR_CONV, $incpy;
            $incpy = $input; # Used in below filter by create_function().
            $p = new Player($pid);
            $fields = array('inj', 'agn1', 'agn2');
            foreach ($fields as $f) {
                if (!in_array($input[$f], array_keys($CHR_CONV))) # Allow passed injury unconditionally.
                    continue;
                
                if (
                    // Currently allowed injuries of this kind (= $input[$f]).
                    $p->chrLimits('inj', $input[$f]) 
                    // Of the "currently allowed", this amount is contributed to "Currently allowed" by this match. 
                    // Ie. the sum of the two is the allowed injuries of this kind if we neglect the contributions of this match to the total inj. count.
                    + $INJS[$CHR_CONV[ $input[$f] ]]
                    // This is the total inj. amount of this kind (=$input[$f]) which we want to add as recieved inuries from this match.
                    - count(array_filter($fields, create_function('$x', "global \$incpy; return (\$incpy[\$x]==\$incpy['$f']);"))) 
                    < 0) {
                    $input[$f] = NONE; 
                }
            }
        }

        /********************
         *  Insert data into MySQL 
         ********************/

        // Delete entry if already exists (we don't use MySQL UPDATE on rows for simplicity)
        $status &= mysql_query("DELETE FROM match_data WHERE f_player_id = $pid AND f_match_id = $mid");
        $query = 'INSERT INTO match_data ('.implode(',', $EXPECTED).') VALUES ('.implode(',', array_values($input)).')';
        
        return mysql_query($query) && 
            // Extra stats, if sent.
            (!empty($ES) ? self::ESentry(array(
                'f_pid' => $input['f_player_id'], 'f_tid' => $input['f_team_id'], 'f_cid' => $input['f_coach_id'], 'f_rid' => $input['f_race_id'], 
                'f_mid' => $input['f_match_id'], 'f_trid' => $input['f_tour_id'], 'f_did' => $input['f_did'], 'f_lid' => $input['f_lid']
            ), $ES) : true)
            && $status;
    }
    
    public static function ESentry(array $relations, array $playerData)
    {
        global $core_tables;
        
        // Ready the data.
        $tbl = 'match_data_es';
        # Required keys/columns.
        $KEYS = array_keys($core_tables[$tbl]); sort($KEYS);
        # Recieved data.
        $_receivedInput = array_merge($relations, $playerData); ksort($_receivedInput);
        $INPUT_KEYS     = array_keys($_receivedInput);
        $INPUT_VALUES   = array_values($_receivedInput);

        // Verify input.
        if ($INPUT_KEYS !== $KEYS)
            return false;
            
        // Delete entry if already exists (we don't use MySQL UPDATE on rows for simplicity)
        $WHERE = "f_mid = $relations[f_mid] AND f_pid = $relations[f_pid]";
        $query = "SELECT f_mid FROM $tbl WHERE $WHERE";
        if (($result = mysql_query($query)) && mysql_num_rows($result) > 0) {
            mysql_query("DELETE FROM $tbl WHERE $WHERE");
        }
        
        // Insert entry.
        $query  = 'INSERT INTO '.$tbl.' ('.implode(',', $KEYS).') VALUES ('.implode(',', $INPUT_VALUES).')';
        return mysql_query($query);
    }

    public static function getMatches($n = false, $node = false, $node_id = false, $getUpcomming = false) {
    
        /**
         * Returns an array of match objects for the latest $n matches, or all if $n = false.
         **/
         
        $m = array();
        switch ($node) 
        {
            case STATS_TOUR:     $where = "f_tour_id = $node_id"; break;
            case STATS_DIVISION: $where = "f_did = $node_id"; break;
            case STATS_LEAGUE:   $where = "f_lid = $node_id"; break;
            default: $where = false;
        }
        $query = "SELECT match_id FROM matches, tours, divisions 
            WHERE date_played IS ".(($getUpcomming) ? '' : 'NOT')." NULL AND match_id > 0 AND f_tour_id = tour_id AND f_did = did
            ".(($where) ? " AND $where " : '')."
            ORDER BY date_played DESC" . (($n) ? " LIMIT $n" : '');
        $result = mysql_query($query);
        
        if ($result && mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($m, new Match($row['match_id']));
            }
        }
        
        return $m;
    }
    
    public static function create(array $input) {

        /**
         * Creates a new match.
         *
         * Input: team1_id, team2_id, round, f_tour_id
         **/

        global $settings;
    
        if ($input['team1_id'] == $input['team2_id'] || ($isLocked = get_alt_col('tours', 'tour_id', $input['f_tour_id'], 'locked')))
            return false;
            
        // If team->league relations are on don't allow teams from different leagues to play each other.
        // If tour (f_tour_id) is not a node under the league associated with both teams, then deny match creation.
        $tr = get_alt_col('divisions', 'did', get_alt_col('tours', 'tour_id', $input['f_tour_id'], 'f_did'), 'f_lid');
        $t1 = get_alt_col('teams', 'team_id', $input['team1_id'], 'f_lid');
        $t2 = get_alt_col('teams', 'team_id', $input['team2_id'], 'f_lid');
        if ($settings['relate_team_to_league'] && ($t1 == $t2 && $t1 != $tr || $t1 != $t2))
            return false;

        $query = "INSERT INTO matches (team1_id, team2_id, round, f_tour_id, date_created)
                    VALUES ($input[team1_id], $input[team2_id], $input[round], '$input[f_tour_id]', NOW())";

        return mysql_query($query) && Module::runTriggers(T_TRIGGER_MATCH_CREATE, array(mysql_insert_id()));
    }
}

?>
