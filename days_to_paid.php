<?php

/**
 *
 * Gather all paid invoices for a specified billing contact ID within a
 * given time frame and display how many days it took to get paid.
 *
 */

function curl_get($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, 'Content-Type: application/json');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "user@domain.com:password");

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

//API version
$meruscase_api = "https://api-canary.meruscase.com";

//Billing contact to lookup
$billto_contact_id = '00000000';

$dateFilter = '';

//billto_date_start
$start_date = '';

//billto_date_end
$end_date = '';

//Check for dates and filter if found
if ( $start_date != '' ){
    $gteDate = date('Y-m-d', strtotime($start_date));
    $dateFilter = '&bill_date[gte]='.$gteDate;
}
if ( $end_date != '' ){
    $lteDate = date('Y-m-d', strtotime($end_date));
    $dateFilter = '&bill_date[lte]='.$lteDate;
}

//Get invoices based on above criteria
$invoiceData = curl_get($meruscase_api.'/invoices/index/?billto_contact_id='.$billto_contact_id.'&is_paid=1'.$dateFilter);

//CSV headings
$csv = "invoice_number,invoice_total,amount_paid,amount_discounted,invoice_date,date_paid,number_of_days\n";

foreach ( $invoiceData['data']['data'] as $invoice ) {

    //Calculate how many days it took the invoice to be paid.
    $dateDifference = date_diff(date_create(gmdate('Y-m-d', $invoice['bill_date_end'])), date_create(gmdate('Y-m-d', $invoice['last_post_date'])))->format('%a');

    //Build CSV data for each invoice
    $csv .= $invoice['tracker'] . ","; //invoice number
    $csv .= $invoice['amount'] . ","; //total invoice amount
    $csv .= $invoice['amount_paid'] . ","; //total paid
    $csv .= $invoice['amount_discounted'] . ","; //total discounted
    $csv .= gmdate('m/d/Y', $invoice['bill_date_end']) . ","; //invoice date
    $csv .= gmdate('m/d/Y', $invoice['last_post_date']) . ","; //date of last payment
    $csv .= $dateDifference . "\n"; //amount of days it took to get paid

}

//Send CSV to browser
header("Content-Type: text/x-csv");
header("Content-Disposition: attachment; filename=invoice_days_".$billto_contact_id.".csv");
echo $csv;
