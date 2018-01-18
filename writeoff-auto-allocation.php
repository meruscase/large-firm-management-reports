<?php
set_time_limit(0);                      // ignore php timeout
while (ob_get_level()) ob_end_clean();  // remove output buffers
ob_implicit_flush(true);                // output stuff directly

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

function unique_array($array, $key)
{
    $temp_array = array();
    $i = 0;
    $key_array = array();

    foreach ($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $temp_array[$i] = $val;
        }
        $i++;
    }
    return $temp_array;
}

/*
 * Retrieve a the list of branch offices to put on the final report.
 */
$offices = cmd_curl($meruscase_api . '/firmOffices/', '');

/*
 * Retrieve a list of all firm users to create the main user/total array.
 */
$userData = cmd_curl($meruscase_api . '/users/index/', '');

/*
 * MAIN ARRAY BUILD
 * Create a main array to house all of the users in the firm, their associated office and their write-off total
 */
$userArr = array();
foreach ($userData['data'] as $user) {
    $userArr[$user['id']] = array('id' => $user['id'], 'name' => $user[1] . ' ' . $user[2], 'office' => $offices['data'][$user['firm_office_id']]['description'], 'total' => '0');
}

/*
 * GET WRITE-OFF's
 * Get the list of write-off's for a given time frame then loop through them.
 */
$woI = 0;
$woData = cmd_curl($meruscase_api . '/caseLedgersOpen/index?date[gte]=2016-10-01&date[lte]=2016-10-31&ledger_type_id[]=112&ledger_type_id[]=111&invoice_id[gt]=0', '');
foreach ($woData['data']['data'] as $writeoff) {

    $woI++;

    /*
     * RETRIEVE INVOICE DATA
     * Grab the invoice data for each associated write-off ledger.
     */
    $invData = cmd_curl($meruscase_api . '/invoices/view/' . $writeoff['invoice_id'], '');
    $ledgers = $invData['CaseLedger']; //all invoice ledgers.
    $users = unique_array($ledgers, 'user_id'); //find unique users within all of the ledgers.
    $invoice_total = $invData['Invoice']['amount']; //original invoice total
    $write_off = $writeoff['amount']; //original write-off amount

    $numItems = count($users);
    $i = 0;
    $overall_total = 0;

    /*
     * COSTS
     * Loop through ledgers to add up all the costs. The cost total will be removed from the invoice total as we
     * do not write-off costs.
     */
    $cost_total = 0;
    foreach ($ledgers as $cost_search) {
        if ($cost_search['user_id'] == '140934' || $cost_search['user_id'] == '433244') {
            $cost_total = $cost_total + $cost_search['amount'];
        }
    }
    $cost_total = round(abs($cost_total), 2); //cost total for invoice.
    $invoice_total = $invoice_total - $cost_total; //remove the costs from the invoice total.

    foreach ($users as $user) { //Loop through unique users adding up the ledgers for each to get a total

        if (($user['user_id'] != '140934') && ($user['user_id'] != '433244')) { //exclude all costs; handled above.

            $user_id = $user['user_id'];
            $user_total = 0;

            //check each ledger to see if it matches the current user; if it does add it to the user_total.
            foreach ($ledgers as $ledger) {
                if ($ledger['user_id'] == $user_id) {
                    $user_total = $user_total + $ledger['amount'];
                }
            }

            $user_total = abs($user_total); //final user total
            $user_percentage = $user_total / $invoice_total; //percentage of the (adjusted) invoice total that belongs to this user.
            $user_percentage = round($user_percentage, 2); //round the percentage.
            $user_allocation = round(($write_off * $user_percentage), 2); //percentage of write-off beloning to this user, based on the above percentage amount.
            $overall_total = $overall_total + $user_allocation; //keep a running total of write-offs allocated for this invoice.
            $user_current_total = $userArr[$user_id]['total']; //get the users current total from the main array.
            $userArr[$user_id]['total'] = $user_current_total + $user_allocation; //add the write-off total for this invoice to the users main array total.
        }
    }

    /*
     * WRITE-OFF ADJUSTMENTS
     * Check if the current allocated write-off amount (overall_total) is equal to the actual write-off total on the original ledger.
     * If the allocated total is more than the actual amount then take the difference and subtract it from the last users total. If the
     * allocated total is less than the actual amount then add the difference to the last user. This ensures that the actual write-off
     * amount equals the allocated write-off amount.
     */
    if ($overall_total > $write_off) { //if over-allocated subtract the difference.
        $difference = $overall_total - $write_off;
        $user_current_total = $userArr[$user_id]['total'] - round($difference, 2);
        $userArr[$user_id]['total'] = $user_current_total;
        $overall_total = $overall_total - round($difference, 2);
    } elseif ($overall_total < $write_off) { //if under-allocated add the difference.
        $difference = $write_off - $overall_total;
        $user_current_total = $userArr[$user_id]['total'] + round($difference, 2);
        $userArr[$user_id]['total'] = $user_current_total;
        $overall_total = $overall_total + round($difference, 2);
    }

}

/*
 * OUTPUT
 * Generate CSV with the user, office and total write-off amount.
 */
$csv = "Timekeeper,Office,Allocated Write-Off\n";

foreach ($userArr as $user) { //only include users which have a write-off amount.
    if ($user['total'] > 0) {
        $csv .= $user['name'] . "," . $user['office'] . "," . $user['total'] . "\n";
    }
}

header("Content-Type: text/x-csv");
header("Content-Disposition: attachment; filename=AutoAllocatedWriteOff.csv");
echo $csv;