CLAMS (Client Location Access Mist Search)
CLAMS is a simple, single-file PHP web application designed to quickly search the entire Juniper Mist organization for client devices and resolve their connected Access Point (AP) MAC addresses to human-readable hostnames.

It provides a lightweight, focused alternative to the full Mist UI for quick client lookups across large organizations.

🚀 Features
Organization-Wide Search: Query clients across your entire Mist Organization by client MAC Address, Hostname, or Name.

AP Name Resolution: Automatically uses a secondary API call to resolve the last_ap MAC address to the AP's configured hostname (e.g., ECMS-214).

Real-time Data: Fetches the client's last seen time, last IP, and connected AP name.

Debug Panel: A collapsible "Diagnostics: API Calls" section at the bottom displays the full API URLs called for easy troubleshooting.

Modern UI: A clean, responsive interface built with Tailwind CSS.

🛠️ Prerequisites
To run CLAMS, you need a web server environment with the following:

PHP: Version 7.4 or newer.

cURL Extension: Must be enabled in your PHP installation.

🔑 Setup and Configuration
The entire application logic is contained within the single file, client_history_search.php. Before running, you must configure your Mist API credentials.

Obtain Credentials:

API Token (API_TOKEN): Generate an API token from your Mist User Settings. Ensure the token has Organization Read permissions.

Organization ID (ORG_ID): You can find your organization ID in the Mist URL when logged in (e.g., https://manage.mist.com/orgs/<ORG_ID>/...).

Edit the PHP File: Open clams.php and update the constants at the top of the file:

// =========================================================================
// Configuration - REPLACE THESE WITH YOUR ACTUAL MIST VALUES
// =========================================================================
const MIST_API_BASE_URL = '[https://api.mist.com/api/v1](https://api.mist.com/api/v1)'; // Check this for regional clouds (e.g., EU)
const API_TOKEN = 'YOUR_MIST_API_TOKEN_HERE';          // <-- REPLACE THIS
const ORG_ID = 'YOUR_MIST_ORG_ID_HERE';                // <-- REPLACE THIS
// =========================================================================

Deployment: Place the configured clams.php file on your web server and access it via your browser.

🏃 Usage
Access the Tool: Navigate to the file's URL in your web browser.

Enter Identifier: In the search box, enter the identifier for the client you wish to find (e.g., jane's-laptop, 1a2b3c4d5e6f, or test-mac-01).

Click Search: Click the Search Organization button.

The results table will display all matching clients, including the resolved Connected AP Name (if the AP is online and resolvable).

Troubleshooting
If you encounter errors or unexpected results, expand the Diagnostics: API Calls panel at the bottom of the page to review the full URLs and error messages returned by the Mist API.
