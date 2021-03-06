<?php
/* $Id: UnitsOfMeasure.php 4567 2011-05-15 04:34:49Z daintree $*/

include('includes/session.inc');

$title = _('Departments');

include('includes/header.inc');
echo '<p class="page_title_text"><img src="' . $rootpath . '/css/' . $theme . '/images/magnifier.png" title="' .
		_('Top Sales Order Search') . '" alt="" />' . ' ' . $title . '</p>';

if ( isset($_GET['SelectedDepartmentID']) )
	$SelectedDepartmentID = $_GET['SelectedDepartmentID'];
elseif (isset($_POST['SelectedDepartmentID']))
	$SelectedDepartmentID = $_POST['SelectedDepartmentID'];

if (isset($_POST['Submit'])) {

	//initialise no input errors assumed initially before we test

	$InputError = 0;

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible

	if (strpos($_POST['DepartmentName'],'&')>0 OR strpos($_POST['DepartmentName'],"'")>0) {
		$InputError = 1;
		prnMsg( _('The description of the department must not contain the character') . " '&' " . _('or the character') ." '",'error');
	}
	if (trim($_POST['DepartmentName']) == '') {
		$InputError = 1;
		prnMsg( _('The Name of the Department should not be empty'), 'error');
	}

	if (isset($_POST['SelectedDepartmentID']) AND $_POST['SelectedDepartmentID']!='' AND $InputError !=1) {


		/*SelectedDepartmentID could also exist if submit had not been clicked this code would not run in this case cos submit is false of course  see the delete code below*/
		// Check the name does not clash
		$sql = "SELECT count(*) FROM departments
				WHERE departmentid <> '" . $SelectedDepartmentID ."'
				AND description ".LIKE." '" . $_POST['DepartmentName'] . "'";
		$result = DB_query($sql,$db);
		$myrow = DB_fetch_row($result);
		if ( $myrow[0] > 0 ) {
			$InputError = 1;
			prnMsg( _('This department name already exists.'),'error');
		} else {
			// Get the old name and check that the record still exist neet to be very carefull here
			// idealy this is one of those sets that should be in a stored procedure simce even the checks are
			// relavant
			$sql = "SELECT description FROM departments
				WHERE departmentid = '" . $SelectedDepartmentID . "'";
			$result = DB_query($sql,$db);
			if ( DB_num_rows($result) != 0 ) {
				// This is probably the safest way there is
				$myrow = DB_fetch_row($result);
				$OldDepartmentName = $myrow[0];
				$sql = array();
				$sql[] = "UPDATE departments
					SET description='" . $_POST['DepartmentName'] . "',
						authoriser='" . $_POST['Authoriser'] . "'
					WHERE description ".LIKE." '".$OldDepartmentName."'";
			} else {
				$InputError = 1;
				prnMsg( _('The Department does not exist.'),'error');
			}
		}
		$msg = _('The department has been modified');
	} elseif ($InputError !=1) {
		/*SelectedDepartmentID is null cos no item selected on first time round so must be adding a record*/
		$sql = "SELECT count(*) FROM departments
				WHERE description " .LIKE. " '".$_POST['DepartmentName'] ."'";
		$result = DB_query($sql,$db);
		$myrow = DB_fetch_row($result);
		if ( $myrow[0] > 0 ) {
			$InputError = 1;
			prnMsg( _('There is already a Department with the specified name.'),'error');
		} else {
			$sql = "INSERT INTO departments (
						description,
						authoriser )
				VALUES (
					'" . $_POST['DepartmentName'] ."',
					'" . $_POST['Authoriser'] ."'
					)";
		}
		$msg = _('The new department has been created');
	}

	if ($InputError!=1){
		//run the SQL from either of the above possibilites
		if (is_array($sql)) {
			$result = DB_Txn_Begin($db);
			$tmpErr = _('The department could not be inserted');
			$tmpDbg = _('The sql that failed was') . ':';
			foreach ($sql as $stmt ) {
				$result = DB_query($stmt,$db, $tmpErr,$tmpDbg,true);
				if(!$result) {
					$InputError = 1;
					break;
				}
			}
			if ($InputError!=1){
				$result = DB_Txn_Commit($db);
			} else {
				$result = DB_Txn_Rollback($db);
			}
		} else {
			$result = DB_query($sql,$db);
		}
		prnMsg($msg,'success');
	}
	unset ($SelectedDepartmentID);
	unset ($_POST['SelectedDepartmentID']);
	unset ($_POST['DepartmentName']);

} elseif (isset($_GET['delete'])) {
//the link to delete a selected record was clicked instead of the submit button
// PREVENT DELETES IF DEPENDENT RECORDS IN 'stockmaster'
	// Get the original name of the unit of measure the ID is just a secure way to find the unit of measure
	$sql = "SELECT description FROM departments
		WHERE departmentid = '" . $SelectedDepartmentID . "'";
	$result = DB_query($sql,$db);
	if ( DB_num_rows($result) == 0 ) {
		// This is probably the safest way there is
		prnMsg( _('You cannot delete this Department'),'warn');
	} else {
		$myrow = DB_fetch_row($result);
		$OldDepartmentName = $myrow[0];
		$sql= "SELECT COUNT(*) FROM dispatch,departments WHERE dispatch.departmentid=departments.departmentid  and description ".LIKE." '" . $OldDepartmentName . "'";
		$result = DB_query($sql,$db);
		$myrow = DB_fetch_row($result);
		if ($myrow[0]>0) {
			prnMsg( _('You cannot delete this Department'),'warn');
			echo '<br />' . _('There are') . ' ' . $myrow[0] . ' ' . _('There are items related to this department');
		} else {
			$sql="DELETE FROM departments WHERE description ".LIKE."'" . $OldDepartmentName . "'";
			$result = DB_query($sql,$db);
			prnMsg( $OldDepartmentName . ' ' . _('The department has been removed') . '!','success');
		}
	} //end if account group used in GL accounts
	unset ($SelectedDepartmentID);
	unset ($_GET['SelectedDepartmentID']);
	unset($_GET['delete']);
	unset ($_POST['SelectedDepartmentID']);
	unset ($_POST['DepartmentID']);
	unset ($_POST['DepartmentName']);
}

 if (!isset($SelectedDepartmentID)) {

/* An unit of measure could be posted when one has been edited and is being updated
  or GOT when selected for modification
  SelectedDepartmentID will exist because it was sent with the page in a GET .
  If its the first time the page has been displayed with no parameters
  then none of the above are true and the list of account groups will be displayed with
  links to delete or edit each. These will call the same page again and allow update/input
  or deletion of the records*/

	$sql = "SELECT departmentid,
			description,
			authoriser
			FROM departments
			ORDER BY departmentid";

	$ErrMsg = _('There are no departments created');
	$result = DB_query($sql,$db,$ErrMsg);

	echo '<table class="selection">
			<tr>
				<th>' . _('Department Name') . '</th>
				<th>' . _('Authoriser') . '</th>
			</tr>';

	$k=0; //row colour counter
	while ($myrow = DB_fetch_array($result)) {

		if ($k==1){
			echo '<tr class="EvenTableRows">';
			$k=0;
		} else {
			echo '<tr class="OddTableRows">';
			$k++;
		}

		echo '<td>' . $myrow['description'] . '</td>';
		echo '<td>' . $myrow['authoriser'] . '</td>';
		echo '<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?SelectedDepartmentID=' . $myrow['departmentid'] . '">' . _('Edit') . '</a></td>';
		echo '<td><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?SelectedDepartmentID=' . $myrow['departmentid'] . '&delete=1">' . _('Delete') .'</a></td>';
		echo '</tr>';

	} //END WHILE LIST LOOP
	echo '</table>';
} //end of ifs and buts!


if (isset($SelectedDepartmentID)) {
	echo '<div class="centre"><a href="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '">' . _('View all Departments') . '</a></div>';
}

echo '<br />';

if (! isset($_GET['delete'])) {

	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') .  '">';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	if (isset($SelectedDepartmentID)) {
		//editing an existing section

		$sql = "SELECT departmentid,
				description
				FROM departments
				WHERE departmentid='" . $SelectedDepartmentID . "'";

		$result = DB_query($sql, $db);
		if ( DB_num_rows($result) == 0 ) {
			prnMsg( _('The selected departemnt could not be found.'),'warn');
			unset($SelectedDepartmentID);
		} else {
			$myrow = DB_fetch_array($result);

			$_POST['DepartmentID'] = $myrow['departmentid'];
			$_POST['DepartmentName']  = $myrow['description'];

			echo '<input type="hidden" name="SelectedDepartmentID" value="' . $_POST['DepartmentID'] . '" />';
			echo '<table class="selection">';
		}

	}  else {
		$_POST['DepartmentName']='';
		echo '<table class="selection">';
	}
	echo '<tr>
		<td>' . _('Department Name') . ':' . '</td>
		<td><input type="text" name="DepartmentName" size="50" maxlength="100" value="' . $_POST['DepartmentName'] . '" /></td>
		</tr>';
	echo '<tr><td>'._('Authoriser').'</td><td><select name="Authoriser">';
	$usersql="SELECT userid FROM www_users";
	$userresult=DB_query($usersql,$db);
	while ($myrow=DB_fetch_array($userresult)) {
		if ($myrow['userid']==$UserID) {
			echo '<option selected="True" value="'.$myrow['userid'].'">'.$myrow['userid'].'</option>';
		} else {
			echo '<option value="'.$myrow['userid'].'">'.$myrow['userid'].'</option>';
		}
	}
	echo '</select></td></tr>';
	echo '</table><br />';

	echo '<div class="centre"><button type="submit" name="Submit">' . _('Enter Information') . '</button></div><br />';

	echo '</form>';

} //end if record deleted no point displaying form to add record

include('includes/footer.inc');
?>