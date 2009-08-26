<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2009. All Rights Reserved.
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

class Team_HTMLOUT extends Team
{

public function teamPage()
{
    global $coach, $settings;
    
    /* Argument(s) passed to generating functions. */
    $ALLOW_EDIT = (is_object($coach) && ($this->owned_by_coach_id == $coach->coach_id || $coach->admin) && !$this->is_retired); # Show team action boxes?
    $DETAILED   = (isset($_GET['detailed']) && $_GET['detailed'] == 1);# Detailed roster view?

    /* Team pages consist of the output of these generating functions. */
    $this->_handleActions($ALLOW_EDIT); # Handles any actions/request sent.
    list($players, $players_backup) = $this->_loadPlayers($DETAILED); # Should come after _handleActions().
    $this->_roster($ALLOW_EDIT, $DETAILED, $players);
    $players = $players_backup; # Restore the $players array (_roster() manipulates the passed $players array).
    $this->_linksAndStarMercHH($DETAILED);
    $this->_actionBoxes($ALLOW_EDIT, $players);
    $this->_about($ALLOW_EDIT);
    $this->_news($ALLOW_EDIT);
    $this->_recentGames();
}

private function _handleActions($ALLOW_EDIT)
{
    global $coach;
    $team = $this; // Copy. Used instead of $this for readability.

    // No request sent?
    if (!isset($_POST['type']) || !$ALLOW_EDIT) {
        return false;
    }

    // Handle request.
    if (get_magic_quotes_gpc()) {
        $_POST['name']     = stripslashes(isset($_POST['name'])  ? $_POST['name']  : '');
        $_POST['skill']    = stripslashes(isset($_POST['skill']) ? $_POST['skill'] : '');
        $_POST['thing']    = stripslashes(isset($_POST['thing']) ? $_POST['thing'] : '');
        $_POST['teamtext'] = stripslashes(isset($_POST['teamtext']) ? $_POST['teamtext'] : '');
        $_POST['txt']      = stripslashes(isset($_POST['txt']) ? $_POST['txt'] : '');
    }
    
    $p = (isset($_POST['player']) && $_POST['type'] != 'hire_player') ? new Player($_POST['player']) : null;

    switch ($_POST['type']) {

        case 'hire_player':
            $status = Player::create(array(
                'nr'        => $_POST['number'], 
                'position'  => $_POST['player'], 
                'team_id'   => $team->team_id, 
                'name'      => $_POST['name']),
                (isset($_POST['as_journeyman']) && $_POST['as_journeyman']) ? true : false);
            status($status[0], (($status[0] == true) ? null : $status[1]));
            break;

        case 'hire_journeyman': status($p->hireJourneyman()); break;
        case 'fire_player':     status($p->sell()); break;
        case 'rename_player':   status($p->rename($_POST['name'])); break;
        case 'renumber_player': status($p->renumber($_POST['number'])); break;
        case 'rename_team':     status($team->rename($_POST['name'])); break;
        case 'buy_goods':       status($team->buy($_POST['thing'])); break;
        case 'drop_goods':      status($team->drop($_POST['thing'])); break;
        case 'ready_state':     status($team->setReady(isset($_POST['bool']))); break;
        case 'unbuy_player':    status($p->unbuy()); break;
        
        case 'skill':        
            $type = null;
            $p->setChoosableSkills();
            if     (in_array($_POST['skill'], $p->choosable_skills['N skills'])) $type = 'N';
            elseif (in_array($_POST['skill'], $p->choosable_skills['D skills'])) $type = 'D';
            elseif (preg_match('/^ach_/', $_POST['skill']))                      $type = 'C';
            status($p->addSkill($type, $_POST['skill']));
            break;

        case 'teamtext': status($team->saveText($_POST['teamtext'])); break;
        case 'news':     status($team->writeNews($_POST['txt'])); break;
        case 'newsdel':  status($team->deleteNews($_POST['news_id'])); break;
        case 'newsedit': status($team->editNews($_POST['news_id'], $_POST['txt'])); break;

        case 'pic': 
            if (isset($_FILES[Image::$defaultHTMLUploadName.'_stad'])) 
                status($team->saveStadiumPic(Image::$defaultHTMLUploadName.'_stad'));
            elseif (isset($_FILES[Image::$defaultHTMLUploadName.'_logo']))
                status($team->saveLogo(Image::$defaultHTMLUploadName.'_logo'));
            break;
    }

    // Administrator tools used?
    if ($coach->admin) {

        switch ($_POST['type']) {
            
            case 'unhire_journeyman': status($p->unhireJourneyman()); break;
            case 'unsell_player':     status($p->unsell()); break;
            case 'unbuy_goods':       status($team->unbuy($_POST['thing'])); break;
            case 'bank':              status($team->dtreasury($dtreas = ($_POST['sign'] == '+' ? 1 : -1) * $_POST['amount'] * 1000) && SiteLog::create("Coach '$coach->name' (ID=$coach->coach_id) added a treasury delta for team '$team->name' (ID=$team->team_id) of amount = $dtreas", $coach->coach_id)); break;
            case 'chown':             status($team->setOwnership((int) $_POST['cid'])); break;
            case 'spp':               status($p->dspp(($_POST['sign'] == '+' ? 1 : -1) * $_POST['amount'])); break;
            case 'dval':              status($p->dval(($_POST['sign'] == '+' ? 1 : -1) * $_POST['amount']*1000)); break;
            
            case 'extra_skills':
                $func = ($_POST['sign'] == '+') ? 'addSkill' : 'rmSkill';
                status($p->$func('E', $_POST['skill'])); 
                break;
                
            case 'ach_skills':
                $type = null;
                if     (in_array($_POST['skill'], $p->ach_nor_skills))  $type = 'N';
                elseif (in_array($_POST['skill'], $p->ach_dob_skills))  $type = 'D';
                else                                                    $type = 'C'; # Assume it's a characteristic.
                status($p->rmSkill($type, $_POST['skill']));
                break;
        }
    }
}

private function _loadPlayers($DETAILED)
{
    /* 
        Lets prepare the players for the roster.
    */
    global $settings;
    $team = $this; // Copy. Used instead of $this for readability.
    $players = $players_org = array();
    
    $team = new Team($this->team_id); # Update team object in case of changes to team were made by requested actions (_handleActions()).
    $players_org = $team->getPlayers(); 
    // Make two copies: We will be overwriting $players later when the roster has been printed, so that the team actions boxes have the correct untempered player data to work with.
    foreach ($players_org as $p) {
        array_push($players, clone $p);
    }
    // Filter players depending on settings and view mode.
    $tmp_players = array();
    foreach ($players as $p) {
        if (
            !$DETAILED && ($p->is_dead || $p->is_sold) ||
            $DETAILED && !$settings['show_sold_journeymen'] && $p->is_journeyman && $p->is_sold
            ) {
            continue;
        }
        array_push($tmp_players, $p);
    }
    $players = $tmp_players;
    
    return array($players, $players_org);
}

private function _roster($ALLOW_EDIT, $DETAILED, $players)
{
    global $rules, $settings, $lng;
    $team = $this; // Copy. Used instead of $this for readability.

    /******************************
     *
     *   Make the players ready for roster printing.
     *
     ******************************/
     
    foreach ($players as $p) {
    
        /* 
            Misc
        */
        $p->name = preg_replace('/\s/', '&nbsp;', $p->name);
        $p->position = preg_replace('/\s/', '&nbsp;', $p->position);
    
        /* 
            Colors
        */        
        
        // Fictive player color fields used for creating player table.
        $p->HTMLfcolor = '#000000';
        $p->HTMLbcolor = COLOR_HTML_NORMAL;
        
        if     ($p->is_sold && $DETAILED)   $p->HTMLbcolor = COLOR_HTML_SOLD; # Sold has highest priority.
        elseif ($p->is_dead && $DETAILED)   $p->HTMLbcolor = COLOR_HTML_DEAD;
        elseif ($p->is_mng)                 $p->HTMLbcolor = COLOR_HTML_MNG;
        elseif ($p->is_journeyman)          $p->HTMLbcolor = COLOR_HTML_JOURNEY;
        elseif ($p->mayHaveNewSkill())      $p->HTMLbcolor = COLOR_HTML_NEWSKILL;
        elseif ($DETAILED)                  $p->HTMLbcolor = COLOR_HTML_READY;

        $p->skills   = '<small>'.$p->getSkillsStr(true).'</small>';
        $p->injs     = $p->getInjsStr(true);
        $p->position = "<table style='border-spacing:0px;'><tr><td><img align='left' src='$p->icon' alt='player avatar'></td><td>$p->position</td></tr></table>";

        if ($DETAILED) {
            $p->cas = "$p->bh/$p->si/$p->ki";
            $p->spp = "$p->spp/$p->extra_spp";
        }
        
        // Characteristic's colors
        foreach (array('ma', 'ag', 'av', 'st') as $chr) {
            $sub = $p->$chr - $p->{"def_$chr"};
            if ($sub == 0) {
                // Nothing!
            }
            elseif ($sub == 1)  $p->{"${chr}_color"} = COLOR_HTML_CHR_EQP1;
            elseif ($sub > 1)   $p->{"${chr}_color"} = COLOR_HTML_CHR_GTP1;
            elseif ($sub == -1) $p->{"${chr}_color"} = COLOR_HTML_CHR_EQM1;
            elseif ($sub < -1)  $p->{"${chr}_color"} = COLOR_HTML_CHR_LTM1;
        }
        
        /* 
            New skills drop-down.
        */      
          
        $x = '';
        if ($ALLOW_EDIT && $p->mayHaveNewSkill()) {
            $p->setChoosableSkills();
            
            $x .= "<form method='POST'>\n";
            $x .= "<select name='skill'>\n";

            $x .= "<optgroup label='Normal skills'>\n";
            foreach ($p->choosable_skills['N skills'] as $s) {
                $x .= "<option value='$s'>$s</option>\n";
            }
            $x .= "</optgroup>\n";

            $x .= "<optgroup label='Double skills'>\n";
            foreach ($p->choosable_skills['D skills'] as $s) {
                $x .= "<option value='$s'>$s</option>\n";
            }
            $x .= "</optgroup>\n";
            
            $x .= "<optgroup label='Other'>\n";
            foreach (array('ma', 'st', 'ag', 'av') as $s) {
                if ($p->chrLimits('ach', $s))
                    $x .= "<option value='ach_$s'>+ " . ucfirst($s) . "</option>\n";
            }
            $x .= "</optgroup>\n";

            $x .= '
            </select>
            <input type="submit" name="button" value="OK">
            <input type="hidden" name="type" value="skill">
            <input type="hidden" name="player" value="'.$p->player_id.'">
            </form>
            </td>
            ';
        }
        $p->skills .= $x;
    }
    
    /* If enabled add stars and summed mercenaries entries to the roster */
    
    if ($DETAILED && $settings['show_stars_mercs'] && $rules['enable_stars_mercs']) {
    
        $stars = array();
        foreach (Star::getStars(STATS_TEAM, $team->team_id, false, false) as $s) {
            $s->name = preg_replace('/\s/', '&nbsp;', $s->name);
            $s->player_id = $s->star_id;
            $s->nr = 0;
            $s->position = "<table style='border-spacing:0px;'><tr><td><img align='left' src='$s->icon' alt='player avatar'></td><td><i>Star&nbsp;player</i></td></tr></table>";
            $s->skills = '<small>'.implode(', ', $s->skills).'</small>';
            $s->injs = '';
            $s->value = 0;
            $s->setStats(STATS_TEAM, $team->team_id, false, false);
            $s->cas = "$s->bh/$s->si/$s->ki"; // Must come after setStats(), since it else would be overwrited.
            $s->is_dead = $s->is_sold = $s->is_mng = $s->is_journeyman = false;
            $s->HTMLbcolor = COLOR_HTML_STARMERC;
            array_push($stars, $s);
        }
        $players = array_merge($players, $stars);
        
        $smerc = (object) null;
        $smerc->mvp = $smerc->td = $smerc->cp = $smerc->intcpt = $smerc->bh = $smerc->si = $smerc->ki = $smerc->skills = 0;
        foreach (Mercenary::getMercsHiredByTeam($team->team_id) as $merc) {
            $smerc->mvp += $merc->mvp;
            $smerc->td += $merc->td;
            $smerc->cp += $merc->cp;
            $smerc->intcpt += $merc->intcpt;
            $smerc->bh += $merc->bh;
            $smerc->si += $merc->si;
            $smerc->ki += $merc->ki;
            $smerc->skills += $merc->skills;
        }
        $smerc->player_id = ID_MERCS;
        $smerc->nr = 0;
        $smerc->name = 'All&nbsp;mercenary&nbsp;hirings';
        $smerc->position = "<i>Mercenaries</i>";
        $smerc->cas = "$smerc->bh/$smerc->si/$smerc->ki";
        $smerc->ma = '-';
        $smerc->st = '-';
        $smerc->ag = '-';
        $smerc->av = '-';
        $smerc->skills = 'Total bought extra skills: '.$smerc->skills;
        $smerc->injs = '';
        $smerc->spp = '-';
        $smerc->value = 0;
        $smerc->is_dead = $smerc->is_sold = $smerc->is_mng = $smerc->is_journeyman = false;
        $smerc->HTMLbcolor = COLOR_HTML_STARMERC;
        array_push($players, $smerc);
    }

    /******************************
     * Team players table
     * ------------------
     *
     * Contains player information and menu(s) for skill choice.
     *
     ******************************/

    title($team->name . (($team->is_retired) ? ' <font color="red"> (Retired)</font>' : ''));
    
    $fields = array(
        'nr'        => array('desc' => 'Nr.'), 
        'name'      => array('desc' => 'Name', 'href' => array('link' => 'index.php?section=coachcorner', 'field' => 'player_id', 'value' => 'player_id')),
        'position'  => array('desc' => 'Position', 'nosort' => true), 
        'ma'        => array('desc' => 'Ma'), 
        'st'        => array('desc' => 'St'), 
        'ag'        => array('desc' => 'Ag'), 
        'av'        => array('desc' => 'Av'), 
        'skills'    => array('desc' => 'Skills', 'nosort' => true),
        'injs'      => array('desc' => 'Injuries', 'nosort' => true),
        'cp'        => array('desc' => 'Cp'), 
        'td'        => array('desc' => 'Td'), 
        'intcpt'    => array('desc' => 'Int'), 
        'cas'       => array('desc' => ($DETAILED) ? 'BH/SI/Ki' : 'Cas', 'nosort' => ($DETAILED) ? true : false),
        'mvp'       => array('desc' => 'MVP'), 
        'spp'       => array('desc' => ($DETAILED) ? 'SPP/extra' : 'SPP', 'nosort' => ($DETAILED) ? true : false),
        'value'     => array('desc' => 'Value', 'kilo' => true, 'suffix' => 'k'),  
    );

    HTMLOUT::sort_table(
        $lng->getTrn('secs/teams/playersof').' '.$team->name, 
        "index.php?section=coachcorner&amp;team_id=$team->team_id".(($DETAILED) ? '&amp;detailed=1' : '&amp;detailed=0'), 
        $players, 
        $fields, 
        ($DETAILED) ? array('+is_dead', '+is_sold', '+is_mng', '+is_journeyman', '+nr', '+name') : sort_rule('player'), 
        (isset($_GET['sort'])) ? array((($_GET['dir'] == 'a') ? '+' : '-') . $_GET['sort']) : array(),
        array('color' => ($DETAILED) ? true : false, 'doNr' => false, 'noHelp' => true)
    );
}

private function _linksAndStarMercHH($DETAILED)
{
    /* 
        Show color descriptions in detailed view and links to special team page actions. 
    */

    global $lng, $rules, $settings;
    $team = $this; // Copy. Used instead of $this for readability.

    ?>
    <table class="text">
        <tr>
            <td style="width: 100%;"> </td>
            <?php
            if ($DETAILED) {
                ?>
                <td style="background-color: <?php echo COLOR_HTML_READY;   ?>;"><font color='black'>Ready</font></td>
                <td style="background-color: <?php echo COLOR_HTML_MNG;     ?>;"><font color='black'>MNG</font></td>
                <td style="background-color: <?php echo COLOR_HTML_JOURNEY; ?>;"><font color='black'>Journey</font></td>
                <td style="background-color: <?php echo COLOR_HTML_DEAD;    ?>;"><font color='black'>Dead</font></td>
                <td style="background-color: <?php echo COLOR_HTML_SOLD;    ?>;"><font color='black'>Sold</font></td>
                <td style="background-color: <?php echo COLOR_HTML_STARMERC;?>;"><font color='black'>Star/merc</font></td>
                <td style="background-color: <?php echo COLOR_HTML_NEWSKILL;?>;"><font color='black'>New&nbsp;skill</font></td>
                <?php
            }
            ?>
        </tr>
        <tr>
            <td colspan="8">
            <?php
            $botocs = "";
            if ($settings['leegmgr_enabled']) $botocs = " <a href='handler.php?type=botocsxml&amp;teamid=$_GET[team_id]'>BOTOCS-XML</a>";
            echo "<a href='index.php?section=coachcorner&amp;team_id=$_GET[team_id]&amp;detailed=".(($DETAILED) ? 0 : 1)."'><b>".(($DETAILED) ? $lng->getTrn('secs/teams/n_view') : $lng->getTrn('secs/teams/d_view'))."</b></a>\n";
            echo "&nbsp;|&nbsp;<b><a href='handler.php?type=roster&amp;team_id=$_GET[team_id]&amp;detailed=" . ($DETAILED ? '1' : '0') . "'>PDF</a> <a href='handler.php?type=xmlexport&amp;tid=$_GET[team_id]'>XML</a>{$botocs} ".$lng->getTrn('secs/teams/roster')."</b>\n";
            if ($rules['enable_stars_mercs']) {
                echo "&nbsp;|&nbsp;<a href='javascript:void(0)' onClick=\"shh=document.getElementById('SHH'); if (shh.style.display != 'none'){shh.style.display='none'}else{shh.style.display='block'};\" title='Show/hide star hire history'><b>Star HH</b></a>\n";
                echo "&nbsp;|&nbsp;<a href='javascript:void(0)' onClick=\"mhh=document.getElementById('MHH'); if (mhh.style.display != 'none'){mhh.style.display='none'}else{mhh.style.display='block'};\" title='Show/hide mercenary hire history'><b>Merc. HH</b></a>\n";
            }
            echo "&nbsp;|&nbsp;<a href='#anc_news'><b>News</b></a>\n";
            echo "&nbsp;|&nbsp;<a href='handler.php?type=inducements&amp;team_id=$team->team_id'><b>".$lng->getTrn('secs/teams/indctry')."</b></a>\n";
            echo "&nbsp;|&nbsp;<a href='handler.php?type=graph&amp;gtype=".SG_T_TEAM."&amp;id=$team->team_id''><b>Vis. stats</b></a>\n";
            ?>
            </td>
        </tr>
        <tr><td class='seperator' colspan='8'></td></tr>
        <tr>
            <td colspan='8'>
            <div id='SHH'>
                <?php
                if ($rules['enable_stars_mercs']) {
                    HTMLOUT::starHireHistory(STATS_TEAM, $team->team_id, false, false, false, array(
                        'url' => "index.php?section=coachcorner&amp;team_id=$team->team_id".(($DETAILED) ? '&amp;detailed=1' : '&amp;detailed=0'), 
                        'GET_SS' => 'shh', 
                        'anchor' => 'shhanc')
                    );
                }
                ?>
            </div>
            </td>
        </tr>
        <tr><td class='seperator' colspan='8'></td></tr>
        <tr>
            <td colspan='8'>
            <div id='MHH'>
                <?php
                if ($rules['enable_stars_mercs']) {
                    $mdat = array();
                    foreach (Mercenary::getMercsHiredByTeam($team->team_id, false) as $merc) {
                        $o = (object) array();
                        $m = new Match($merc->match_id);
                        $o->date_played = $m->date_played;
                        $o->opponent = ($m->team1_id == $team->team_id) ? $m->team1_name : $m->team2_name;
                        foreach (array('match_id', 'skills', 'mvp', 'cp', 'td', 'intcpt', 'bh', 'ki', 'si') as $f) {
                            $o->$f = $merc->$f;
                        }
                        $o->cas = $o->bh+$o->ki+$o->si;
                        $o->match = '[view]';
                        $o->tour = get_alt_col('tours', 'tour_id', $m->f_tour_id, 'name');
                        $o->score = "$m->team1_score - $m->team2_score";
                        $o->result = matchresult_icon(
                            (
                            ($m->team1_id == $team->team_id && $m->team1_score > $m->team2_score) ||
                            ($m->team2_id == $team->team_id && $m->team1_score < $m->team2_score)
                            ) 
                                ? 'W'
                                : (($m->team1_score == $m->team2_score) ? 'D' : 'L')
                        );
                        
                        array_push($mdat, $o);
                    }
                    $fields = array(
                        'date_played'   => array('desc' => 'Hire date'), 
                        'tour'          => array('desc' => 'Tournament'),
                        'opponent'      => array('desc' => 'Opponent team'), 
                        'skills' => array('desc' => 'Add. skills'), 
                        'cp'     => array('desc' => 'Cp'), 
                        'td'     => array('desc' => 'Td'), 
                        'intcpt' => array('desc' => 'Int'), 
                        'cas'    => array('desc' => 'Cas'), 
                        'bh'     => array('desc' => 'BH'), 
                        'si'     => array('desc' => 'Si'), 
                        'ki'     => array('desc' => 'Ki'), 
                        'mvp'    => array('desc' => 'MVP'), 
                        'score'  => array('desc' => 'Score', 'nosort' => true),
                        'result' => array('desc' => 'Result', 'nosort' => true),
                        'match'  => array('desc' => 'Match', 'href' => array('link' => 'index.php?section=fixturelist', 'field' => 'match_id', 'value' => 'match_id'), 'nosort' => true), 
                    );
                    HTMLOUT::sort_table(
                        "<a name='mhhanc'>Mercenary hiring history</a>", 
                        "index.php?section=coachcorner&amp;team_id=$team->team_id".(($DETAILED) ? '&amp;detailed=1' : '&amp;detailed=0'), 
                        $mdat, 
                        $fields, 
                        sort_rule('star_HH'), 
                        (isset($_GET['sortmhh'])) ? array((($_GET['dirmhh'] == 'a') ? '+' : '-') . $_GET['sortmhh']) : array(),
                        array('GETsuffix' => 'mhh', 'doNr' => false, 'anchor' => 'mhhanc')
                    );
                }
                ?>
            </div>
            </td>
        </tr>
    </table>  
    
    <script language="JavaScript" type="text/javascript">
        <?php if (!isset($_GET['sortshh'])) echo "document.getElementById('SHH').style.display='none';\n"?>
        <?php if (!isset($_GET['sortmhh'])) echo "document.getElementById('MHH').style.display='none';\n"?>
    </script>
    <?php
}

private function _actionBoxes($ALLOW_EDIT, $players)
{
    /******************************
     * Team management
     * ---------------
     *   
     * Here we are able to view team stats and manage the team, depending on visitors privileges.
     *
     ******************************/
     
    global $lng, $rules, $skillarray, $coach, $DEA;
    $team = $this; // Copy. Used instead of $this for readability.
    $JMP_ANC = (isset($_POST['menu_tmanage']) || isset($_POST['menu_admintools'])); # Jump condition MUST be set here due to _POST variables being changed later.
     
    ?>
    <div class="tpageBox">
        <div class="boxTitle1"><a name='aanc'><?php echo $lng->getTrn('secs/teams/box_info/title');?></a></div>
        <div class="boxBody">
            <table width="100%">
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/coach');?></td>
                    <td><a href="index.php?section=coaches&amp;coach_id=<?php echo $team->owned_by_coach_id;?>"><?php echo $team->coach_name; ?></a></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/race');?></td>
                    <td><a href='index.php?section=races&amp;race=<?php echo $team->f_race_id; ?>'><?php echo $team->race; ?></a></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/ready');?></td>
                    <td><?php echo ($team->rdy) ? $lng->getTrn('secs/teams/yes') : $lng->getTrn('secs/teams/no'); ?></td>
                </tr>                
                <tr>
                    <td>TV</td>
                    <td><?php echo $team->value/1000 . 'k'; ?></td>
                </tr>
                <tr>
                    <td>Treasury</td>
                    <td><?php echo $team->treasury/1000 . 'k'; ?></td>
                </tr>
                <tr>
                <?php
                if ($team->race == 'Necromantic' || $team->race == 'Undead') {
                    ?>
                    <td>Necromancer</td>
                    <td>Yes</td>
                    <?php
                }
                elseif ($team->race != 'Khemri' && $team->race != 'Nurgle') {
                    echo "<td>Apothecary</td>\n";
                    echo "<td>" . ($team->apothecary ? $lng->getTrn('secs/teams/yes') : $lng->getTrn('secs/teams/no')) . "</td>\n";
                }
                ?>
                </tr>
                <tr>
                    <td>Rerolls</td>
                    <td><?php echo $team->rerolls; ?></td>
                </tr>
                <tr>
                    <td>Fan&nbsp;Factor</td>
                    <td><?php echo $team->fan_factor; ?></td>
                </tr>
                <tr>
                    <td>Ass.&nbsp;Coaches</td>
                    <td><?php echo $team->ass_coaches; ?></td>
                </tr>
                <tr>
                    <td>Cheerleaders</td>
                    <td><?php echo $team->cheerleaders; ?></td>
                </tr>
                <tr>
                    <td colspan=2><hr></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/gp');?></td>
                    <td><?php echo $team->played; ?></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/pct_won');?></td>
                    <td><?php echo sprintf("%1.1f", $team->win_percentage).'%'; ?></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/tours_won');?></td>
                    <td><?php echo $team->won_tours; ?></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/ws');?></td>
                    <td><?php echo $team->row_won; ?></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/ls');?></td>
                    <td><?php echo $team->row_lost; ?></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/ds');?></td>
                    <td><?php echo $team->row_draw; ?></td>
                </tr>
                <tr>
                    <td><?php echo $lng->getTrn('secs/teams/box_info/ltour');?></td>
                    <td><?php $lt = $team->getLatestTour(); echo ($lt) ? get_alt_col('tours', 'tour_id', $lt, 'name') : '<i>'.$lng->getTrn('secs/teams/none').'</i>'; ?></td>
                </tr>
                <tr valign="top">
                    <td><?php echo $lng->getTrn('secs/teams/box_info/toursplayed');?></td>
                    <td><small><?php $tours = $team->getToursPlayedIn(false); echo (empty($tours)) ? '<i>'.$lng->getTrn('secs/teams/none').'</i>' : implode(', ', array_map(create_function('$val', 'return $val->name;'), $tours)); ?></small></td>
                </tr>
                <tr valign="top">
                    <td><?php echo $lng->getTrn('secs/teams/box_info/prizes');?></td>
                    <td><small><?php $prizes = $team->getPrizes(true); echo (empty($prizes)) ? '<i>'.$lng->getTrn('secs/teams/none').'</i>' : $prizes; ?></small></td>
                </tr>
            </table>
        </div>
    </div>
    
    <?php
    if ($ALLOW_EDIT) {
        ?>
        <div class="tpageBox">
            <div class="boxTitle2"><?php echo $lng->getTrn('secs/teams/box_tm/title');?></div>
            <div class="boxBody">
                <?php
                
                $tmanage = array(
                    'hire_player'       => $lng->getTrn('secs/teams/box_tm/hire_player'),
                    'hire_journeyman'   => $lng->getTrn('secs/teams/box_tm/hire_journeyman'),
                    'fire_player'       => $lng->getTrn('secs/teams/box_tm/fire_player'),
                    'rename_player'     => $lng->getTrn('secs/teams/box_tm/rename_player'),
                    'renumber_player'   => $lng->getTrn('secs/teams/box_tm/renumber_player'),
                    'rename_team'       => $lng->getTrn('secs/teams/box_tm/rename_team'),
                    'buy_goods'         => $lng->getTrn('secs/teams/box_tm/buy_goods'),
                    'drop_goods'        => $lng->getTrn('secs/teams/box_tm/drop_goods'),
                    'ready_state'       => $lng->getTrn('secs/teams/box_tm/ready_state'),
                    'unbuy_player'      => $lng->getTrn('secs/teams/box_tm/unbuy_player'),
                );

                // Set default choice.
                if (!isset($_POST['menu_tmanage'])) {
                    reset($tmanage);
                    $_POST['menu_tmanage'] = key($tmanage);
                }

                // If action is already chosen, then make it the default selected.
                if (isset($_POST['type']) && array_key_exists($_POST['type'], $tmanage)) {
                    $_POST['menu_tmanage'] = $_POST['type'];
                }
                
                ?>
                <form method="POST">
                    <select name="menu_tmanage">
                        <?php
                        foreach ($tmanage as $opt => $desc)
                            echo "<option value='$opt'" . ($_POST['menu_tmanage'] == $opt ? 'SELECTED' : '') . ">$desc</option>";
                        ?>
                    </select>
                    <input type="submit" name="tmanage" value="OK">
                </form>

                <br><i><?php echo $lng->getTrn('secs/teams/desc');?>:</i><br><br>
                <form name="form_tmanage" method="POST" enctype="multipart/form-data">
                <?php
                $DISABLE = false;
                
                switch ($_POST['menu_tmanage']) {
                
                    /**************
                     * Hire player
                     **************/
                        
                    case 'hire_player':
                        echo $lng->getTrn('secs/teams/box_tm/desc/hire_player');
                        ?>
                        <hr><br>
                        Player:<br>
                        <select name='player'>
                        <?php
                        $active_players = array_filter($players, create_function('$p', "return (\$p->is_sold || \$p->is_dead || \$p->is_mng) ? false : true;"));
                        $DISABLE = true;
                        foreach ($DEA[$team->race]['players'] as $pos => $details) {
                        
                            // Show players on the select list if buyable, or if player is a potential journeyman AND team has not reached journeymen limit.
                            if (($team->isPlayerBuyable($pos) && $team->treasury >= $details['cost']) || 
                                (($details['qty'] == 16 || (($rules['enable_lrb6x']) ? ($details['qty'] == 12) : false)) && count($active_players) < $rules['journeymen_limit'])) {
                                echo "<option value='$pos'>" . $details['cost']/1000 . "k | $pos</option>\n";
                                $DISABLE = false;
                            }
                        }
                        echo "</select>\n";
                        ?>
                        <br><br>
                        Number:<br>
                        <select name="number">
                        <?php
                        foreach (range(1, MAX_PLAYER_NR) as $i) {
                            foreach ($players as $p) {
                                if ($p->nr == $i && !$p->is_sold && !$p->is_dead)
                                    continue 2;
                            }
                            echo "<option value='$i'>$i</option>\n";
                        }
                        ?>
                        </select>
                        <br><br>
                        As journeyman: <input type="checkbox" name="as_journeyman" value="1">
                        <br><br>
                        Name:<br>
                        <input type="text" name="name">
                        <input type="hidden" name="type" value="hire_player">
                        <?php
                        break;
                        
                    /**************
                     * Hire journeymen
                     **************/
                    
                    case 'hire_journeyman':
                        echo $lng->getTrn('secs/teams/box_tm/desc/hire_journeyman');
                        ?>
                        <hr><br>
                        Player:<br>
                        <select name="player">
                        <?php
                        $DISABLE = true;
                        foreach ($players as $p) {
                            $price = $DEA[$team->race]['players'][$p->pos]['cost'];
                            if (!$p->is_journeyman || $p->is_sold || $p->is_dead || 
                                $team->treasury < $price || !$team->isPlayerBuyable($p->pos) || $team->isFull()) {
                                continue;
                            }

                            echo "<option value='$p->player_id'>$p->name | " . $price/1000 . " k</option>\n";
                            $DISABLE = false;
                        }
                        ?>
                        </select>
                        <input type="hidden" name="type" value="hire_journeyman">
                        <?php
                        break;

                    /**************
                     * Fire player
                     **************/
                        
                    case 'fire_player':
                        echo $lng->getTrn('secs/teams/box_tm/desc/fire_player').' '.$rules['player_refund']*100 . "%.\n";
                        ?>
                        <hr><br>
                        Player:<br>
                        <select name="player">
                        <?php
                        $DISABLE = true;
                        foreach ($players as $p) {
                            if ($p->is_dead || $p->is_sold)
                                continue;

                            echo "<option value='$p->player_id'>" . (($p->value/1000)*$rules['player_refund']) . "k refund | $p->name</option>\n";
                            $DISABLE = false;
                        }
                        ?>
                        </select>
                        <input type="hidden" name="type" value="fire_player">
                        <?php
                        break;
                        
                    /**************
                     * Rename player
                     **************/
                        
                    case 'rename_player':
                        echo $lng->getTrn('secs/teams/box_tm/desc/rename_player');
                        ?>
                        <hr><br>
                        Player:<br>
                        <select name="player">
                        <?php
                        $DISABLE = true;
                        foreach ($players as $p) {
                            unset($color);
                            if ($p->is_dead)
                                $color = COLOR_HTML_DEAD;
                            elseif ($p->is_sold)
                                $color = COLOR_HTML_SOLD;

                            echo "<option value='$p->player_id' ".(isset($color) ? "style='background-color: $color;'" : '').">$p->name</option>\n";
                            $DISABLE = false;
                        }
                        ?>
                        </select>
                        <br><br>
                        New name:<br>
                        <input type='text' name='name' maxlength=50 size=20>
                        <input type="hidden" name="type" value="rename_player">
                        <?php
                        break;

                    /**************
                     * Renumber player
                     **************/
                        
                    case 'renumber_player':
                        echo $lng->getTrn('secs/teams/box_tm/desc/renumber_player');
                        ?>
                        <hr><br>
                        Player:<br>
                        <select name="player">
                        <?php
                        $DISABLE = true;
                        foreach ($players as $p) {
                            unset($color);
                            if ($p->is_dead)
                                $color = COLOR_HTML_DEAD;
                            elseif ($p->is_sold)
                                $color = COLOR_HTML_SOLD;

                            echo "<option value='$p->player_id' ".(isset($color) ? "style='background-color: $color;'" : '').">$p->name</option>\n";
                            $DISABLE = false;
                        }
                        ?>
                        </select>
                        <br><br>
                        Number:<br>
                        <select name="number">
                        <?php
                        foreach (range(1, MAX_PLAYER_NR) as $i) {
                            echo "<option value='$i'>$i</option>\n";
                        }
                        ?>
                        </select>
                        <input type="hidden" name="type" value="renumber_player">
                        <?php
                        break;
                        
                    /**************
                     * Rename team
                     **************/
                        
                    case 'rename_team':
                        echo $lng->getTrn('secs/teams/box_tm/desc/rename_team');
                        ?>
                        <hr><br>
                        New name:<br>
                        <input type='text' name='name' maxlength='50' size='20'>
                        <input type="hidden" name="type" value="rename_team">
                        <?php
                        break;
                        
                    /**************
                     * Buy team goods
                     **************/
                        
                    case 'buy_goods':
                        echo $lng->getTrn('secs/teams/box_tm/desc/buy_goods');
                        $goods_temp = $team->getGoods();
                        if ($DEA[$team->race]['other']['RerollCost'] != $goods_temp['rerolls']['cost']) {
                            echo $lng->getTrn('secs/teams/box_tm/desc/buy_goods_warn');
                        }
                        ?>
                        <hr><br>
                        Thing:<br>
                        <select name="thing">
                        <?php
                        $DISABLE = true;
                        foreach ($team->getGoods() as $name => $details) {
                            if ($name == 'fan_factor' && !$rules['post_game_ff'] && $team->played > 0)
                                continue;
                            if (($team->$name < $details['max'] || $details['max'] == -1) && $team->treasury >= $details['cost']) {
                                echo "<option value='$name'>" . $details['cost']/1000 . "k | $details[item]</option>\n";
                                $DISABLE = false;
                            }
                        }
                        ?>
                        </select>
                        <input type="hidden" name="type" value="buy_goods">
                        <?php
                        break;
                        
                    /**************
                     * Let go (drop) of team goods
                     **************/
                        
                    case 'drop_goods':
                        echo $lng->getTrn('secs/teams/box_tm/desc/drop_goods');
                        ?>
                        <hr><br>
                        Thing:<br>
                        <select name="thing">
                        <?php
                        $DISABLE = true;
                        foreach ($team->getGoods() as $name => $details) {
                            if ($name == 'fan_factor' && !$rules['post_game_ff'] && $team->played > 0)
                                continue;
                            if ($team->$name > 0) {
                                echo "<option value='$name'>$details[item]</option>\n";
                                $DISABLE = false;
                            }
                        }
                        ?>
                        </select>
                        <input type="hidden" name="type" value="drop_goods">
                        <?php
                        break;
                        
                    /**************
                     * Set ready state
                     **************/
                        
                    case 'ready_state':
                        echo $lng->getTrn('secs/teams/box_tm/desc/ready_state');
                        ?>
                        <hr><br>
                        Team ready? 
                        <input type="checkbox" name="bool" value="1" <?php echo ($team->rdy) ? 'CHECKED' : '';?>>
                        <input type="hidden" name="type" value="ready_state">
                        <?php
                        break;
                        
                    /***************
                     * Un-buy player
                     **************/
                        
                    case 'unbuy_player':
                        echo $lng->getTrn('secs/teams/box_tm/desc/unbuy_player');
                        ?>
                        <hr><br>
                        Player:<br>
                        <select name="player">
                        <?php
                        $DISABLE = true;
                        foreach ($players as $p) {
                            if ($p->is_unbuyable() && !$p->is_sold) {
                                    echo "<option value='$p->player_id'>$p->name</option>\n";
                                    $DISABLE = false;
                            }
                        }
                        ?>
                        </select>
                        <input type="hidden" name="type" value="unbuy_player">
                        <?php
                        break;
                    }
                    ?>
                    <br><br>
                    <input type="submit" name="button" value="OK" <?php echo ($DISABLE ? 'DISABLED' : '');?> >
                </form>
            </div>
        </div>
        <?php
        if ($coach->admin) {
            ?>
            <div class="tpageBox">
                <div class="boxTitle3"><?php echo $lng->getTrn('secs/teams/box_admin/title');?></div>
                <div class="boxBody">
                    <?php

                    $admin_tools = array(
                        'unhire_journeyman' => $lng->getTrn('secs/teams/box_admin/unhire_journeyman'),
                        'unsell_player'     => $lng->getTrn('secs/teams/box_admin/unsell_player'),
                        'unbuy_goods'       => $lng->getTrn('secs/teams/box_admin/unbuy_goods'),
                        'bank'              => $lng->getTrn('secs/teams/box_admin/bank'),
                        'chown'             => $lng->getTrn('secs/teams/box_admin/chown'),
                        'spp'               => $lng->getTrn('secs/teams/box_admin/spp'),
                        'dval'              => $lng->getTrn('secs/teams/box_admin/dval'),
                        'extra_skills'      => $lng->getTrn('secs/teams/box_admin/extra_skills'),
                        'ach_skills'        => $lng->getTrn('secs/teams/box_admin/ach_skills'),
                    );

                    // Set default choice.
                    if (!isset($_POST['menu_admintools'])) {
                        reset($admin_tools);
                        $_POST['menu_admintools'] = key($admin_tools);
                    }

                    // If action is already chosen, then make it the default selected.
                    if (isset($_POST['type']) && array_key_exists($_POST['type'], $admin_tools)) {
                        $_POST['menu_admintools'] = $_POST['type'];
                    }
                    
                    ?>
                    <form method="POST">
                        <select name="menu_admintools">
                            <?php
                            foreach ($admin_tools as $opt => $desc)
                                echo "<option value='$opt'" . ($_POST['menu_admintools'] == $opt ? 'SELECTED' : '') . ">$desc</option>";
                            ?>
                        </select>
                        <input type="submit" name="admintools" value="OK">
                    </form>

                    <br><i><?php echo $lng->getTrn('secs/teams/desc');?>:</i><br><br>
                    <form name='form_admintools' method='POST'>
                        <?php
                        $DISABLE = false;

                        switch ($_POST['menu_admintools']) {

                            /***************
                             * Un-hire journeymen
                             **************/

                            case 'unhire_journeyman':
                                echo $lng->getTrn('secs/teams/box_admin/desc/unhire_journeyman');
                                ?>
                                <hr><br>
                                Player:<br>
                                <select name="player">
                                <?php
                                $DISABLE = true;
                                foreach ($players as $p) {
                                    if ($p->is_sold || $p->is_dead || $p->is_journeyman || $p->qty != 16)
                                        continue;
                                        
                                    echo "<option value='$p->player_id'>$p->name</option>\n";
                                    $DISABLE = false;
                                }
                                ?>
                                </select>
                                <input type="hidden" name="type" value="unhire_journeyman">
                                <?php
                                break;

                            /***************
                             * Un-sell player
                             **************/
                                
                            case 'unsell_player':
                                echo $lng->getTrn('secs/teams/box_admin/desc/unsell_player');
                                ?>
                                <hr><br>
                                Player:<br>
                                <select name="player">
                                <?php
                                $DISABLE = true;
                                foreach ($players as $p) {
                                    if ($p->is_sold) {
                                            echo "<option value='$p->player_id'>$p->name</option>\n";
                                            $DISABLE = false;
                                    }
                                }
                                ?>
                                </select>
                                <input type="hidden" name="type" value="unsell_player">
                                <?php
                                break;
                                
                            /***************
                             * Un-buy team goods
                             **************/
                                
                            case 'unbuy_goods':
                                echo $lng->getTrn('secs/teams/box_admin/desc/unbuy_goods');
                                ?>
                                <hr><br>
                                <select name="thing">
                                <?php
                                $DISABLE = true;
                                    foreach ($team->getGoods() as $name => $details) {
                                    if ($team->$name > 0) { # Only allow to un-buy those things which we already have some of.
                                        echo "<option value='$name'>$details[item]</option>\n";
                                        $DISABLE = false;
                                    }
                                }
                                ?>
                                </select>
                                <input type="hidden" name="type" value="unbuy_goods">
                                <?php
                                break;
                                
                            /***************
                             * Gold bank
                             **************/
                                
                            case 'bank':
                                echo $lng->getTrn('secs/teams/box_admin/desc/bank');
                                ?>
                                <hr><br>
                                &Delta; team treasury:<br>
                                <input type="radio" CHECKED name="sign" value="+">+
                                <input type="radio" name="sign" value="-">-
                                <input type='text' name="amount" maxlength=5 size=5>k
                                <input type="hidden" name="type" value="bank">
                                <?php
                                break;

                            /***************
                             * Change team ownership
                             **************/
                                
                            case 'chown':
                                echo $lng->getTrn('secs/teams/box_admin/desc/chown');
                                ?>
                                <hr><br>
                                New owner:<br>
                                <select name="cid">
                                <?php
                                foreach (Coach::getCoaches() as $c) {
                                    echo "<option value='$c->coach_id'>$c->name</option>\n";
                                }
                                ?>
                                </select>
                                <input type="hidden" name="type" value="chown">
                                <?php
                                break;
                                
                            /***************
                             * Manage extra SPP
                             **************/
                                
                            case 'spp':
                                echo $lng->getTrn('secs/teams/box_admin/desc/spp');
                                ?>
                                <hr><br>
                                Player:<br>
                                <select name="player">
                                <?php
                                $DISABLE = true;
                                objsort($players, array('+is_dead', '+name'));
                                foreach ($players as $p) {
                                    if (!$p->is_sold) {
                                        echo "<option value='$p->player_id'".(($p->is_dead) ? ' style="background-color:'.COLOR_HTML_DEAD.';"' : '').">$p->name</option>";
                                        $DISABLE = false;
                                    }
                                }
                                objsort($players, array('+nr'));
                                ?>
                                </select>
                                <br><br>
                                <input type="radio" CHECKED name="sign" value="+">+
                                <input type="radio" name="sign" value="-">-
                                <input type='text' name='amount' maxlength="5" size="5"> &Delta; SPP
                                <input type="hidden" name="type" value="spp">
                                <?php
                                break;

                            /***************
                             * Manage extra player value
                             **************/
                                
                            case 'dval':
                                echo $lng->getTrn('secs/teams/box_admin/desc/dval');
                                ?>
                                <hr><br>
                                Player:<br>
                                <select name="player">
                                <?php
                                $DISABLE = true;
                                objsort($players, array('+is_dead', '+name'));
                                foreach ($players as $p) {
                                    if (!$p->is_sold) {
                                        echo "<option value='$p->player_id'".(($p->is_dead) ? ' style="background-color:'.COLOR_HTML_DEAD.';"' : '').">$p->name (current extra = ".($p->extra_val/1000)."k)</option>";
                                        $DISABLE = false;
                                    }
                                }
                                objsort($players, array('+nr'));
                                ?>
                                </select>
                                <br><br>
                                Set extra value to<br>
                                <input type="radio" CHECKED name="sign" value="+">+
                                <input type="radio" name="sign" value="-">-
                                <input type='text' name='amount' maxlength="10" size="6">k
                                <input type="hidden" name="type" value="dval">
                                <?php
                                break;

                            /***************
                             * Manage extra skills
                             **************/
                                
                            case 'extra_skills':
                                echo $lng->getTrn('secs/teams/box_admin/desc/extra_skills');
                                ?>
                                <hr><br>
                                Player:<br>
                                <select name="player">
                                <?php
                                $DISABLE = true;
                                foreach ($players as $p) {
                                    if (!$p->is_sold && !$p->is_dead) {
                                        echo "<option value='$p->player_id'>$p->name</option>";
                                        $DISABLE = false;
                                    }
                                }
                                ?>
                                </select>
                                <br><br>
                                Skill:<br>
                                <select name="skill">
                                <?php
                                foreach ($skillarray as $cat => $skills) {
                                    if ($cat == 'Achieved characteristics')
                                        continue;
                                        
                                    echo "<OPTGROUP LABEL='$cat'>";
                                    foreach ($skills as $skill) {
                                        echo "<option value='$skill'>$skill</option>";
                                    }
                                    echo "</OPTGROUP>";
                                }
                                ?>
                                </select>
                                <br><br>
                                Action (add/remove)<br>
                                <input type="radio" CHECKED name="sign" value="+">+
                                <input type="radio" name="sign" value="-">-
                                <input type="hidden" name="type" value="extra_skills">
                                <?php
                                break;

                            /***************
                             * Remove achived skills
                             **************/
                                
                            case 'ach_skills':
                                echo $lng->getTrn('secs/teams/box_admin/desc/ach_skills');
                                ?>
                                <hr><br>
                                Player:<br>
                                <select name="player">
                                <?php
                                $DISABLE = true;
                                foreach ($players as $p) {
                                    if (!$p->is_dead && !$p->is_sold) {
                                        echo "<option value='$p->player_id'>$p->name</option>\n";
                                        $DISABLE = false;
                                    }
                                }
                                ?>
                                </select>
                                <br><br>
                                Skill<br>
                                <select name="skill">
                                <?php
                                foreach ($skillarray as $cat => $skills) {
                                    if ($cat == 'Achieved characteristics')
                                        continue;
                                        
                                    echo "<OPTGROUP LABEL='$cat'>";
                                    foreach ($skills as $skill) {
                                        echo "<option value='$skill'>$skill</option>";
                                    }
                                    echo "</OPTGROUP>";
                                }
                                echo "<optgroup label='Other'>\n";
                                foreach (array('ma', 'st', 'ag', 'av') as $type) {
                                    echo "<option value='ach_$type'>+ " . ucfirst($type) . "</option>\n";
                                }
                                echo "</optgroup>\n";
                                ?>
                                </select>
                                <input type="hidden" name="type" value="ach_skills">
                                <?php
                                break;
                        }
                        ?>
                        <br><br>
                        <input type="submit" name="button" value="OK" <?php echo ($DISABLE ? 'DISABLED' : '');?> >
                    </form>
                </div>
            </div>
            <?php
        }
    }
    
    // If an team action was chosen, jump to actions HTML anchor.
    if ($JMP_ANC) {
        ?>
        <script language="JavaScript" type="text/javascript">
        window.location = "#aanc";
        </script>
        <?php
    }
}

private function _about($ALLOW_EDIT)
{
    global $lng;
    $team = $this; // Copy. Used instead of $this for readability.

    title("<a name='anc_about'>".$lng->getTrn('secs/teams/about')." $team->name</a>");
    ?>
    <table class='picAndText'>
        <tr>
            <td class='light'><b><?php echo $lng->getTrn('secs/teams/logo');?></b></td>
            <td class='light'><b><?php echo $lng->getTrn('secs/teams/stad');?></b></td>
            <td class='light'><b><?php echo $lng->getTrn('secs/teams/about');?></b></td>
        </tr>
        <tr>
            <td>
                <?php
                Image::makeBox(IMGTYPE_TEAMLOGO, $team->team_id, $ALLOW_EDIT, '_logo');
                ?>
            </td>
            <td>
                <?php
                Image::makeBox(IMGTYPE_TEAMSTADIUM, $team->team_id, $ALLOW_EDIT, '_stad');
                ?>
            </td>
            <td valign='top' style='width: 100%;'>
                <?php
                $txt = $team->getText();
                if (empty($txt)) {
                    $txt = $lng->getTrn('secs/teams/nowrite')." $team->name."; 
                }
                
                if ($ALLOW_EDIT) {
                    ?>
                    <form method='POST'>
                        <textarea name='teamtext' rows='15' style='width: 100%;'><?php echo $txt;?></textarea>
                        <br><br>
                        <input type="hidden" name="type" value="teamtext">
                        <center>
                        <input type="submit" name='Save' value='<?php echo $lng->getTrn('secs/teams/save');?>'>
                        </center>
                    </form>
                    <?php
                }
                else {
                    echo '<p>'.$txt."</p>\n";
                }
                ?>
            </td>
        </tr>
    </table>
    <?php
}

private function _news($ALLOW_EDIT)
{
    global $lng;
    $team = $this; // Copy. Used instead of $this for readability.
    
    title("<a name='anc_news'>".$lng->getTrn('secs/teams/news')."</a>");
    $news = $team->getNews(MAX_TNEWS);
    ?>
    <div class="row">
        <div class="tnewsBox">
            <div class="boxTitle1"><?php echo $lng->getTrn('secs/teams/tnews');?></div>
            <div class="boxBody">
            <?php
            $news_2 = array();
            foreach ($news as $n) {
                $news_2[] = '<p>'.$n->txt.
                '<div id="newsedit'.$n->news_id.'" style="display:none; clear:both;"><form method="POST">
                    <textarea name="txt" cols="60" rows="4">'.$n->txt.'</textarea>
                    <input type="hidden" name="type" value="newsedit">
                    <input type="hidden" name="news_id" value="'.$n->news_id.'">
                    <br><br>
                    <input type="submit" value="'.$lng->getTrn('secs/teams/submitnews').'">
                </form></div>
                <div style="text-align: right;"><p style="display: inline;">'.textdate($n->date, true).
                (($ALLOW_EDIT) 
                    ? " | <form method='POST' name='newsForm$n->news_id' style='display:inline; margin:0px;'>
                        <input type='hidden' name='type' value='newsdel'>
                        <input type='hidden' name='news_id' value='$n->news_id'>
                        <a href='javascript:void(0);' onClick='document.newsForm$n->news_id.submit();'>[".$lng->getTrn('secs/teams/delete')."]</a>
                        </form>".
                        "| <a href='javascript:void(0);' onClick=\"document.getElementById('newsedit".$n->news_id."').style.display='block';\">[".$lng->getTrn('secs/teams/edit')."]</a>"
                    : '')
                .'</p></div><br></p>';
            }
            echo implode("<hr>\n", $news_2);
            if (empty($news)) {
                echo '<i>'.$lng->getTrn('secs/teams/nonews').'</i>';
            }

            if ($ALLOW_EDIT) {
                ?>
                <hr>
                <br>
                <b><?php echo $lng->getTrn('secs/teams/wnews');?></b>
                <form method="POST">
                    <textarea name='txt' cols='60' rows='4'></textarea>
                    <br><br>
                    <input type="hidden" name="type" value="news">
                    <input type='submit' value="<?php echo $lng->getTrn('secs/teams/submitnews');?>">
                </form>
                <?php
            }
            ?>
            </div>    
        </div>
    </div>
    <?php
}

private function _recentGames()
{
    global $lng;
    $team = $this; // Copy. Used instead of $this for readability.

    title("<a name='gp'>".$lng->getTrn('secs/teams/gamesplayed')."</a>");
    HTMLOUT::recentGames(STATS_TEAM, $team->team_id, false, false, false, false, array('url' => "index.php?section=coachcorner&amp;team_id=$team->team_id", 'n' => MAX_RECENT_GAMES, 'GET_SS' => 'gp'));
}

}
