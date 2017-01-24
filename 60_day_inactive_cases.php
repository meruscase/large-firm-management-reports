<?php

function cmd_curl($url, $post)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, 'Content-Type: application/json');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "email@domain.com:password");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function status_name($str)
{
    switch($str){
        case '4223':
            return 'Archived';
            break;
        case '4225':
            return 'Closed, C/R';
        case '4229':
            return 'Closed, Stip';
            break;
        case '4215':
            return 'Closed';
            break;
        case '4228':
            return 'Compromise and Release';
            break;
        case '1243':
            return 'Contribution';
            break;
        case '4217':
            return 'Deceased';
            break;
        case '4216':
            return 'Declined';
            break;
        case '4222':
            return 'Destroyed';
            break;
        case '4220':
            return 'Dismissed';
            break;
        case '4230':
            return 'Findings &amp; Award';
            break;
        case '4219':
            return 'Hold';
            break;
        case '4218':
            return 'Intake';
            break;
        case '4226':
            return 'Lien Claim';
            break;
        case '4212':
            return 'Open';
            break;
        case '4221':
            return 'Other';
            break;
        case '4213':
            return 'Pending';
            break;
        case '4231':
            return 'Settlement';
            break;
        case '4214':
            return 'Stipulation';
            break;
        case '4227':
            return 'SubOut, Lien Claim';
            break;
        case '4224':
            return 'SubOut';
            break;
        case '1042':
            return 'Walk-through';
            break;

    }
}

function remove_comma($str){
    return str_replace(',','', $str);
}


//CSV HEADING
$csv = "case_number,case_name,status,last_ledger_date,days_inactive\n";

//URL FOR API
$url = 'https://api-canary.meruscase.com/caseFiles/index?';
//Intake
$url .= 'case_status_id[]=4218&';
//Lien Claim
$url .= 'case_status_id[]=4226&';
//Open
$url .= 'case_status_id[]=4212&';
//Other
$url .= 'case_status_id[]=4221&';
//Pending
$url .= 'case_status_id[]=4213&';
//Walk-Through
$url .= 'case_status_id[]=1042&';
//Include date of last ledger
$url .= 'extra_columns=last_ledger_date';

//GET DATA
$cases = cmd_curl($url, '');

//LOOP THROUGH CASES
foreach ( $cases['data'] as $case ){
    //Calculate inactive days
    $date_current = new DateTime('now');
    $date_oldest = new DateTime($case['last_ledger_date']);
    $inactive_days = date_diff($date_oldest, $date_current);
    $inactive = $inactive_days->format('%a');

    //Include on the report if inactive for 60 days or more.
    if ( $inactive > 59 ) {
        $csv .= $case[0] . ",";
        $csv .= remove_comma($case[1]) . ",";
        $csv .= remove_comma(status_name($case[4])) . ",";
        $csv .= date('m/d/Y', strtotime($case['last_ledger_date'])) . ",";
        $csv .= $inactive . "\n";
    }
}

header("Content-Type: text/x-csv");
header("Content-Disposition: attachment; filename=inactive_cases.csv");
echo $csv;
