<?php

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

function comma($str)
{
    return str_replace(",", "", $str);
}

$api_version = 'api-canary';
$csv = '';

$recData = curl_get('https://' . $api_version . '.meruscase.com/receivables/index?date_posted[gte]=1970-01-01&date_posted[lte]=1970-12-31');

$payments = $recData['data']['data'];

$csv .= "Payment ID,";
$csv .= "Date Posted,";
$csv .= "Payor,";
$csv .= "Check Number,";
$csv .= "Memo,";
$csv .= "Amount,";
$csv .= "Overpayment,";
$csv .= "Refund,";
$csv .= "\n";

foreach ($payments as $payment) {

    $refund_amt = '';

    //retrieve refund amount if present
    if (!empty($payment['payables'])) {
        foreach ($payment['payables'] as $refund) {
            $refundData = curl_get('https://' . $api_version . '.meruscase.com/payables/view/' . $refund);
            $refund_amt .= $refundData['Payable']['amount'];
        }
    }

    //overpayment amount if receivable is not reconciled
    if ($payment['is_reconciled'] == '0') {
        $amount_remaining = $payment['amount_remain'];
    } else {
        $amount_remaining = '';
    }

    $csv .= $payment['id'] . ",";
    $csv .= gmdate('m/d/Y', $payment['date_posted']) . ",";
    $csv .= comma($payment['payor']) . ",";
    $csv .= comma($payment['check_number']) . ",";
    $csv .= comma($payment['check_memo']) . ",";
    $csv .= comma($payment['amount']) . ",";
    $csv .= comma($amount_remaining) . ",";
    $csv .= comma($refund_amt) . ",";
    $csv .= "\n";

}
header("Content-Type: text/x-csv");
header("Content-Disposition: attachment; filename=payments_report.csv");

echo $csv;