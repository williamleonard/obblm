<?php

/*
 *  Copyright (c) Nicholas Mossor Rathmann <nicholas.rathmann@gmail.com> 2008-2009. All Rights Reserved.
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

require('header.php'); // Includes and constants.

if (!isset($_GET['type']))
    fatal("Sorry. Don't know what to do. Please specify 'type' via GET.");

$COACH_IS_ADMIN = (is_object($coach) && $coach->admin);

switch ($_GET['type'])
{
    /* PDF-roster */
    case 'roster':
        Module::run('PDFroster', array());
        break;

    /* RSS feed */
    case 'rss':
        Module::run('RSSfeed', array());
        break;

    /* Visual stats */
    case 'graph':
        Module::run('SGraph', array($_GET['gtype'], $_GET['id'], false));
        break;

    /* Inducements */
    case 'inducements':
        Module::run('IndcPage', array());
        break;

    /* BOTOCS match import */
    case 'leegmgr':
        Module::run('UPLOAD_BOTOCS', array());
        break;

    /* Team BOTOCS XML export */
    case 'botocsxml':
        Module::run('XML_BOTOCS', array());
        break;

    /* Mem. matches */
   	case 'memmatches':
   		Module::run('Memmatches', array());
   		break;

    /* Register */
   	case 'registration':
   		Module::run('Registration', array());
   		break;

    /* Hall of fame */
    case 'hof':    
        Module::run('HOF', array('makeList', $COACH_IS_ADMIN)); 
        break;
        
    /* Wanted */
    case 'wanted': 
        Module::run('Wanted', array('makeList', $COACH_IS_ADMIN)); 
        break;
        
    /* Prizes */
    case 'prize':  
        Module::run('Prize', array('makeList', $COACH_IS_ADMIN)); 
        break;
        
    /* Gallery */
    case 'gallery':  
        Module::run('Gallery', array()); 
        break;

    
    /* Veridy team name - AJAX use */
    case 'verifyteam':
        echo (isset($_POST['tname']) && is_numeric($tid = get_alt_col('teams', 'name', $_POST['tname'], 'team_id'))) ? $tid : '0';
        break;

    default:
        fatal("Sorry. I don't know what the type '$_GET[type]' means.\n");
}

mysql_close($conn);

?>
