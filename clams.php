<?php
/**
 * CLAMS (Client Location Access Mist Search)
 *
 * This script provides a simple web interface to query the Juniper Mist API
 * for clients across the entire organization using the search endpoint.
 * It supports searching by MAC address, hostname, or client name.
 *
 * NOTE: You MUST replace the placeholder values for API_TOKEN and ORG_ID.
 */

// =========================================================================
// Configuration - REPLACE THESE WITH YOUR ACTUAL MIST VALUES
// =========================================================================
// !!! Check this if you are on a regional cloud (e.g., EU) !!!
const MIST_API_BASE_URL = 'https://api.mist.com/api/v1'; 
// The API Token is mandatory for authentication.
const API_TOKEN = 'YOUR_MIST_API_TOKEN_HERE';
// The Organization ID is required to scope the search.
const ORG_ID = 'YOUR_MIST_ORG_ID_HERE';
// SITE_ID is no longer used for org-wide search, but kept for clarity
const SITE_ID = 'YOUR_MIST_SITE_ID_HERE'; 
// =========================================================================

$results = null;
$error = null;
$apiCallsDebug = []; // Array to store all API URLs called
$apNameCache = []; // Cache for AP names to reduce duplicate API calls

/**
 * Executes a cURL request to the Mist API.
 *
 * @param string $url The full URL to the MIST API endpoint.
 * @return array|null Returns the decoded JSON response array or null on failure.
 */
function callMistApi($url) {
    global $apiCallsDebug;
    $apiCallsDebug[] = $url; // Log the URL for debugging

    // Only check mandatory org-level credentials
    if (API_TOKEN === 'YOUR_MIST_API_TOKEN_HERE' || ORG_ID === 'YOUR_MIST_ORG_ID_HERE') {
        return ['error' => 'API Token or Org ID is not configured.'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

    $headers = [
        'Content-Type: application/json',
        'Authorization: Token ' . API_TOKEN
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        $error = 'cURL Error: ' . curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    }
    curl_close($ch);

    if ($http_code !== 200) {
        $decoded = json_decode($response, true);
        $error_message = $decoded['detail'] ?? "HTTP Code $http_code. Check API Token, Org ID, or Base URL. Response Body: " . (is_string($response) ? $response : 'N/A');
        // NOTE: We don't return the error for AP lookups to prevent them from overwriting the main search error, 
        // but we need to know the lookup failed (which results in the AP MAC being displayed).
        return ['error' => $error_message];
    }

    return json_decode($response, true);
}


/**
 * Fetches the AP name using the AP's MAC address from the organization devices search endpoint.
 * Uses a global cache to avoid redundant API calls.
 *
 * @param string $apMac The MAC address of the AP.
 * @return string The AP name, or the MAC address if the name is not found.
 */
function getApNameFromMac($apMac) {
    global $apNameCache;

    if (empty($apMac) || $apMac === 'N/A') {
        return 'N/A (No AP)';
    }

    // 1. Check Cache
    if (isset($apNameCache[$apMac])) {
        return $apNameCache[$apMac];
    }

    // 2. Construct AP Search API URL
    $api_url = MIST_API_BASE_URL . "/orgs/" . ORG_ID . "/devices/search" .
               "?mac=" . urlencode($apMac);

    $api_response = callMistApi($api_url);

    $ap_name = $apMac; // Default to MAC if not found

    // 3. Extract Name from Search Results
    // FIX: The AP name is stored under 'last_hostname', not 'name'.
    if (
        !isset($api_response['error']) && 
        is_array($api_response) && 
        !empty($api_response['results'][0]['last_hostname'])
    ) {
        $ap_name = $api_response['results'][0]['last_hostname'];
    }
    
    // 4. Store in cache
    $apNameCache[$apMac] = $ap_name;

    return $ap_name;
}


// =========================================================================
// Request Handling
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_identifier'])) {
    $client_search_term = trim($_POST['client_identifier']);
    
    // 1. Construct the API URL for organization-wide client search
    $api_url = MIST_API_BASE_URL . "/orgs/" . ORG_ID . "/clients/search" .
               "?text=" . urlencode($client_search_term);

    $api_response = callMistApi($api_url);

    if (isset($api_response['error'])) {
        $error = "Organization Search Failed: " . $api_response['error'];
    } else if (is_array($api_response)) {
        // FIX: The response data is nested under the 'results' key.
        $results = $api_response['results'] ?? [];

        // NEW: Enrich results with AP Name lookup
        foreach ($results as $index => $client) {
            $apMac = $client['last_ap'] ?? null;
            $apName = getApNameFromMac($apMac);
            $results[$index]['ap_name'] = $apName; // Inject the AP name into the client result
        }

    } else {
        $error = "API returned an unexpected response format.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Updated Title -->
    <title>CLAMS (Client Location Access Mist Search)</title> 
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7f7f7; }
        .card { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1); }
        .header { background-color: #0076a8; } /* Juniper Mist Blue */
    </style>
</head>
<body>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'mist-blue': '#0076a8',
                        'mist-dark': '#00263f',
                    }
                }
            }
        }
    </script>
    <div class="min-h-screen p-4 md:p-8">
        <header class="header text-white p-6 rounded-t-lg mb-8">
            <!-- Updated Header Title -->
            <h1 class="text-3xl font-bold">CLAMS (Client Location Access Mist Search)</h1>
            <p class="text-mist-blue-100 mt-1">Quickly locate clients and resolve their connected Access Point (AP) name across your MIST organization.</p>
        </header>

        <div class="card bg-white p-6 rounded-lg mb-8">
            <h2 class="text-xl font-semibold text-mist-dark mb-4">Search Parameters</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label for="client_identifier" class="block text-sm font-medium text-gray-700">Client Identifier (Name, Hostname, or MAC)</label>
                    <input type="text" name="client_identifier" id="client_identifier" 
                           value="<?php echo htmlspecialchars($_POST['client_identifier'] ?? ''); ?>"
                           required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2 border focus:ring-mist-blue focus:border-mist-blue"
                           placeholder="e.g., Jane's Laptop or aabbccddeeff">
                </div>
                <button type="submit" class="w-full md:w-auto px-6 py-2 bg-mist-blue text-white font-semibold rounded-lg shadow-md hover:bg-mist-dark transition duration-150 ease-in-out">
                    Search Organization
                </button>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg card mb-8" role="alert">
                <p class="font-bold">API Error</p>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($results !== null): ?>
            <div class="card bg-white p-6 rounded-lg">
                <h2 class="text-xl font-semibold text-mist-dark mb-4">Results matching "<?php echo htmlspecialchars($_POST['client_identifier'] ?? 'Client'); ?>"</h2>
                
                <?php if (empty($results)): ?>
                    <div class="text-gray-500 p-4 bg-gray-50 rounded-md">
                        No clients found matching "<?php echo htmlspecialchars($_POST['client_identifier'] ?? 'Client'); ?>" in the organization.
                    </div>
                <?php else: ?>
                    <p class="text-sm text-gray-600 mb-4">Found <?php echo count($results); ?> matching clients.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client Name/Hostname</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">MAC Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Connected AP Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Seen</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($results as $client): 
                                    // last_seen is a timestamp in seconds
                                    $last_seen = isset($client['timestamp']) ? date('Y-m-d H:i:s', floor($client['timestamp'])) : 'N/A';
                                    
                                    // Use last_hostname, fallback to mac
                                    $client_name = $client['last_hostname'] ?? $client['mac'];
                                ?>
                                    <tr class="hover:bg-indigo-50 transition duration-150">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($client_name); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($client['mac'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium"><?php echo htmlspecialchars($client['ap_name'] ?? $client['last_ap'] ?? 'N/A'); ?></td> <!-- Use injected ap_name -->
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($last_seen); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($client['last_ip'] ?? 'N/A'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div id="diagnostics-container" class="mt-8">
                <button type="button" 
                        onclick="document.getElementById('diagnostics-content').classList.toggle('hidden');"
                        class="w-full text-left p-3 font-semibold text-white bg-mist-dark hover:bg-mist-blue rounded-t-lg transition duration-150 ease-in-out flex justify-between items-center">
                    <span>Diagnostics: API Calls (Click to Expand)</span>
                </button>
                <div id="diagnostics-content" class="hidden bg-indigo-50 border-x border-b border-indigo-400 text-indigo-800 p-4 rounded-b-lg">
                    <p class="font-bold">API Call History</p>
                    <ul class="text-sm list-disc pl-5 mt-2 space-y-1 break-all">
                        <li>**Configured Base URL:** <code class="font-mono"><?php echo MIST_API_BASE_URL; ?></code></li>
                        <li>**Configured Org ID:** <code class="font-mono"><?php echo ORG_ID; ?></code></li>
                        <?php 
                        // Display all logged API calls
                        foreach ($apiCallsDebug as $index => $url) {
                            $label = ($index === 0) ? 'Client Search API' : 'AP Lookup API';
                            echo "<li>**$label $index:** <code class=\"font-mono\">" . htmlspecialchars($url) . "</code></li>";
                        }
                        ?>
                    </ul>
                    <p class="text-sm mt-2 font-semibold text-red-600">
                        If you see a 404, double-check the Base URL and Org ID. <span class="font-extrabold">If you see a 403/Permission Error, verify your API_TOKEN has Organization Read scope.</span>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <footer class="mt-8 text-center text-xs text-gray-500">
            Powered by Juniper Mist API & PHP cURL.
        </footer>
    </div>
</body>
</html>

