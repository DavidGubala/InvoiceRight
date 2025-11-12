<?php

if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author      InvoicePlane Developers & Contributors
 * @copyright   Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license     https://invoiceplane.com/license.txt
 * @link        https://invoiceplane.com
 */

/**
 * Bill.com API Integration Library
 * 
 * This library handles communication with the Bill.com v3 API
 * for submitting invoices from InvoicePlane to Bill.com.
 * 
 * Implements proper authentication flow:
 * - Calls POST /v3/login to get sessionId
 * - Caches sessionId in PHP session
 * - Auto-refreshes when session expires (35 min)
 */
#[AllowDynamicProperties]
class Billcom
{
    private $CI;
    private $username;
    private $password;
    private $dev_key;
    private $org_id;
    private $api_url;
    private $enabled;
    private $session_id;
    private $session_expires_at;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->helper('settings');
        $this->CI->load->library('session');
        
        // Load Bill.com settings
        $this->username = get_setting('billcom_username');
        $this->password = get_setting('billcom_password');
        $this->dev_key = get_setting('billcom_dev_key');
        $this->org_id = get_setting('billcom_org_id');
        $this->api_url = get_setting('billcom_api_url', 'https://gateway.stage.bill.com/connect');
        $this->enabled = get_setting('billcom_enabled', '0');
        
        // Decrypt password if encrypted
        if (!empty($this->password)) {
            $this->CI->load->library('crypt');
            $this->password = $this->CI->crypt->decode($this->password);
        }
        
        // Decrypt dev_key if encrypted
        if (!empty($this->dev_key)) {
            if (!isset($this->CI->crypt)) {
                $this->CI->load->library('crypt');
            }
            $this->dev_key = $this->CI->crypt->decode($this->dev_key);
        }

        // Try to load cached session
        $this->session_id = $this->CI->session->userdata('billcom_session_id');
        $this->session_expires_at = $this->CI->session->userdata('billcom_session_expires_at');
    }

    /**
     * Check if Bill.com integration is enabled
     *
     * @return bool
     */
    public function is_enabled()
    {
        return $this->enabled === '1' || $this->enabled === 1;
    }

    /**
     * Login to Bill.com and get sessionId
     * 
     * Calls POST /v3/login with credentials
     * Returns sessionId which is valid for 35 minutes
     *
     * @return array Response with 'success' boolean and 'session_id' or 'error'
     */
    private function login()
    {
        if (empty($this->username) || empty($this->password) || empty($this->dev_key)) {
            log_message('error', 'Bill.com: Missing login credentials');
            return [
                'success' => false,
                'error' => 'Missing Bill.com credentials (username, password, or dev key)'
            ];
        }

        $login_data = [
            'username' => $this->username,
            'password' => $this->password,
            'devKey' => $this->dev_key
        ];

        // Add organizationId if specified
        if (!empty($this->org_id)) {
            $login_data['organizationId'] = $this->org_id;
        }

        $endpoint = $this->api_url . '/v3/login';
        
        log_message('info', 'Bill.com: Attempting login for user: ' . $this->username);
        
        try {
            $response = $this->makeLoginRequest($endpoint, $login_data);
            $decoded = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Bill.com: Invalid JSON response from login');
                return [
                    'success' => false,
                    'error' => 'Invalid response from Bill.com login'
                ];
            }
            
            // Check for error in response (Bill.com v3 returns array of errors)
            if (is_array($decoded) && isset($decoded[0]) && isset($decoded[0]['message'])) {
                // Array of error objects format
                $error_messages = [];
                foreach ($decoded as $error) {
                    if (isset($error['message'])) {
                        $error_messages[] = $error['message'];
                    }
                }
                $error_message = implode(', ', $error_messages);
                log_message('error', 'Bill.com: Login errors: ' . $error_message);
                
                return [
                    'success' => false,
                    'error' => $error_message
                ];
            }
            
            // Check for single error object format
            if (isset($decoded['error'])) {
                $error_message = isset($decoded['error']['message']) 
                    ? $decoded['error']['message'] 
                    : 'Login failed';
                
                log_message('error', 'Bill.com: Login error: ' . $error_message);
                
                return [
                    'success' => false,
                    'error' => $error_message
                ];
            }
            
            // Check if sessionId is present
            if (isset($decoded['sessionId'])) {
                $session_id = $decoded['sessionId'];
                
                // Cache session (expires in 35 minutes, we'll refresh at 30 to be safe)
                $expires_at = time() + (30 * 60); // 30 minutes
                
                $this->session_id = $session_id;
                $this->session_expires_at = $expires_at;
                
                // Store in PHP session
                $this->CI->session->set_userdata('billcom_session_id', $session_id);
                $this->CI->session->set_userdata('billcom_session_expires_at', $expires_at);
                
                log_message('info', 'Bill.com: Login successful, session cached');
                
                return [
                    'success' => true,
                    'session_id' => $session_id
                ];
            }
            
            // Unexpected response
            log_message('error', 'Bill.com: Login response missing sessionId');
            return [
                'success' => false,
                'error' => 'Login response missing sessionId'
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Bill.com: Exception during login: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to connect to Bill.com: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get valid sessionId (login if needed or session expired)
     *
     * @return array Response with 'success' boolean and 'session_id' or 'error'
     */
    private function getSessionId()
    {
        // Check if we have a cached session that's still valid
        if (!empty($this->session_id) && !empty($this->session_expires_at)) {
            if (time() < $this->session_expires_at) {
                // Session is still valid
                return [
                    'success' => true,
                    'session_id' => $this->session_id
                ];
            } else {
                log_message('info', 'Bill.com: Session expired, logging in again');
            }
        }

        // Need to login
        return $this->login();
    }

    /**
     * Validate that required credentials are configured
     *
     * @return bool
     */
    public function authenticate()
    {
        if (empty($this->username) || empty($this->password) || empty($this->dev_key) || empty($this->api_url)) {
            log_message('error', 'Bill.com: Missing credentials - username, password, dev_key, or api_url not configured');
            return false;
        }
        
        return true;
    }

    /**
     * Create an invoice in Bill.com
     *
     * @param object $invoice InvoicePlane invoice object with all related data
     * @param object $client Client object
     * @param array $items Invoice items array
     * @param array $custom_fields Custom fields array (optional)
     * @return array Response array with 'success' boolean and 'data' or 'error'
     */
    public function createInvoice($invoice, $client, $items, $custom_fields = [])
    {
        if (!$this->is_enabled()) {
            return [
                'success' => false,
                'error' => 'Bill.com integration is not enabled'
            ];
        }

        if (!$this->authenticate()) {
            return [
                'success' => false,
                'error' => 'Bill.com credentials not configured properly'
            ];
        }

        // Get valid session ID (will login if needed)
        $session_result = $this->getSessionId();
        if (!$session_result['success']) {
            return $session_result; // Return login error
        }

        // Map InvoicePlane data to Bill.com format
        $billcom_data = $this->mapInvoiceData($invoice, $client, $items, $custom_fields);
        
        if (!$billcom_data['success']) {
            log_message('error', 'Bill.com: Failed to map invoice data for invoice ' . $invoice->invoice_id . ': ' . $billcom_data['error']);
            return $billcom_data; // Return mapping error
        }
        
        // Make API request
        $endpoint = $this->api_url . '/v3/invoices';
        
        log_message('info', 'Bill.com: Sending invoice ' . $invoice->invoice_id . ' (IP#' . $invoice->invoice_number . ') to Bill.com for client: ' . $client->client_name);
        log_message('debug', 'Bill.com: Invoice data: ' . json_encode($billcom_data['data']));
        
        try {
            $response = $this->makeApiRequest($endpoint, 'POST', $billcom_data['data'], $session_result['session_id']);
            $result = $this->handleApiResponse($response, $invoice->invoice_id);
            
            if ($result['success']) {
                log_message('info', 'Bill.com: Successfully sent invoice ' . $invoice->invoice_id . ' - Bill.com ID: ' . (isset($result['data']['id']) ? $result['data']['id'] : 'N/A'));
            } else {
                log_message('error', 'Bill.com: Failed to send invoice ' . $invoice->invoice_id . ': ' . $result['error']);
            }
            
            return $result;
        } catch (Exception $e) {
            log_message('error', 'Bill.com: Exception when creating invoice ' . $invoice->invoice_id . ': ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to connect to Bill.com: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Map InvoicePlane invoice data to Bill.com API format
     *
     * @param object $invoice InvoicePlane invoice object
     * @param object $client Client object
     * @param array $items Invoice items
     * @param array $custom_fields Custom fields (optional)
     * @return array Result with 'success' boolean and 'data' or 'error'
     */
    private function mapInvoiceData($invoice, $client, $items, $custom_fields = [])
    {
        $this->CI->load->helper('date');
        
        $data = [];
        
        // Customer data - use existing Bill.com customer ID if available
        if (!empty($client->client_billcom_customer_id)) {
            // Use existing customer ID (email not required per Bill.com API docs)
            $data['customer'] = [
                'id' => $client->client_billcom_customer_id
            ];
            log_message('info', 'Bill.com: Using existing customer ID ' . $client->client_billcom_customer_id);
        } else {
            // Create new customer - requires name and email
            if (empty($client->client_email)) {
                log_message('error', 'Bill.com: Client ' . $client->client_id . ' (' . $client->client_name . ') has no email and no Bill.com Customer ID');
                return [
                    'success' => false,
                    'error' => 'Client "' . $client->client_name . '" must have an email address or Bill.com Customer ID to send invoices'
                ];
            }
            $data['customer'] = [
                'name' => format_client($client),
                'email' => $client->client_email
            ];
            log_message('info', 'Bill.com: Creating new customer "' . format_client($client) . '" with email ' . $client->client_email);
        }
        
        // Map invoice line items (start with custom fields as informational items)
        $data['invoiceLineItems'] = [];
        
        // Add custom fields as 0 qty, 0 price line items at the beginning
        if (!empty($custom_fields)) {
            foreach ($custom_fields as $custom_field) {
                // Only add if the field has a value
                if (!empty($custom_field->invoice_custom_fieldvalue)) {
                    $data['invoiceLineItems'][] = [
                        'description' => $custom_field->custom_field_label . ': ' . $custom_field->invoice_custom_fieldvalue,
                        'quantity' => 0,
                        'price' => 0
                    ];
                    log_message('debug', 'Bill.com: Adding custom field "' . $custom_field->custom_field_label . ': ' . $custom_field->invoice_custom_fieldvalue . '" as line item');
                }
            }
        }
        
        // Add regular invoice items
        foreach ($items as $item) {
            $line_item = [
                'quantity' => (float)$item->item_quantity,
                'price' => (float)$item->item_price
            ];
            
            // Add description if available
            if (!empty($item->item_name)) {
                $line_item['description'] = $item->item_name;
                if (!empty($item->item_description)) {
                    $line_item['description'] .= ' - ' . $item->item_description;
                }
            } elseif (!empty($item->item_description)) {
                $line_item['description'] = $item->item_description;
            } else {
                $line_item['description'] = 'Item';
            }
            
            $data['invoiceLineItems'][] = $line_item;
        }
        
        // Add invoice number if available
        if (!empty($invoice->invoice_number)) {
            $data['invoiceNumber'] = $invoice->invoice_number;
        }
        
        // Add invoice date (use today if missing or invalid)
        if (!empty($invoice->invoice_date_created) && strtotime($invoice->invoice_date_created)) {
            $data['invoiceDate'] = date('Y-m-d', strtotime($invoice->invoice_date_created));
        } else {
            $data['invoiceDate'] = date('Y-m-d'); // Default to today
            log_message('warning', 'Bill.com: Invalid invoice date for invoice ' . $invoice->invoice_id . ', using today');
        }
        
        // Add due date (use invoice date + 30 days if missing or invalid)
        if (!empty($invoice->invoice_date_due) && strtotime($invoice->invoice_date_due)) {
            $data['dueDate'] = date('Y-m-d', strtotime($invoice->invoice_date_due));
        } else {
            $data['dueDate'] = date('Y-m-d', strtotime('+30 days', strtotime($data['invoiceDate'])));
            log_message('warning', 'Bill.com: Invalid due date for invoice ' . $invoice->invoice_id . ', using +30 days');
        }
        
        // Processing options - don't auto-send email from Bill.com
        $data['processingOptions'] = [
            'sendEmail' => false
        ];
        
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Make HTTP request to Bill.com login endpoint (no auth header)
     *
     * @param string $url API endpoint URL
     * @param array $data Login data
     * @return string Response body
     */
    private function makeLoginRequest($url, $data)
    {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TESTING ONLY - NOT SECURE!
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            $error_message = $this->formatCurlError($curl_errno, $curl_error, $url);
            log_message('error', 'Bill.com: Login cURL error ' . $curl_errno . ': ' . $curl_error);
            throw new Exception($error_message);
        }
        
        // Check HTTP status code
        if ($http_code < 200 || $http_code >= 300) {
            log_message('error', 'Bill.com: Login failed with HTTP ' . $http_code . ': ' . substr($response, 0, 500));
            
            // Try to parse error message from response
            $decoded = json_decode($response, true);
            if (isset($decoded['error']['message'])) {
                $error_details = $decoded['error']['message'];
                // Add additional error info if available
                if (isset($decoded['error']['errorCode'])) {
                    $error_details .= ' (Error code: ' . $decoded['error']['errorCode'] . ')';
                }
                if (isset($decoded['error']['moreInfo'])) {
                    $error_details .= ' - ' . $decoded['error']['moreInfo'];
                }
                throw new Exception('Bill.com login failed: ' . $error_details . ' (HTTP ' . $http_code . ')');
            }
            
            // If we can't parse the error, show the raw response
            throw new Exception('Bill.com login returned HTTP ' . $http_code . '. Response: ' . substr($response, 0, 200) . '... Check your credentials and API URL.');
        }
        
        log_message('debug', 'Bill.com: Login API Response (HTTP ' . $http_code . ')');
        
        return $response;
    }

    /**
     * Make HTTP request to Bill.com API (with sessionId)
     *
     * @param string $url API endpoint URL
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $data Request data
     * @param string $session_id Session ID for authentication
     * @return string Response body
     */
    private function makeApiRequest($url, $method = 'GET', $data = null, $session_id = null)
    {
        $ch = curl_init();
        
        $headers = [
            'Content-Type: application/json',
            'devKey: ' . $this->dev_key,
            'sessionId: ' . $session_id
        ];
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TESTING ONLY - NOT SECURE!
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        
        curl_close($ch);
        
        if ($response === false) {
            $error_message = $this->formatCurlError($curl_errno, $curl_error, $url);
            log_message('error', 'Bill.com: API cURL error ' . $curl_errno . ': ' . $curl_error . ' (URL: ' . $url . ')');
            throw new Exception($error_message);
        }
        
        // Check HTTP status code
        if ($http_code < 200 || $http_code >= 300) {
            log_message('error', 'Bill.com: API request failed with HTTP ' . $http_code . ': ' . substr($response, 0, 500));
            
            // Try to parse error message from response
            $decoded = json_decode($response, true);
            
            // Check for array of error objects (Bill.com v3 format)
            if (is_array($decoded) && isset($decoded[0]) && isset($decoded[0]['message'])) {
                $error_messages = [];
                foreach ($decoded as $error) {
                    if (isset($error['message'])) {
                        $error_messages[] = $error['message'];
                    }
                }
                $error_text = implode(', ', $error_messages);
                throw new Exception('Bill.com API error: ' . $error_text . ' (HTTP ' . $http_code . ')');
            }
            
            // Check for single error object format
            if (isset($decoded['error']['message'])) {
                throw new Exception('Bill.com API error: ' . $decoded['error']['message'] . ' (HTTP ' . $http_code . ')');
            }
            
            throw new Exception('Bill.com API returned HTTP ' . $http_code . '. Response: ' . substr($response, 0, 200));
        }
        
        log_message('debug', 'Bill.com: API Response (HTTP ' . $http_code . '): ' . substr($response, 0, 500));
        
        return $response;
    }

    /**
     * Handle API response from Bill.com
     *
     * @param string $response Raw API response
     * @param int $invoice_id InvoicePlane invoice ID for logging
     * @return array Response array with 'success' boolean and 'data' or 'error'
     */
    private function handleApiResponse($response, $invoice_id)
    {
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'Bill.com: Invalid JSON response for invoice ' . $invoice_id);
            return [
                'success' => false,
                'error' => 'Invalid response from Bill.com API'
            ];
        }
        
        // Check for array of error objects (Bill.com v3 format)
        if (is_array($decoded) && isset($decoded[0]) && isset($decoded[0]['message'])) {
            $error_messages = [];
            foreach ($decoded as $error) {
                if (isset($error['message'])) {
                    $error_messages[] = $error['message'];
                }
            }
            $error_message = implode(', ', $error_messages);
            log_message('error', 'Bill.com: API errors for invoice ' . $invoice_id . ': ' . $error_message);
            
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
        
        // Check if response contains an error (single object format)
        if (isset($decoded['error'])) {
            $error_message = isset($decoded['error']['message']) 
                ? $decoded['error']['message'] 
                : 'Unknown error from Bill.com';
            
            log_message('error', 'Bill.com: API error for invoice ' . $invoice_id . ': ' . $error_message);
            
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
        
        // Check if response contains invoice ID (successful creation)
        if (isset($decoded['id'])) {
            log_message('info', 'Bill.com: Successfully created invoice ' . $invoice_id . ' with Bill.com ID: ' . $decoded['id']);
            
            return [
                'success' => true,
                'data' => $decoded
            ];
        }
        
        // Unexpected response format
        log_message('error', 'Bill.com: Unexpected response format for invoice ' . $invoice_id);
        return [
            'success' => false,
            'error' => 'Unexpected response from Bill.com API'
        ];
    }

    /**
     * Test connection to Bill.com API
     *
     * @return array Response array with 'success' boolean and 'message'
     */
    public function testConnection()
    {
        if (!$this->authenticate()) {
            return [
                'success' => false,
                'message' => 'Missing credentials - please configure username, password, and dev_key'
            ];
        }
        
        // Try to login
        $login_result = $this->login();
        
        if ($login_result['success']) {
            return [
                'success' => true,
                'message' => 'Successfully connected to Bill.com!'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Login failed: ' . $login_result['error']
            ];
        }
    }

    /**
     * Format cURL error into user-friendly message
     *
     * @param int $errno cURL error number
     * @param string $error cURL error message
     * @param string $url URL that was being accessed
     * @return string Formatted error message with troubleshooting tips
     */
    private function formatCurlError($errno, $error, $url)
    {
        $message = 'Connection Error: ';
        
        switch ($errno) {
            case 6: // CURLE_COULDNT_RESOLVE_HOST
                $host = parse_url($url, PHP_URL_HOST);
                $message .= "Could not connect to Bill.com (hostname: $host). ";
                $message .= "Please check: 1) Your API URL setting is correct ";
                $message .= "(Sandbox: gateway.stage.bill.com, Production: gateway.prod.bill.com), ";
                $message .= "2) Your server has internet access, 3) DNS is working properly.";
                break;
                
            case 7: // CURLE_COULDNT_CONNECT
                $message .= "Failed to connect to Bill.com. ";
                $message .= "Please check: 1) Your server has internet access, ";
                $message .= "2) Your firewall allows outbound HTTPS connections, ";
                $message .= "3) Bill.com services are online.";
                break;
                
            case 28: // CURLE_OPERATION_TIMEDOUT
                $message .= "Connection to Bill.com timed out (>30 seconds). ";
                $message .= "Please check your internet connection speed and try again.";
                break;
                
            case 35: // CURLE_SSL_CONNECT_ERROR
                $message .= "SSL/TLS connection failed. ";
                $message .= "Please check: 1) Your server's OpenSSL installation, ";
                $message .= "2) Your server can access HTTPS sites, 3) Your PHP cURL extension is properly configured.";
                break;
                
            case 51: // CURLE_PEER_FAILED_VERIFICATION
                $message .= "SSL certificate verification failed. ";
                $message .= "The Bill.com SSL certificate could not be verified. ";
                $message .= "Please check your server's CA certificate bundle is up to date.";
                break;
                
            case 60: // CURLE_SSL_CACERT
                $message .= "SSL certificate problem. ";
                $message .= "Please check: 1) Your server's CA certificate bundle exists, ";
                $message .= "2) The ca-bundle.crt or cacert.pem file is up to date, ";
                $message .= "3) PHP curl.cainfo setting in php.ini.";
                break;
                
            default:
                $message .= $error . " (Error code: $errno). ";
                $message .= "Please check your server's cURL configuration and internet connectivity.";
                break;
        }
        
        return $message;
    }
}
