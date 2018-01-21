<?php
$GLOBALS['api-version'] = 'api';

function curl_get($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "name@domain.com:p@ssw0rd");

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function csvSanitize($str)
{
    return str_replace(",", "", $str);
}

function getCompany($contactID)
{
    if ($contactID == 0)
        return array();

    return curl_get('https://' . $GLOBALS['api-version'] . '.meruscase.com/contacts/view/' . $contactID);
}

$caseFileData = curl_get('https://' . $GLOBALS['api-version'] . '.meruscase.com/caseFiles/index?extra_columns=last_ledger_date');
$caseTypesData = curl_get('https://' . $GLOBALS['api-version'] . '.meruscase.com/caseTypes/index');
$caseStatusesData = curl_get('https://' . $GLOBALS['api-version'] . '.meruscase.com/caseStatuses/index');
$usersData = curl_get('https://' . $GLOBALS['api-version'] . '.meruscase.com/users/index');
$contactsData = curl_get('https://' . $GLOBALS['api-version'] . '.meruscase.com/contacts/index');

if (isset($caseFileData['data']))
    $caseFiles = $caseFileData['data'];
else
    throw new Exception('Could not retrieve caseFile data.');
if (isset($caseTypesData['data']))
    $caseTypes = $caseTypesData['data'];
if (isset($caseStatusesData['data']))
    $caseStatuses = $caseStatusesData['data'];
if (isset($usersData['data']))
    $users = $usersData['data'];
if (isset($contactsData['data']))
    $contacts = $contactsData['data'];

$csv = '';

$csv .= "Case No.,";
$csv .= "Name,";
if (isset($caseTypes))
    $csv .= "Type,";
else
    $csv .= "Type ID,";
if (isset($caseStatuses))
    $csv .= "Status,";
else
    $csv .= "Status ID,";
if (isset($users))
    $csv .= "Staff,";
else
    $csv .= "Staff IDs,";
$csv .= "Billing Contact,";
$csv .= "Last Billed,";
$csv .= "\n";

foreach ($caseFiles as $caseFile) {
    //Only show cases that have a last_ledger_date
    if ($caseFile['last_ledger_date'] !== null) {
        $csv .= csvSanitize($caseFile['0']) . ","; //Case No
        $csv .= csvSanitize($caseFile['1']) . ","; //Name

        //If the caseTypes endpoint returns data print types, otherwise print IDs
        if (isset($caseTypes)) {
            foreach ($caseTypes as $aKey => $aCaseType) {
                if ($caseFile['3'] == $aKey) {
                    $csv .= $aCaseType . ","; //Pretty type
                    break;
                }
            }
        } else
            $csv .= $caseFile['3'] . ","; //Type ID

        //If the caseStatuses endpoint returns data print statuses, otherwise print IDs
        if (isset($caseStatuses)) {
            foreach ($caseStatuses as $aCaseStatus) {
                if ($caseFile['4'] == $aCaseStatus['id']) {
                    $csv .= $aCaseStatus['status'] . ","; //Pretty status
                    break;
                }
            }
        } else
            $csv .= $caseFile['4'] . ","; //Status ID

        //If the users endpoint returns data print user names for each staff_id, otherwise print IDs. If a particular user cannot be matched just print an ID.
        $staffInitials = '';
        foreach ($caseFile['5'] as $aStaffMember_id) {
            if (isset($users)) {
                if (isset($users[$aStaffMember_id])) {
                    $staffInitials .= $users[$aStaffMember_id]['6'] . "|";
                } else
                    $staffInitials .= $aStaffMember_id . "|";
            } else
                $staffInitials .= $aStaffMember_id . "|";
        }

        $csv .= substr($staffInitials, 0, -1) . ","; //Print Staff to the output stream

        //TODO Implement UTBMS setting display
        //$csv .= comma($caseFile['13']) . ","; //UTBMS set for case y/n
        //TODO Implement branch office query, may need endpoint created as well
        //$csv .= comma($caseFile['15']) . ","; //Firm Branch office id

        //If the contacts endpoint returns data print contact names, otherwise print IDs
        if ($caseFile['16'] == 0)
            $csv .= ","; //If the contact_id is 0 don't print anything
        else {
            if (isset($contacts)) {
                if (isset($contacts[$caseFile['16']]))
                    $csv .= $contacts[$caseFile['16']][1] . ' ' . $contacts[$caseFile['16']][0] . ","; //Pretty type
                else {
                    //Contact might be a company, let's try to retrieve its name
                    $company = getCompany($caseFile['16']);

                    if (isset($company['Contact']['name']))
                        $csv .= $company['Contact']['name'] . ","; //Company name
                    else if (isset($company['errors'])) {
                        if ($company['errors'][0]['errorMessage'] == "This Contact has been deleted.")
                            $csv .= "DELETED" . ","; //Manual error message to look a little prettier
                        else
                            $csv .= $company['errors'][0]['errorMessage'] . ","; //Print explicit error if not contact deleted
                    } else
                        $csv .= 'contact_id=' . $caseFile['16'] . ","; //Contact/company ID fallback
                }
            } else
                $csv .= 'contact_id=' . $caseFile['16'] . ","; //Contact ID
        }

        $csv .= $caseFile['last_ledger_date'] . ","; //Last ledger date
        $csv .= "\n";
    }
}

header("Content-Type: text/x-csv");
header("Content-Disposition: attachment; filename=payments_report.csv");

echo $csv;
