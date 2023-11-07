
<?php

define("refresh_token", '', true);
define("lwa_app_id", '', true);
define("lwa_client_secret", '', true);


function getAccessToken() {
    try {

        $baseUrl = 'https://api.amazon.com/auth/O2/token';
        $payload = [
            'grant_type' => 'refresh_token',
            'refresh_token' => refresh_token,
            'client_id' => lwa_app_id,
            'client_secret' => lwa_client_secret,
        ];

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($payload),
            ],
        ];



        $context = stream_context_create($options);
        $response = file_get_contents($baseUrl, false, $context);

        $data = json_decode($response, true);

        return $data['access_token'];
    } catch (Exception $err) {
        echo $err->getMessage();
        return null;
    }
}

function getOrders() {
    try {

        // Set the US Amazon Marketplace ID.
        $marketplaceId = "ATVPDKIKX0DER";

      // Set the request parameters.
        $requestParams = [
            "MarketplaceIds" => $marketplaceId, // required parameter
            "CreatedAfter" => '2023-11-04T20:11:24.000Z', // orders created since 30 days ago, the date needs to be in the ISO format
        ];

      // Encode the query parameters to the URL.
        $query_string = http_build_query($requestParams);

        $end_point = 'https://sellingpartnerapi-na.amazon.com';
        $uri_path = '/orders/v0/orders';

        $uri = "$end_point$uri_path?$query_string";
        // Set the HTTP headers.

        $headers = array(
            'x-amz-access-token: ' . getAccessToken(),
        );

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return $data;

    } catch (Exception $err) {
        echo $err->getMessage();
        return null;
    }
}

function writeStockDataToCSV($inventoryData) {
    if (empty($inventoryData)) {
        echo "No inventory data to write.";
        return;
    }

    $csvFileName = 'stockData.csv';

    $mode = 'w';

    if (file_exists($csvFileName)) {
        $mode = 'a+';
    }

    $csvFile = @fopen($csvFileName, $mode);

    if ($csvFile) {
        $date = date('Y-m-d H:i:s'); // Get current date and time

        // Check if the file exists but has no headers
        if ($mode === 'a+' && filesize($csvFileName) == 0) {
            fputcsv($csvFile, array('Date', 'ASIN', 'SKU', 'Fulfillable Quantity'));
        }

        if ($mode === 'w') {
            fputcsv($csvFile, array('Date', 'ASIN', 'SKU', 'Fulfillable Quantity'));
        }

        foreach ($inventoryData as $item) {
            $itemWithDate = array_merge(array($date), $item); // Add date as the first column
            fputcsv($csvFile, $itemWithDate);
        }

        fclose($csvFile);
        echo "Stock data has been written to $csvFileName";
    } else {
        echo "Failed to open or write to $csvFileName";
    }

    return  "Stock data has been written to $csvFileName";
}

function getInventoryData($json) {
    $inventoryData = array();

    if (!empty($json)) {
        $data = json_decode($json, true);

        if ($data && isset($data['payload']['inventorySummaries'])) {
            $inventorySummaries = $data['payload']['inventorySummaries'];

            foreach ($inventorySummaries as $item) {
                $asin = $item['asin'];
                $sku = $item['sellerSku'];
                $fulfillableQuantity = $item['inventoryDetails']['fulfillableQuantity'];

                $inventoryData[] = array(
                    'asin' => $asin,
                    'sku' => $sku,
                    'fulfillableQuantity' => $fulfillableQuantity
                );
            }
        } else {
            // Invalid or missing JSON data structure
            echo "Invalid or missing JSON data structure.";
        }
    } else {
        // No JSON data provided
        echo "No JSON data provided.";
    }

    return $inventoryData;
}

function getAmzInventory() {
    try {
        // Set the US Amazon Marketplace ID.
        $marketplaceId = "ATVPDKIKX0DER";
        $fileContents = file_get_contents('seller_skus.txt');
        $lines = explode("\n", $fileContents);
        $sellerSkusString = '';

        foreach ($lines as $line) {
            $pairs = explode(',', trim($line));
            foreach ($pairs as $pair) {
                $sellerSkusString .= $pair . ',';
            }
        }

        $sellerSkusString = rtrim($sellerSkusString, ',');
        $sellerSkusString = str_replace("\n", '', $sellerSkusString); // Removing any new lines

        $sellerSkus = '';

        // Set the request parameters.
        $requestParams = [
            "details" => 'true',
            "granularityType" => "Marketplace", // required parameter
            "granularityId" => $marketplaceId, // required parameter
            "sellerSkus" => $sellerSkusString, // seller-specific SKUs
            'marketplaceIds'=> $marketplaceId
        ];

        // Encode the query parameters to the URL.
        $query_string = http_build_query($requestParams);

        $end_point = 'https://sellingpartnerapi-na.amazon.com';
        $uri_path = '/fba/inventory/v1/summaries';

        $uri = "$end_point$uri_path?$query_string";

        // Set the HTTP headers.
        $headers = array(
            'x-amz-access-token: ' . getAccessToken(),
        );

        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);


        $invStockData = getInventoryData($response);
        $invStockDataWrite =  writeStockDataToCSV($invStockData);

        return $invStockDataWrite;
    } catch (Exception $err) {
        echo $err->getMessage();
        return null;
    }
}

//$orders  = getOrders();

$invData  = getAmzInventory();

echo "<pre>";
print_r($invData);
echo "</pre>";
exit();

