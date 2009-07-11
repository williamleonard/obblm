<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2007-2009. All Rights Reserved.
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

class Team
{
    /***************
     * Properties 
     ***************/

    // MySQL stored information
    public $team_id           = 0;
    public $name              = "";
    public $owned_by_coach_id = 0;
    public $race              = "";
    public $treasury          = 0;
    public $apothecary        = 0;
    public $rerolls           = 0;
    public $ass_coaches       = 0;
    public $cheerleaders      = 0;
    public $rdy               = 1; // Ready bool.
    public $imported          = false;
    public $is_retired        = 0;

    public $coach_name        = '';
    private $_bought_fan_factor = 0;
    
    // MySQL stored initials for imported teams
    public $won_0  = 0;
    public $lost_0 = 0;
    public $draw_0 = 0;
    public $sw_0   = 0;
    public $sl_0   = 0;
    public $sd_0   = 0;
    public $wt_0   = 0;
    public $gf_0   = 0;
    public $ga_0   = 0;
    public $elo_0  = 0;
    public $tcas_0 = 0;
    
    // General (total) calcualted fields
    public $mvp         = 0;
    public $cp          = 0;
    public $td          = 0;
    public $intcpt      = 0;
    public $bh          = 0;
    public $si          = 0;
    public $ki          = 0;
    public $cas         = 0; // Sum of players' bh+ki+si.
    public $tdcas       = 0; // Is td+cas. Used by some ranking systems.
    public $spp         = 0; // Summed up SPPs.
    //-------------------
    public $played      = 0;
    public $won         = 0;
    public $lost        = 0;
    public $draw        = 0;
    public $win_percentage = 0;
    public $score_team  = 0;    // Total score made by this team.
    public $score_opponent = 0; // Total score made against this team.
    public $score_diff  = 0;    // score_team - score_opponent
    public $fan_factor  = 0;
    public $points      = 0; // Total team points, if points ranking system is used.
    public $smp         = 0; // Sportsmanship points.
    public $tcas        = 0; // Team cas.
    //-------------------    

    // Non-constructor filled fields.

        // By setExtraStats().
        public $won_tours = 0;

        // By setStreaks().
        public $row_won  = 0; // Won in row.
        public $row_lost = 0;
        public $row_draw = 0;
        
        // By setValue().
        public $value  = 0;

        // By getPlayers().
        private $_players = array();
   
    /***************
     * Methods 
     ***************/

    function __construct($team_id) {
    
        // MySQL stored information
        $result = mysql_query("SELECT * FROM teams WHERE team_id = $team_id");
        
        if (mysql_num_rows($result) <= 0)
            return false;
        
        $row = mysql_fetch_assoc($result);
        foreach ($row as $col => $val)
            $this->$col = $val ? $val : 0;

        $this->is_retired = ($this->retired || get_alt_col('coaches', 'coach_id', $this->owned_by_coach_id, 'retired'));
        unset($this->retired); // We use $this->is_retired instead.
        $this->coach_name = get_alt_col('coaches', 'coach_id', $this->owned_by_coach_id, 'name');
        $this->_bought_fan_factor = $this->fan_factor;
        $this->imported = ($this->imported == 1); // Make boolean.
        $this->setStats(false);
        $this->setValue();
        
        return true;
    }

    public function setStats($tour_id = false) {
        
        /**
         * Overwrites object's stats fields.
         **/
         
        foreach (array_merge(Stats::getStats(false, $this->team_id, false, false, $tour_id), Stats::getMatchStats(STATS_TEAM, $this->team_id, $tour_id)) as $field => $val) {
            $this->$field = $val;
        }

        $this->fan_factor += $this->_bought_fan_factor;
        
        // Import fields
        if ($this->imported) {
            $this->won            += $this->won_0;
            $this->lost           += $this->lost_0;
            $this->draw           += $this->draw_0;
            $this->played         += $this->won_0 + $this->lost_0 + $this->draw_0;
            $this->score_team     += $this->gf_0;
            $this->score_opponent += $this->ga_0;
            $this->tcas           += $this->tcas_0;
            # Corrections:
            $this->score_diff     = $this->score_team - $this->score_opponent;
            $this->win_percentage = ($this->played == 0) ? 0 : 100*$this->won/$this->played;
        }
        

        return true;
    }

    public function setExtraStats() {
    
        /**
         * Set extra team stats.
         **/
    
        $this->won_tours = (($this->imported) ? $this->wt_0 : 0) + count($this->getWonTours());
        
        return true;
    }
    
    public function setStreaks($trid = false) {

        /**
         * Counts most won, lost and draw matches in a row.
         **/

        foreach (Stats::getStreaks(STATS_TEAM, $this->team_id, $trid) as $key => $val) {
            $this->$key = $val;
        }

        // Import fields
        if ($this->imported) {
            if ($this->row_won < $this->sw_0)  $this->row_won  = $this->sw_0;
            if ($this->row_lost < $this->sl_0) $this->row_lost = $this->sl_0;
            if ($this->row_draw < $this->sd_0) $this->row_draw = $this->sd_0;
        }

        return true;
    }
    
    private function setValue() {
    
        global $rules;
    
        /*
            Sets team value without creating all team's player objects to get each player's value.
            
            NOTE: This is an awfully ugly MySQL query, which has been broken down into several pseudo tables !!!
        */
    
        $this->value = 0;

        /* Start compiling the query ... */        
        
        // For each player_id on this team, this tables contains the date of the most recent played match by each player.
        $latestMatchDate = "
            (
                SELECT 
                    f_player_id AS 'pid', 
                    MAX(date_played) AS 'date' 
                FROM 
                    match_data, 
                    matches 
                WHERE 
                        f_match_id = match_id 
                    AND date_played IS NOT NULL
                    AND f_team_id = $this->team_id 
                GROUP BY f_player_id
            ) AS latestMatchDate
        ";
        
        // For each player_id on this team, this tables contains the current player injury (sustained in the most recent match played by player).
        $currentInj = "
            (
                SELECT 
                    latestMatchDate.pid AS 'pid', 
                    inj
                FROM 
                    match_data, 
                    matches,
                    $latestMatchDate
                WHERE 
                        match_data.f_match_id   = matches.match_id 
                    AND match_data.f_player_id  = latestMatchDate.pid
                    AND matches.date_played     = latestMatchDate.date
                    AND f_team_id               = $this->team_id 
            ) AS currentInj
        ";
        
        // Contains this team's race's player positions' prices.
        global $DEA;
        $sqlUnions = array();
        foreach ($DEA[$this->race]['players'] as $pos => $desc) {
            array_push($sqlUnions, "SELECT '".mysql_real_escape_string($pos)."' AS 'position', $desc[cost] AS 'cost'");
        }
        $prices = "
            (
                ".implode(' UNION ', $sqlUnions)."
            ) AS prices
        ";
        
        // Contains all the required parts to calculate each player's values.
        $valueParts = "
            (
                SELECT 
                    players.player_id AS 'pid', 
                    ach_ma,
                    ach_av,
                    ach_ag,
                    ach_st,
                    LENGTH(ach_nor_skills) - LENGTH(REPLACE(ach_nor_skills, ',', '')) + IF(LENGTH(ach_nor_skills) = 0, 0, 1) AS 'nor', 
                    LENGTH(ach_dob_skills) - LENGTH(REPLACE(ach_dob_skills, ',', '')) + IF(LENGTH(ach_dob_skills) = 0, 0, 1) AS 'dob',
                    cost
                FROM 
                    $prices,
                    (
                        players
                        LEFT JOIN
                            $currentInj
                        ON
                            players.player_id = currentInj.pid
                    )
                WHERE 
                        players.position = prices.position
                    AND (inj IS NULL OR inj = ".NONE.")
                    AND date_sold IS NULL
                    AND owned_by_team_id = $this->team_id
            ) AS valueParts
        ";

        // If player injury value reduction is used, then compile an extra needed table.
        $valReducInjs = false;
        if ($rules['val_reduc_ma'] || $rules['val_reduc_st'] || $rules['val_reduc_av'] || $rules['val_reduc_ag']) {
            $NI = NI; $MA = MA; $AV = AV; $AG = AG; $ST = ST;
            $valReducInjs = "
                (
                    SELECT 
                        f_player_id as 'pid',
                        SUM(IF(inj = $MA, 1, 0) + IF(agn1 = $MA, 1, 0) + IF(agn2 = $MA, 1, 0)) AS 'inj_ma', 
                        SUM(IF(inj = $AV, 1, 0) + IF(agn1 = $AV, 1, 0) + IF(agn2 = $AV, 1, 0)) AS 'inj_av', 
                        SUM(IF(inj = $AG, 1, 0) + IF(agn1 = $AG, 1, 0) + IF(agn2 = $AG, 1, 0)) AS 'inj_ag', 
                        SUM(IF(inj = $ST, 1, 0) + IF(agn1 = $ST, 1, 0) + IF(agn2 = $ST, 1, 0)) AS 'inj_st' 
                    FROM match_data
                    WHERE f_team_id = $this->team_id AND f_player_id > 0
                    GROUP BY f_player_id
                ) AS valReducInjs
            ";
            $subtract = "(
                IF(inj_ma IS NULL, 0, inj_ma*$rules[val_reduc_ma]) + 
                IF(inj_av IS NULL, 0, inj_av*$rules[val_reduc_av]) + 
                IF(inj_ag IS NULL, 0, inj_ag*$rules[val_reduc_ag]) + 
                IF(inj_st IS NULL, 0, inj_st*$rules[val_reduc_st]))";
        }

        // Final master query.
        $query = "
            SELECT
                SUM(cost + (ach_ma + ach_av)*30000 + ach_ag*40000 + ach_st*50000 + nor*20000 + dob*30000 ".(($valReducInjs) ? " - $subtract" : '').") AS 'playerValueSum'
            FROM
                $valueParts
                ".(($valReducInjs) ? " LEFT JOIN $valReducInjs ON valueParts.pid = valReducInjs.pid" : '')."
        ";

        /* 
            Compile finished! Phew! 
            Lets get that player value sum. 
        */
        
        $result = mysql_query($query);
        if (mysql_num_rows($result) > 0) {
            $row = mysql_fetch_assoc($result);
            $this->value = $row['playerValueSum'];
        }        
        
        /* Finally we add goods values */
        
        foreach ($this->getGoods(false) as $thing => $details) { # "false" arg. = force normal "un-doubled" re-roll prices.
            $this->value += $this->$thing * $details['cost'];
        }
        
        return true;
    }

    public function setOwnership($cid) {

        /**
         * Changes team ownership to the coach ID $cid.
         **/
        
        $query = "UPDATE teams SET owned_by_coach_id = $cid WHERE team_id = $this->team_id";
        return (mysql_query($query) && ($this->owned_by_coach_id = $cid));
    }

    public function getPlayers() {

        /**
         * Returns an array of player objects for those players owned by this team.
         **/
    
        $this->_players = array();
        
        $result = mysql_query("SELECT player_id FROM players WHERE owned_by_team_id = $this->team_id ORDER BY nr ASC, name ASC");
        if (mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($this->_players, new Player($row['player_id']));
            }
        }

        return $this->_players;
    }

    public function getWonTours() {

        /**
         * Returns an array of tournament objects for those tournaments this team has won.
         **/

        $tours = array();
        
        foreach (Tour::getTours() as $t) {
            if ($t->winner == $this->team_id)
                array_push($tours, $t);
        }        
        
        return $tours;
    }
    
    public function getLatestTour() {
        
        /**
         * Returns the ID of latest tournament competed in.
         **/
         
        $query = "SELECT f_tour_id FROM matches WHERE team1_id = $this->team_id OR team2_id = $this->team_id ORDER BY date_played DESC LIMIT 1";
        $result = mysql_query($query);
        if (mysql_num_rows($result) > 0) {
            $row = mysql_fetch_assoc($result);
            return $row['f_tour_id'];
        }
        
        return false;
    }
    
    public function getGoods($double_price = true) {

        /**
         * Returns array containing buyable stuff for teams in their coach corner.
         **/

        global $DEA;
        global $rules;

        $rerollcost = 0;
        if (!empty($this->race)) {
            $rerollcost = $DEA[$this->race]['other']['RerollCost'];
            $rerollcost *= (($double_price && !$rules['static_rerolls_prices'] && $this->played > 0) ? 2 : 1);
        }

        $apothecary = true;
        if ($this->race == 'Khemri' || $this->race == 'Necromantic' || $this->race == 'Nurgle' || $this->race == 'Undead')
            $apothecary = false;
        
        $team_goods = array(
                // MySQL names
                'apothecary'    => array('cost' => $rules['cost_apothecary'],   'max' => ($apothecary ? 1 : 0),         'item' => 'Apothecary'),
                'rerolls'       => array('cost' => $rerollcost,                 'max' => $rules['max_rerolls'],         'item' => 'Reroll'),
                'fan_factor'    => array('cost' => $rules['cost_fan_factor'],   'max' => $rules['max_fan_factor'],      'item' => 'Fan Factor'),
                'ass_coaches'   => array('cost' => $rules['cost_ass_coaches'],  'max' => $rules['max_ass_coaches'],     'item' => 'Assistant Coach'),
                'cheerleaders'  => array('cost' => $rules['cost_cheerleaders'], 'max' => $rules['max_cheerleaders'],    'item' => 'Cheerleader'),
        );

        return $team_goods;
    }

    public function delete() {
        
        /**
         * Deletes team if deletable.
         **/
         
        if ($this->isDeletable()) {
            $query = "DELETE FROM match_data WHERE f_team_id = $this->team_id"; mysql_query($query); // These entries occur only when players are imported.
            $query = "DELETE FROM players WHERE owned_by_team_id = $this->team_id"; mysql_query($query);
            $query = "DELETE FROM teams WHERE team_id = $this->team_id"; mysql_query($query);
            return true;
        }
        
        return false;
    }

    public function rename($new_name) {
    
        /**
         * Renames team.
         **/
    
        // Do not allow changing the team name to an other existing team's name (to avoid confusion).
        if (empty($new_name))
            return false;
            
        $query  = "SELECT team_id FROM teams WHERE team_id != $this->team_id AND name = '" . mysql_real_escape_string($new_name) . "'";
        $result = mysql_query($query);
        if (mysql_num_rows($result) > 0)
            return false;
            
        $query = "UPDATE teams SET name = '" . mysql_real_escape_string($new_name) . "' WHERE team_id = $this->team_id";
        if (mysql_query($query))
            return true;
        else
            return false;
    }

    public function setRetired($bool) {
    
        return mysql_query("UPDATE teams SET retired = ".(($bool) ? 1 : 0)." WHERE team_id = $this->team_id");
    }

    public function buy($thing) {

        /**
         * Buy team stuff (coaching staff/re-rolls/fan factor).
         **/

        global $rules;

        $team_goods = $this->getGoods();

        // Valid item?
        if (!array_key_exists($thing, $team_goods))
            return false;

        // Is post game FF purchaseable? Note: Only counts for when teams are not newly imported ie. $this->played = $this-> "played_0".
        if ($thing == 'fan_factor' && !$rules['post_game_ff'] && $this->played > 0 && $this->played != $this->won_0 + $this->lost_0 + $this->draw_0)
            return false;

        // Enough money?
        if ($this->treasury - $team_goods[$thing]['cost'] < 0)
            return false;

        // Reached max allow quantity of item?
        if ($this->$thing >= $team_goods[$thing]['max'] && $team_goods[$thing]['max'] != -1)
            return false;

        // Buy that thing!
        $price = $team_goods[$thing]['cost'];
        if (mysql_query("UPDATE teams SET treasury = treasury - $price, $thing = $thing + 1 WHERE team_id = $this->team_id")) {
            $this->$thing++;
            $this->treasury -= $price;
            return true;
        }
        else {
            return false;
        }
    }

    public function unbuy($thing) {

        /**
         * Regret the purchase of team stuff (coaching staff/re-rolls/fan factor) and get full refund.
         **/

        $team_goods = $this->getGoods();

        // Valid item?
        if (!array_key_exists($thing, $team_goods))
            return false;
        
        // Have more than 0 of item?
        if ($this->$thing <= 0 || ($thing == 'fan_factor' && $this->_bought_fan_factor <= 0))
            return false;
        
        // Un-buy!
        $price = $team_goods[$thing]['cost'];
        if (mysql_query("UPDATE teams SET treasury = treasury + $price, $thing = $thing - 1 WHERE team_id = $this->team_id")) {
            $this->$thing--;
            $this->treasury += $price;
            return true;
        }
        else {
            return false;
        }
    }
    
    public function drop($thing) {
        
        /**
         * Let go of team stuff (coaching staff/re-rolls/fan factor) WITHOUT refund.
         **/
        
        global $rules;
        $goods = $this->getGoods();
        $price = null;
        
        // May drop post FF?
        if ($thing == 'fan_factor' && !$rules['post_game_ff'] && $this->played > 0)
            return false;
        
        if (array_key_exists($thing, $goods))
            $price = $goods[$thing]['cost'];
        else
            return false;
        
        if ($this->unbuy($thing)) {
            if ($this->dtreasury(-1 * $price))
                return true;
            else
                $this->buy($thing); # Do not allow a situation, where we have removed the team "thing", and were not able to throw the refund away.
        }
        
        return false;
    }
    
    public function dtreasury($delta) {
    
        /**
         * Add a delta to team's treasury.
         **/
        @logTeamAction("Added treasury delta = $delta", $this->team_id);
        
        $query = "UPDATE teams SET treasury = treasury + $delta WHERE team_id = $this->team_id";
        if (mysql_query($query)) {
            $this->treasury += $delta;
            return true;
        }
        else {
            return false;
        }
    }
    
    public function setReady($bool) {
    
        mysql_query("UPDATE teams SET rdy = ".(($bool) ? 1 : 0)." WHERE team_id = $this->team_id");
        $t->rdy = $bool;
        return true;
    }

    public function isDeletable() {

        /**
         * Tests if a team is deletable (has not participated in any matches)
         **/

        $query = "SELECT match_id FROM matches WHERE team1_id = $this->team_id OR team2_id = $this->team_id LIMIT 1";
        $result = mysql_query($query);
        
        return (mysql_num_rows($result) > 0) ? false : true;
    }

    public function isFull() {

        /**
         * Returns true/false depending on, if the team may purchase more players/has reached the max. player limit.
         **/

        global $rules;

        // Determine subtraction value.
        $DOS = 0; # Dead Or Sold
        
        if (empty($this->_players))
            $this->getPlayers(); # Fills $this->_players.
        
        foreach ($this->_players as $p) {
            if ($p->is_dead || $p->is_sold)
                $DOS++;
        }

        if (count($this->_players) - $DOS >= $rules['max_team_players'])
            return true;
        else
            return false;
    }

    public function isPlayerBuyable($position) {

        /**
         * Checks if team has reach player quantity limit for specific player position.
         * Note: Player quantity limits are defined in $DEA 
         **/

        global $DEA;
        
        if (empty($this->_players))
            $this->getPlayers(); # Fills $this->_players.

        // Determine subtraction value.
        $DOS = 0; # Dead Or Sold
        foreach ($this->_players as $p) {
                if ($p->pos == $position && ($p->is_dead || $p->is_sold))
                    $DOS++;
        }

        // Find current count of position.
        $query   = "SELECT COUNT(player_id) as 'number' FROM players WHERE owned_by_team_id = $this->team_id AND position = '$position'";
        $result  = mysql_query($query);
        $row     = mysql_fetch_assoc($result);

        return (array_key_exists($position, $DEA[$this->race]['players']) && ($row['number'] - $DOS) < $DEA[$this->race]['players'][$position]['qty']);
    }

    public function getTourRankings($getFFA = true) {
    
        /**
         * Get structure with tours this team has participated in, and the ranking this team has/had in those tours.
         **/
        $ret = array();
    
        $query = "SELECT DISTINCT f_tour_id FROM matches, tours 
            WHERE f_tour_id = tour_id AND (team1_id = $this->team_id OR team2_id = $this->team_id) ".(($getFFA) ? '' : "AND type != ".TT_SINGLE);
            
        $result = mysql_query($query);
        if (mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                $t = new Tour($row['f_tour_id']);
                $stn = $t->getStandings();
                $t->teamRank = 'ERR';
                for ($i = 0; $i < count($stn); $i++) {
                    if ($stn[$i]->team_id == $this->team_id) {
                        $t->teamRank = $i+1;
                        break;
                    }
                }
                array_push($ret, $t);
            }
        }
        
        return $ret;
    }

    public function getToursPlayedIn($ids_only = false)
    {
        $tours = array();
        
        $query = "SELECT DISTINCT(f_tour_id) FROM matches, tours 
                WHERE f_tour_id = tour_id AND team1_id = $this->team_id OR team2_id = $this->team_id 
                ORDER BY tours.date_created ASC";
        $result = mysql_query($query);
        if (mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($tours, ($ids_only) ? $row['f_tour_id'] : new Tour($row['f_tour_id']));
            }
        }
        
        return $tours;
    }

    public function saveText($str) {
        
        $txt = new TDesc(T_TEXT_TEAM, $this->team_id);
        return $txt->save($str);
    }

    public function getText() {

        $desc = new TDesc(T_TEXT_TEAM, $this->team_id);
        return $desc->txt;
    }

    public function saveLogo($name) {
        return save_pic($name, IMG_TEAMS, $this->team_id);
    }
    
    public function getLogo() {
        $r = get_races();
        $p = get_pic(IMG_TEAMS, $this->team_id);
        if (!preg_match('/'.basename(NO_PIC).'/', $p)) {
            return $p;
        }
        else {
            return $r[$this->race];
        }
    }

    public function saveStadiumPic($name) {
        return save_pic($name, IMG_STADIUMS, $this->team_id);
    }
    
    public function getStadiumPic() {
        return get_pic(IMG_STADIUMS, $this->team_id);
    }
    
    public function writeNews($txt) {
        return TNews::create($txt, $this->team_id);
    }    
    
    public function getNews($n = false) {
        return TNews::getNews($this->team_id, $n);
    }
    
    public function deleteNews($news_id) {
        $news = new TNews($news_id);
        return $news->delete();
    }
    
    public function getPrizes($mkStr = false) {
    
        $prizes = Prize::getPrizesByTeam($this->team_id);
        if ($mkStr) {
            $str = array();
            $ptypes = Prize::getTypes();
            foreach ($ptypes as $idx => $type) {
                $cnt = count(array_filter($prizes, create_function('$p', 'return ($p->type == '.$idx.');')));
                if ($cnt > 0) 
                    $str[] = $cnt.' '.$ptypes[$idx];
            }
            return implode(', ', $str);
        }
        else {
            return $prizes;
        }
    }

    public function xmlExport()
    {
        /* 
            Exports a team by the using the same fields as the import XML schema uses.
        */
        
        $this->setStreaks();
        $this->setExtraStats();
        $ELORanks = ELO::getRanks(false);
        $this->elo = $ELORanks[$this->team_id];
        
        $dom = new DOMDocument();
        $dom->formatOutput = true;

        $el_root = $dom->appendChild($dom->createElement('xmlimport'));
        
        $el_root->appendChild($dom->createElement('coach', $this->coach_name));
        $el_root->appendChild($dom->createElement('name', $this->name));
        $el_root->appendChild($dom->createElement('race', $this->race));
        $el_root->appendChild($dom->createElement('treasury', $this->treasury));
        $el_root->appendChild($dom->createElement('apothecary', $this->apothecary));
        $el_root->appendChild($dom->createElement('rerolls', $this->rerolls));
        $el_root->appendChild($dom->createElement('fan_factor', $this->fan_factor));
        $el_root->appendChild($dom->createElement('ass_coaches', $this->ass_coaches));
        $el_root->appendChild($dom->createElement('cheerleaders', $this->cheerleaders));
        
        $el_root->appendChild($dom->createElement('won_0', $this->won));
        $el_root->appendChild($dom->createElement('lost_0', $this->lost));
        $el_root->appendChild($dom->createElement('draw_0', $this->draw));
        $el_root->appendChild($dom->createElement('sw_0', $this->row_won));
        $el_root->appendChild($dom->createElement('sl_0', $this->row_lost));
        $el_root->appendChild($dom->createElement('sd_0', $this->row_draw));
        $el_root->appendChild($dom->createElement('wt_0', $this->won_tours));
        $el_root->appendChild($dom->createElement('gf_0', $this->score_team));
        $el_root->appendChild($dom->createElement('ga_0', $this->score_opponent));
        $el_root->appendChild($dom->createElement('tcas_0', $this->tcas));
        $el_root->appendChild($dom->createElement('elo_0', $this->elo));

        foreach ($this->getPlayers() as $p) {
            $status = strtolower($p->getStatus(-1));
            if ($status == 'none') {$status = 'ready';}
            if ($p->is_sold) {$status = 'sold';}

            $ply = $el_root->appendChild($dom->createElement('player'));
            $ply->appendChild($dom->createElement('name', $p->name));
            $ply->appendChild($dom->createElement('position', $p->pos));
            $ply->appendChild($dom->createElement('status', $status));
            $ply->appendChild($dom->createElement('stats', "$p->cp/$p->td/$p->intcpt/$p->bh/$p->si/$p->ki/$p->mvp"));
            $ply->appendChild($dom->createElement('injs', "$p->inj_ma/$p->inj_st/$p->inj_ag/$p->inj_av/$p->inj_ni"));
        }
        
        return $dom->saveXML();
    }

    /***************
     * Statics
     ***************/
    
    public static function getTeams($race = false) {
    
        /**
         * Returns an array of all team objects.
         **/
    
        $teams = array();
        
        $query = "SELECT team_id FROM teams" . (($race) ? " WHERE race='$race'" : '');
        $result = mysql_query($query);
        if (mysql_num_rows($result) > 0) {
            while ($row = mysql_fetch_assoc($result)) {
                array_push($teams, new Team($row['team_id']));
            }
        }
                    
        return $teams;
    }

    public static function getRaceStats($race) {
        
        /**
         * Returns an array of race stats by looking at teams' (from that race) stats in MySQL.
         **/        

         // Initialize         
         $d = array();
         $teams = Team::getTeams($race);
         $stats = array('won_tours', 'won', 'lost', 'draw', 'played', 'td', 'cp', 'intcpt', 'cas', 'bh', 'si', 'ki', 'value');
         $avg_calc = array_slice($stats, 5);
         
         foreach ($stats as $s) $d[$s] = 0;
         
         // Fill variables.
         foreach ($teams as $t) {
            $t->setExtraStats();
            foreach ($stats as $s) {
                $d[$s] += $t->$s;
            }
         }
         
        $c = $d['teams'] = count($teams);
        foreach ($avg_calc as $s) {
            $d[$s] = ($c == 0) ? 0 : $d[$s]/$c;
        }
         
        $d['race'] = $race;
        $d['win_percentage'] = ($d['played'] == 0) ? 0 : $d['won']/$d['played'] * 100;
         
        return $d;
    }

    public static function create(array $input, $init = array()) {
    
        /**
         * Creates a new team.
         *
         * Input: coach_id, name, race
         **/

        global $rules;
        
        $IS_VALID_RACE = false;

        foreach (get_races() as $race => $icon_path) {
            if ($race == $input['race']) {
                $IS_VALID_RACE = true;
                break;
            }
        }

        if (!$IS_VALID_RACE)
            return false;

        // Does coach exist? Does team exist already? We do not permit two teams with identical names.
        if (!get_alt_col('coaches', 'coach_id', $input['coach_id'], 'coach_id') || get_alt_col('teams', 'name', $input['name'], 'team_id')) 
            return false;

        $query = "INSERT INTO teams
                    (
                        name,
                        owned_by_coach_id,
                        race,
                        treasury,
                        apothecary,
                        rerolls,
                        fan_factor,
                        ass_coaches,
                        cheerleaders
                        ".((!empty($init)) 
                            ? 
                                ",won_0,
                                lost_0,
                                draw_0,
                                sw_0,
                                sl_0,
                                sd_0,
                                wt_0,
                                gf_0,
                                ga_0,
                                elo_0,
                                tcas_0,
                                imported"
                            : ''
                        )."
                    )
                    VALUES
                    (
                        '" . mysql_real_escape_string($input['name']) . "',
                        $input[coach_id],
                        '$input[race]',
                        $rules[initial_treasury],
                        0,
                        $rules[initial_rerolls],
                        $rules[initial_fan_factor],
                        $rules[initial_ass_coaches],
                        $rules[initial_cheerleaders]
                        ".((!empty($init)) 
                            ? 
                                ",$init[won],
                                $init[lost],
                                $init[draw],
                                $init[sw],
                                $init[sl],
                                $init[sd],
                                $init[wt],
                                $init[gf],
                                $init[ga],
                                $init[elo],
                                $init[tcas],
                                1"
                            : ''
                        )."
                    )";

        return (mysql_query($query));
    }
    
	public static function getTeam ($team_name) {
    
        /**
         * Returns the team with the right name
         **/
        
        $query = "SELECT team_id FROM teams WHERE name='" .$team_name. "';";
        
        $result = mysql_query($query);
        if ($result && mysql_num_rows($result) == 1 ) {
            $row = mysql_fetch_assoc($result);
            return $row['team_id'];
        }
                    
        return ;
    }
    
    /*
     * Creates the SQLite file with Cyanide format
     * File path : TEAM_TEMPLATE_DIR/team_id.dn
     */
    public function cyanideSQLiteExport ()
    {
    	$template_file_path = TEAM_TEMPLATE_DIR ."/". $this->race .".db"; // template file
    	$sqlite_file_path = TEAM_TEMPLATE_DIR . "/". $this->team_id . ".db"; // copy
    	
    	/*
    	 * First, copy the template file
    	 */
    	copy($template_file_path, $sqlite_file_path);
    	
    	/*
    	 * Open the SQLite 3 db
    	 */
    	$match_report_db = new PDO("sqlite:" . $sqlite_file_path);
    	
    	/*
    	 * Get the IDs of the players already in the table (which will be copied)
    	 */
    	$query = "SELECT ID FROM PLAYER_LISTING";
    	if (!($result = $match_report_db->query($query)) || !($array = $result->fetchAll()))
				return false;
		
		$used_player_ids = array();
		foreach ($array as $row) {
			$model_id = $row['ID'];
			array_push($used_player_ids, $model_id);
		}
		
		/*
		 * Cash, Reroll, Apoth, Cheerleaders, FF, name and value
		 */
		$query = "UPDATE 'Team_Listing' SET 'iPopularity' = '" .$this->fan_factor. "', 'iCash' = '0', 'iCheerleaders' = '" .$this->cheerleaders. "', 'bApothecary' = '" .$this->apothecary. "', 'iRerolls' = '" .$this->rerolls. "', 'iValue' = '" .($this->value/1000). "', 'strName' = '" .sqlite_escape_string($this->name). "'";
		if (!($result = $match_report_db->query($query)))
			return false;
		
		/*
		 * Team name as it appears in the Edit screen
		 */
		$query = "UPDATE 'SavedGameInfo' SET 'strName' = '" .sqlite_escape_string($this->name). "', 'Championship_strTeamName' = '" .sqlite_escape_string($this->name). "'";
		if (!($result = $match_report_db->query($query)))
			return false;
    	
    	foreach ($this->getPlayers() as $player) {
    		
    		/*
    		 * Skip sold players
    		 */
    		if ($player->is_sold || $player->is_dead) {
    			continue;
    		}
    		
    		/*
			 * Get the next free id
			 */
			$query = "SELECT ID FROM PLAYER_LISTING ORDER BY ID DESC";
			
			if (!($result = $match_report_db->query($query)) || !($array = $result->fetchAll()))
				return false;
			
			$player_id = $array[0]['ID'] + 1;
    		
    		/*
    		 * Get the data for the right player type (Lineman, ...)
    		 */
			$pos = $player->position;
			$pos = ereg_replace("[J]", "", $pos);
			$pos = ereg_replace("[^a-zA-Z]", "", $pos);
			if ($pos == "Linerat")
				$pos = "Lineman";
			else if ($pos == "BlackOrcBlocker")
				$pos = "Orc_BlackBlocker";
			else if ($pos == "ChaosWarrior")
				$pos = "Chaos_Warrior";
			else if ($this->race == "Goblin" && $pos == "Goblin")
				$pos = "Gob";
			
    		$query = "SELECT PL.* FROM Player_Listing PL JOIN Player_Types PT ON PL.idPlayer_Types=PT.ID WHERE PT.DATA_CONSTANT LIKE '%$pos'";
    		
    		if (!($result = $match_report_db->query($query)) || !($array = $result->fetchAll()))
				return false;
			
			$row = $array[0];
			
			/*
			 * Modify the record
			 */
			
			// ID, name, number, experience, level and value
			$row['ID'] = $player_id;
			$row['strName'] = sqlite_escape_string($player->name);
			$row['iNumber'] = $player->nr;
			$row['iExperience'] = $player->spp;
			$row['idPlayer_Levels'] = $player->getLevel();
			$row['iValue'] = ($player->value/1000);
			
			// Status (NONE, MNG)
			if ($player->getStatus(-1)=="MNG") {
				$row['iMatchSuspended'] = 1;
			}
			
			// Injuries (Ma, St, Ag, Av, Ni)
			if ($player->inj_ma >0) {
				$row['Characteristics_fMovementAllowance'] -= CYANIDE_SQLITE_UNIT_MA * $player->inj_ma;
				$query = "INSERT INTO 'Player_Casualties' ('idPlayer_Listing','idPlayer_Casualty_Types') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_MA_CAS_ID. "')";
				for ($i=0; $i<$player->inj_ma;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->inj_st >0) {
				$row['Characteristics_fStrength'] -= CYANIDE_SQLITE_UNIT_ST * $player->inj_st;
				$query = "INSERT INTO 'Player_Casualties' ('idPlayer_Listing','idPlayer_Casualty_Types') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_ST_CAS_ID. "')";
				for ($i=0; $i<$player->inj_st;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->inj_ag >0) {
				$row['Characteristics_fAgility'] -= CYANIDE_SQLITE_UNIT_AG * $player->inj_ag;
				$query = "INSERT INTO 'Player_Casualties' ('idPlayer_Listing','idPlayer_Casualty_Types') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_AG_CAS_ID. "')";
				for ($i=0; $i<$player->inj_ag;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->inj_av >0) {
    			// Characteristics_fArmourValue is not linear
				switch (round($row['Characteristics_fArmourValue'])) {
					case (round(CYANIDE_SQLITE_UNIT_AV6)) :
						// av 6 always become av 5
						$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV5;
						break;
						
					case (round(CYANIDE_SQLITE_UNIT_AV7)) :
						// av 7 becomes av 6 or av 5
						if ($player->inj_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV6;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV5;
							
						break;
						
					case (round(CYANIDE_SQLITE_UNIT_AV8)) :
						// av 8 becomes av 7 or av 6
						if ($player->inj_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV7;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV6;
							
						break;
						
					case (round(CYANIDE_SQLITE_UNIT_AV9)) :
						// av 9 becomes av 8 or av 7
						if ($player->inj_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV8;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV7;
							
						break;
						
					case (round(CYANIDE_SQLITE_UNIT_AV10)) :
						// av 10 becomes av 9 or av 8
						if ($player->inj_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV9;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV8;
							
						break;
				}
				
				$query = "INSERT INTO 'Player_Casualties' ('idPlayer_Listing','idPlayer_Casualty_Types') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_AV_CAS_ID. "')";
				for ($i=0; $i<$player->inj_av;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->inj_ni >0) {
				$query = "INSERT INTO 'Player_Casualties' ('idPlayer_Listing','idPlayer_Casualty_Types') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_NI_CAS_ID. "')";
				for ($i=0; $i<$player->inj_ni;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
			
			// Stat increases (ma, st, ag, av)
			if ($player->ach_ma >0) {
				$row['Characteristics_fMovementAllowance'] += CYANIDE_SQLITE_UNIT_MA * $player->ach_ma;
				$query = "INSERT INTO 'Player_Skills' ('idPlayer_Listing','idSkill_Listing') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_MA_SKILL_ID. "')";
				for ($i=0; $i<$player->ach_ma;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->ach_st >0) {
    			$row['Characteristics_fStrength'] += CYANIDE_SQLITE_UNIT_ST * $player->ach_st;
				$query = "INSERT INTO 'Player_Skills' ('idPlayer_Listing','idSkill_Listing') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_ST_SKILL_ID. "')";
				for ($i=0; $i<$player->ach_st;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->ach_ag >0) {
    			$row['Characteristics_fAgility'] += CYANIDE_SQLITE_UNIT_AG * $player->ach_ag;
				$query = "INSERT INTO 'Player_Skills' ('idPlayer_Listing','idSkill_Listing') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_AG_SKILL_ID. "')";
				for ($i=0; $i<$player->ach_ag;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
    		if ($player->ach_av >0) {
	    		// Characteristics_fArmourValue is not linear
				switch (round($row['Characteristics_fArmourValue'])) {
					case (round(CYANIDE_SQLITE_UNIT_AV5)) :
						// av 5 becomes av 6 or av 7
						if ($player->ach_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV6;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV7;
							
						break;
					
					case (round(CYANIDE_SQLITE_UNIT_AV6)) :
						// av 6 becomes av 7 or av 8
						if ($player->ach_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV7;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV8;
							
						break;
					
					case (round(CYANIDE_SQLITE_UNIT_AV7)) :
						// av 7 becomes av 8 or av 9
						if ($player->ach_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV8;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV9;
							
						break;
					
					case (round(CYANIDE_SQLITE_UNIT_AV8)) :
						// av 8 becomes av 9 or av 10
						if ($player->ach_av == 1)
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV9;
						else
							$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV10;
							
						break;
					
					case (round(CYANIDE_SQLITE_UNIT_AV9)) :
						// av 9 becomes av 10
						$row['Characteristics_fArmourValue'] = CYANIDE_SQLITE_UNIT_AV10;
						
						break;
					
				}
				$query = "INSERT INTO 'Player_Skills' ('idPlayer_Listing','idSkill_Listing') VALUES ('" .$player_id. "','" .CYANIDE_SQLITE_AV_SKILL_ID. "')";
				for ($i=0; $i<$player->ach_av;$i++) {
					if (!($result = $match_report_db->query($query)))
						return false;
				}
			}
			
			// Skills
			$skills = array_merge($player->ach_nor_skills, $player->ach_dob_skills, $player->extra_skills);
			foreach ($skills as $skill) {
				
				if ($skill == "Claw/Claws")
					$skill = "Claw";
				else if ($skill == "Very Long Legs")
					$skill = "Long Legs";
				
				$skill = ereg_replace("[^a-zA-Z]", "", $skill);
				
				$query = "SELECT SL.ID FROM Skill_Listing SL WHERE SL.DATA_CONSTANT='" .$skill. "'";
				if (!($result = $match_report_db->query($query)) || !($array = $result->fetchAll()))
					return false;
				
				$skill_id = $array[0]['ID'];

				$query = "INSERT INTO 'Player_Skills' ('idPlayer_Listing','idSkill_Listing') VALUES ('" .$player_id. "','" .$skill_id. "')";
				if (!($result = $match_report_db->query($query)))
					return false;
			}
			
			/*
			 * Insert the new record
			 */
			$query = "INSERT INTO Player_Listing ('ID', 'idPlayer_Names','strName','idPlayer_Types','idTeam_Listing','idTeam_Listing_Previous','idRaces','iPlayerColor','iSkinScalePercent','iSkinMeshVariant','iSkinTextureVariant','fAgeing','iNumber','Characteristics_fMovementAllowance','Characteristics_fStrength','Characteristics_fAgility','Characteristics_fArmourValue','idPlayer_Levels','iExperience','idEquipment_Listing_Helmet','idEquipment_Listing_Pauldron','idEquipment_Listing_Gauntlet','idEquipment_Listing_Boot','Durability_iHelmet','Durability_iPauldron','Durability_iGauntlet','Durability_iBoot','iSalary','Contract_iDuration','Contract_iSeasonRemaining','idNegotiation_Condition_Types','Negotiation_iRemainingTries','Negotiation_iConditionDemand','iValue','iMatchSuspended','iNbLevelsUp','LevelUp_iRollResult','LevelUp_iRollResult2','LevelUp_bDouble','bGenerated','bStar','bEdited','bDead','strLevelUp')";
			$query .= " VALUES (";
			$i = 0;
			foreach ($row as $key => $element)
			{
				if ($i % 2 == 0) // keep only even elements
					$query .= "'" .$element. "', ";
				
				$i++;
			}
			$query = substr($query, 0, -2);
			$query .= ")";
			
			$query = $query;
			
			if (!($result = $match_report_db->query($query)))
				return false;
			
    	}
    	
    	$query = "DELETE FROM Player_Listing WHERE ID IN (";
    	foreach ($used_player_ids as $used_id) {
    		$query .= "'" . $used_id . "', ";
    	}
    	$query = substr($query, 0, -2);
		$query .= ")";
		
		if (!($result = $match_report_db->query($query)))
				return false;
				
		/*
		 * Close db access
		 */
		unset ($match_report_db);
    }
}
?>
