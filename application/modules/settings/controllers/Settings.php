<?php

if ( ! defined('BASEPATH')) {
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

#[AllowDynamicProperties]
class Settings extends Admin_Controller
{
    /**
     * Settings constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('mdl_settings');
        $this->load->library('crypt');
        $this->load->library('form_validation');
        $this->load->helper('payments_helper');
    }

    public function index()
    {
        // Get the payment gateway configurations
        $this->config->load('payment_gateways');
        $gateways = $this->config->item('payment_gateways');

        // Get the number formats configurations
        $this->config->load('number_formats');
        $number_formats = $this->config->item('number_formats');

        // Save input if request is POSt
        if ($this->input->post('settings')) {
            $settings = $this->input->post('settings');

            // Only execute if the setting is different
            if ($settings['tax_rate_decimal_places'] != get_setting('tax_rate_decimal_places')) {
                $this->db->query("
                    ALTER TABLE `ip_tax_rates` CHANGE `tax_rate_percent` `tax_rate_percent`
                    DECIMAL( 5, {$settings['tax_rate_decimal_places']} ) NOT null");
            }

            // Save the submitted settings :todo:improve: Save In One SQL query : $db_array[$key] = val; •••& @end mdl save $db_array.
            foreach ($settings as $key => $value) {
                if (str_contains($key, 'field_is_password') || str_contains($key, 'field_is_amount')) {
                    // Skip all meta fields
                    continue;
                }

                if (isset($settings[$key . '_field_is_password']) && empty($value)) {
                    // Password field, but empty value, let's skip it
                    continue;
                }

                if (isset($settings[$key . '_field_is_password']) && $value != '') {
                    // Encrypt passwords but don't save empty passwords
                    $this->mdl_settings->save($key, $this->crypt->encode(mb_trim($value)));
                } elseif (isset($settings[$key . '_field_is_amount'])) {
                    // Format amount inputs
                    $this->mdl_settings->save($key, standardize_amount($value));
                } else {
                    $this->mdl_settings->save($key, $value);
                }

                if ($key == 'number_format') {
                    // Set thousands_separator and decimal_point according to number_format
                    $this->mdl_settings->save('decimal_point', $number_formats[$value]['decimal_point']);
                    $this->mdl_settings->save('thousands_separator', $number_formats[$value]['thousands_separator']);
                }
            }

            $upload_config = [
                'upload_path'   => './uploads/',
                'allowed_types' => 'gif|jpg|jpeg|png|svg', // Invoice quote logo image :Todo: Add webp avif? (& test imgs in pdf)
                'max_size'      => '9999',
                'max_width'     => '9999',
                'max_height'    => '9999',
            ];

            // Check for invoice logo upload
            if ($_FILES['invoice_logo']['name']) {
                $this->load->library('upload', $upload_config);

                if ( ! $this->upload->do_upload('invoice_logo')) {
                    $this->session->set_flashdata('alert_error', $this->upload->display_errors());
                    redirect('settings');
                }

                $upload_data = $this->upload->data();

                $this->mdl_settings->save('invoice_logo', $upload_data['file_name']);
            }

            // Check for login logo upload
            if ($_FILES['login_logo']['name']) {
                $this->load->library('upload', $upload_config);

                if ( ! $this->upload->do_upload('login_logo')) {
                    $this->session->set_flashdata('alert_error', $this->upload->display_errors());
                    redirect('settings');
                }

                $upload_data = $this->upload->data();

                $this->mdl_settings->save('login_logo', $upload_data['file_name']);
            }

            $this->session->set_flashdata('alert_success', trans('settings_successfully_saved'));

            redirect('settings');
        }

        // Load required resources
        $this->load->model([
            'invoice_groups/mdl_invoice_groups',
            'tax_rates/mdl_tax_rates',
            'email_templates/mdl_email_templates',
            'payment_methods/mdl_payment_methods',
            'invoices/mdl_templates',
            'custom_fields/mdl_invoice_custom',
            'custom_fields/mdl_custom_fields',
        ]);

        // Collect the list of templates
        $pdf_invoice_templates    = $this->mdl_templates->get_invoice_templates('pdf');
        $public_invoice_templates = $this->mdl_templates->get_invoice_templates('public');
        $pdf_quote_templates      = $this->mdl_templates->get_quote_templates('pdf');
        $public_quote_templates   = $this->mdl_templates->get_quote_templates('public');

        // Get all themes
        $available_themes = $this->mdl_settings->get_themes();

        // Set data in the layout
        $this->layout->set(
            [
                'invoice_groups'           => $this->mdl_invoice_groups->get()->result(),
                'tax_rates'                => $this->mdl_tax_rates->get()->result(),
                'payment_methods'          => $this->mdl_payment_methods->get()->result(),
                'public_invoice_templates' => $public_invoice_templates,
                'pdf_invoice_templates'    => $pdf_invoice_templates,
                'public_quote_templates'   => $public_quote_templates,
                'pdf_quote_templates'      => $pdf_quote_templates,
                'languages'                => get_available_languages(),
                'countries'                => get_country_list(trans('cldr')),
                'date_formats'             => date_formats(),
                'current_date'             => new DateTime(),
                'available_themes'         => $available_themes,
                'email_templates_quote'    => $this->mdl_email_templates->where('email_template_type', 'quote')->get()->result(),
                'email_templates_invoice'  => $this->mdl_email_templates->where('email_template_type', 'invoice')->get()->result(),
                'custom_fields'            => ['ip_invoice_custom' => $this->mdl_custom_fields->by_table('ip_invoice_custom')->get()->result()],
                'gateway_drivers'          => $gateways,
                'number_formats'           => $number_formats,
                'gateway_currency_codes'   => get_currencies(),
                'first_days_of_weeks'      => ['0' => lang('sunday'), '1' => lang('monday')],
                'legacy_calculation'       => config_item('legacy_calculation'),
            ]
        );

        $this->layout->buffer('content', 'settings/index');
        $this->layout->render();
    }

    /**
     * @param $type
     */
    public function remove_logo(string $type)
    {
        unlink('./uploads/' . get_setting($type . '_logo'));

        $this->mdl_settings->save($type . '_logo', '');

        $this->session->set_flashdata('alert_success', lang($type . '_logo_removed'));

        redirect('settings');
    }

    /**
     * Test Bill.com connection with provided credentials
     * Used by AJAX to test connection before saving settings
     */
    public function test_billcom_connection()
    {
        // Get test credentials from POST
        $test_username = $this->input->post('billcom_username');
        $test_password = $this->input->post('billcom_password');
        $test_dev_key = $this->input->post('billcom_dev_key');
        $test_org_id = $this->input->post('billcom_org_id');
        $test_api_url = $this->input->post('billcom_api_url');
        
        // Debug logging
        log_message('debug', 'Bill.com Test - Username: ' . ($test_username ? 'present' : 'EMPTY'));
        log_message('debug', 'Bill.com Test - Password: ' . ($test_password ? 'present' : 'EMPTY'));
        log_message('debug', 'Bill.com Test - Dev Key: ' . ($test_dev_key ? 'present' : 'EMPTY'));
        log_message('debug', 'Bill.com Test - Org ID: ' . ($test_org_id ?: 'empty'));
        log_message('debug', 'Bill.com Test - API URL: ' . ($test_api_url ?: 'empty'));
        
        // Validate required fields
        if (empty($test_username) || empty($test_password) || empty($test_dev_key)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Missing required credentials (username, password, or developer key)'
            ]);
            return;
        }
        
        if (empty($test_api_url)) {
            $test_api_url = 'https://gateway.prod.bill.com/connect';
        }
        
        // Test by calling Bill.com login API directly (bypass settings cache)
        $login_data = [
            'username' => $test_username,
            'password' => $test_password,
            'devKey' => $test_dev_key
        ];
        
        // Add orgId if specified
        if (!empty($test_org_id)) {
            $login_data['organizationId'] = $test_org_id;
        }
        
        $endpoint = $test_api_url . '/v3/login';
        
        // Debug: Log the exact request we're sending
        $request_body = json_encode($login_data);
        log_message('debug', 'Bill.com Test - Sending to: ' . $endpoint);
        log_message('debug', 'Bill.com Test - Request body: ' . $request_body);
        
        try {
            $ch = curl_init();
            
            $headers = [
                'Content-Type: application/json'
            ];
            
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TESTING ONLY
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            $curl_errno = curl_errno($ch);
            
            curl_close($ch);
            
            // Debug: Log the response
            log_message('debug', 'Bill.com Test - HTTP Code: ' . $http_code);
            log_message('debug', 'Bill.com Test - Response: ' . substr($response, 0, 500));
            
            if ($response === false) {
                $result = [
                    'success' => false,
                    'message' => 'Connection failed: ' . $curl_error . ' (Error code: ' . $curl_errno . ')'
                ];
            } elseif ($http_code < 200 || $http_code >= 300) {
                // Show the full raw response from Bill.com for debugging
                $result = [
                    'success' => false,
                    'message' => 'Login failed (HTTP ' . $http_code . '). Bill.com Response: ' . $response
                ];
            } else {
                // Parse success response
                $decoded = json_decode($response, true);
                
                if (isset($decoded['sessionId'])) {
                    $result = [
                        'success' => true,
                        'message' => 'Successfully connected to Bill.com!'
                    ];
                } else {
                    $result = [
                        'success' => false,
                        'message' => 'Unexpected response from Bill.com (missing sessionId)'
                    ];
                }
            }
            
        } catch (Exception $e) {
            $result = [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
