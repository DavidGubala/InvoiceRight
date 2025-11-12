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
class Invoices extends Admin_Controller
{
    /**
     * Invoices constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->model('mdl_invoices');
    }

    public function index(): void
    {
        // Display all invoices by default
        redirect('invoices/status/all');
    }

    /**
     * @param int $page
     */
    public function status(string $status = 'all', $page = 0): void
    {
        // Determine which group of invoices to load
        switch ($status) {
            case 'draft':
                $this->mdl_invoices->is_draft();
                break;
            case 'sent':
                $this->mdl_invoices->is_sent();
                break;
            case 'viewed':
                $this->mdl_invoices->is_viewed();
                break;
            case 'paid':
                $this->mdl_invoices->is_paid();
                break;
            case 'overdue':
                $this->mdl_invoices->is_overdue();
                break;
        }

        $this->mdl_invoices->paginate(site_url('invoices/status/' . $status), $page);
        $invoices = $this->mdl_invoices->result();

        $this->layout->set(
            [
                'invoices'           => $invoices,
                'status'             => $status,
                'filter_display'     => true,
                'filter_placeholder' => trans('filter_invoices'),
                'filter_method'      => 'filter_invoices',
                'invoice_statuses'   => $this->mdl_invoices->statuses(),
            ]
        );

        $this->layout->buffer('content', 'invoices/index');
        $this->layout->render();
    }

    public function archive(): void
    {
        $invoice_array = $this->mdl_invoices->get_archives(0);
        $this->layout->set(
            [
                'filter_display'     => true,
                'filter_placeholder' => trans('filter_archives'),
                'filter_method'      => 'filter_archives',
                'invoices_archive'   => $invoice_array,
            ]
        );
        $this->layout->buffer('content', 'invoices/archive');
        $this->layout->render();
    }

    public function download($invoice): void
    {
        $safeBaseDir = realpath(UPLOADS_ARCHIVE_FOLDER);

        $fileName = urldecode(basename($invoice)); // Strip directory traversal sequences
        $filePath = realpath($safeBaseDir . DIRECTORY_SEPARATOR . $fileName);

        if ($filePath === false || ! str_starts_with($filePath, $safeBaseDir)) {
            log_message('error', 'Invalid file access attempt: ' . $fileName);
            show_404();

            return;
        }

        if ( ! file_exists($filePath)) {
            log_message('error', 'While downloading: File not found: ' . $filePath);
            show_404();

            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function view($invoice_id): void
    {
        $this->load->model(
            [
                'invoices/mdl_items',
                'invoices/mdl_invoice_tax_rates',
                'tax_rates/mdl_tax_rates',
                'payment_methods/mdl_payment_methods',
                'custom_fields/mdl_custom_fields',
                'custom_values/mdl_custom_values',
                'custom_fields/mdl_invoice_custom',
                'units/mdl_units',
                'upload/mdl_uploads',
            ]
        );
        $this->load->helper(['custom_values', 'dropzone', 'e-invoice']);
        $this->load->module('payments');

        $this->db->reset_query();

        /*$invoice_custom = $this->mdl_invoice_custom->where('invoice_id', $invoice_id)->get();

        if ($invoice_custom->num_rows()) {
            $invoice_custom = $invoice_custom->row();

            unset($invoice_custom->invoice_id, $invoice_custom->invoice_custom_id);

            foreach ($invoice_custom as $key => $val) {
                $this->mdl_invoices->set_form_value('custom[' . $key . ']', $val);
            }
        }*/

        $fields  = $this->mdl_invoice_custom->by_id($invoice_id)->get()->result();
        $invoice = $this->mdl_invoices->get_by_id($invoice_id);

        if ( ! $invoice) {
            show_404();
        }

        $custom_fields = $this->mdl_custom_fields->by_table('ip_invoice_custom')->get()->result();
        $custom_values = [];
        foreach ($custom_fields as $custom_field) {
            if (in_array($custom_field->custom_field_type, $this->mdl_custom_values->custom_value_fields())) {
                $values                                        = $this->mdl_custom_values->get_by_fid($custom_field->custom_field_id)->result();
                $custom_values[$custom_field->custom_field_id] = $values;
            }
        }

        foreach ($custom_fields as $cfield) {
            foreach ($fields as $fvalue) {
                if ($fvalue->invoice_custom_fieldid == $cfield->custom_field_id) {
                    // TODO: Hackish, may need a better optimization
                    $this->mdl_invoices->set_form_value(
                        'custom[' . $cfield->custom_field_id . ']',
                        $fvalue->invoice_custom_fieldvalue
                    );
                    break;
                }
            }
        }

        // Check whether there are payment custom fields
        $payment_cf       = $this->mdl_custom_fields->by_table('ip_payment_custom')->get();
        $payment_cf_exist = ($payment_cf->num_rows() > 0) ? 'yes' : 'no';
        // Get Items
        $items = $this->mdl_items->where('invoice_id', $invoice_id)->get()->result();
        // Get eInvoice library name and user checks
        $einvoice = get_einvoice_usage($invoice, $items);
        // Activate 'Change_user' if admin users > 1  (get the sum of user type = 1 & active)
        $change_user = $this->db->from('ip_users')->where(['user_type' => 1, 'user_active' => 1])->select_sum('user_type')->get()->row();
        $change_user = $change_user->user_type > 1;

        $this->layout->set(
            [
                'invoice'           => $invoice,
                'items'             => $items,
                'invoice_id'        => $invoice_id,
                'einvoice'          => $einvoice,
                'change_user'       => $change_user,
                'tax_rates'         => $this->mdl_tax_rates->get()->result(),
                'invoice_tax_rates' => $this->mdl_invoice_tax_rates->where('invoice_id', $invoice_id)->get()->result(),
                'units'             => $this->mdl_units->get()->result(),
                'payment_methods'   => $this->mdl_payment_methods->get()->result(),
                'custom_fields'     => $custom_fields,
                'custom_values'     => $custom_values,
                'custom_js_vars'    => [
                    'currency_symbol'           => get_setting('currency_symbol'),
                    'currency_symbol_placement' => get_setting('currency_symbol_placement'),
                    'decimal_point'             => get_setting('decimal_point'),
                ],
                'invoice_statuses'   => $this->mdl_invoices->statuses(),
                'payment_cf_exist'   => $payment_cf_exist,
                'legacy_calculation' => config_item('legacy_calculation'),
            ]
        );

        $this->layout->buffer(
            [
                ['modal_delete_invoice', 'invoices/modal_delete_invoice'],
                ['modal_add_invoice_tax', 'invoices/modal_add_invoice_tax'],
                ['modal_add_payment', 'payments/modal_add_payment'],
                ['content', 'invoices/view' . ($invoice->sumex_id ? '_sumex' : '')],
            ]
        );

        $this->layout->render();
    }

    public function delete($invoice_id): void
    {
        // Get the status of the invoice
        $invoice        = $this->mdl_invoices->get_by_id($invoice_id);
        $invoice_status = $invoice->invoice_status_id;

        if ($invoice_status == 1 || $this->config->item('enable_invoice_deletion') === true) {
            // If invoice refers to tasks, mark those tasks back to 'Complete'
            $this->load->model('tasks/mdl_tasks');
            $tasks = $this->mdl_tasks->update_on_invoice_delete($invoice_id);

            // Delete the invoice
            $this->mdl_invoices->delete($invoice_id);
        } else {
            // Add alert that invoices can't be deleted
            $this->session->set_flashdata('alert_error', trans('invoice_deletion_forbidden'));
        }

        // Redirect to invoice index
        redirect('invoices/index');
    }

    /**
     * @param      $invoice_id
     * @param bool $stream
     */
    public function generate_pdf($invoice_id, $stream = true, $invoice_template = null): void
    {
        $this->load->helper('pdf');

        if (get_setting('mark_invoices_sent_pdf') == 1) {
            $this->mdl_invoices->generate_invoice_number_if_applicable($invoice_id);
            $this->mdl_invoices->mark_sent($invoice_id);
        }

        generate_invoice_pdf($invoice_id, $stream, $invoice_template, null);
    }

    public function generate_xml($invoice_id): void
    {
        $invoice = $this->mdl_invoices->get_by_id($invoice_id);
        if ( ! $invoice) {
            show_404();
        }

        $this->load->model('invoices/mdl_items');
        $items = $this->mdl_items->where('invoice_id', $invoice_id)->get()->result();

        $this->load->helper('e-invoice'); // eInvoicing++
        $einvoice = get_einvoice_usage($invoice, $items, false);
        if ( ! $einvoice->user) {
            show_404();
        }

        // eInvoice library to Generate the appropriate UBL/CII or false
        $xml_id    = $einvoice->name; // $invoice->client_einvoicing_version
        $options   = [];
        $generator = $xml_id;
        $path      = APPPATH . 'helpers/XMLconfigs/';
        if ($xml_id && file_exists($path . $xml_id . '.php') && include $path . $xml_id . '.php') {
            $embed_xml = $xml_setting['embedXML'];
            $XMLname   = $xml_setting['XMLname'];
            $options   = (empty($xml_setting['options']) ? $options : $xml_setting['options']); // Optional
            $generator = (empty($xml_setting['generator']) ? $generator : $xml_setting['generator']); // Optional
        }

        $filename = trans('invoice') . '_' . str_replace(['\\', '/'], '_', $invoice->invoice_number);
        $path     = generate_xml_invoice_file($invoice, $items, $generator, $filename, $options);
        $this->output->set_content_type('text/xml');
        $this->output->set_output(file_get_contents($path));
        unlink($path);
    }

    public function generate_sumex_pdf($invoice_id): void
    {
        $this->load->helper('pdf');

        generate_invoice_sumex($invoice_id);
    }

    public function generate_sumex_copy($invoice_id): void
    {
        $this->load->model('invoices/mdl_items');
        $this->load->library('Sumex', [
            'invoice' => $this->mdl_invoices->get_by_id($invoice_id),
            'items'   => $this->mdl_items->where('invoice_id', $invoice_id)->get()->result(),
            'options' => [
                'copy'   => '1',
                'storno' => '0',
            ],
        ]);

        $this->output->set_content_type('application/pdf');
        $this->output->set_output($this->sumex->pdf());
    }

    public function delete_invoice_tax(string $invoice_id, $invoice_tax_rate_id): void
    {
        $this->load->model('invoices/mdl_invoice_tax_rates');
        $this->mdl_invoice_tax_rates->delete($invoice_tax_rate_id);

        $this->load->model('invoices/mdl_invoice_amounts');
        $global_discount['item'] = $this->mdl_invoice_amounts->get_global_discount($invoice_id);
        // Recalculate invoice amounts
        $this->mdl_invoice_amounts->calculate($invoice_id, $global_discount);

        redirect('invoices/view/' . $invoice_id);
    }

    public function recalculate_all_invoices(): void
    {
        $this->db->select('invoice_id');
        $invoice_ids = $this->db->get('ip_invoices')->result();

        $this->load->model('invoices/mdl_invoice_amounts');

        foreach ($invoice_ids as $invoice_id) {
            $global_discount['item'] = $this->mdl_invoice_amounts->get_global_discount($invoice_id->invoice_id);
            // Recalculate invoice amounts
            $this->mdl_invoice_amounts->calculate($invoice_id->invoice_id, $global_discount);
        }
    }

    /**
     * Send a single invoice to Bill.com
     *
     * @param int $invoice_id
     */
    public function send_to_billcom($invoice_id): void
    {
        log_message('info', 'User initiated Bill.com submission for invoice ' . $invoice_id);
        
        $this->load->library('billcom');

        // Check if Bill.com is enabled
        if (!$this->billcom->is_enabled()) {
            log_message('warning', 'Bill.com submission failed: integration not enabled (invoice ' . $invoice_id . ')');
            $this->session->set_flashdata('alert_error', trans('billcom_not_enabled'));
            redirect('invoices/view/' . $invoice_id);
            return;
        }

        // Load required models
        $this->load->model([
            'invoices/mdl_items',
            'clients/mdl_clients',
            'custom_fields/mdl_invoice_custom'
        ]);

        // Get invoice data
        $invoice = $this->mdl_invoices->get_by_id($invoice_id);
        if (!$invoice) {
            log_message('error', 'Bill.com submission failed: invoice ' . $invoice_id . ' not found');
            $this->session->set_flashdata('alert_error', trans('invoice_not_found'));
            redirect('invoices/index');
            return;
        }

        // Get client data
        $client = $this->mdl_clients->get_by_id($invoice->client_id);
        if (!$client) {
            log_message('error', 'Bill.com submission failed: client ' . $invoice->client_id . ' not found for invoice ' . $invoice_id);
            $this->session->set_flashdata('alert_error', trans('client_not_found'));
            redirect('invoices/view/' . $invoice_id);
            return;
        }

        // Check if Bill.com is enabled for this client
        if ($client->client_billcom_enabled != 1) {
            log_message('info', 'Bill.com submission skipped: client ' . $client->client_id . ' (' . $client->client_name . ') does not have Bill.com enabled');
            $this->session->set_flashdata('alert_error', trans('billcom_client_disabled'));
            redirect('invoices/view/' . $invoice_id);
            return;
        }

        // Validate client has email or Bill.com customer ID
        if (empty($client->client_email) && empty($client->client_billcom_customer_id)) {
            log_message('error', 'Bill.com submission failed: client ' . $client->client_id . ' (' . $client->client_name . ') has no email and no Bill.com Customer ID');
            $this->session->set_flashdata('alert_error', trans('billcom_client_no_email_or_id'));
            redirect('invoices/view/' . $invoice_id);
            return;
        }

        // Get invoice items
        $items = $this->mdl_items->where('invoice_id', $invoice_id)->get()->result();
        if (empty($items)) {
            log_message('warning', 'Bill.com submission failed: invoice ' . $invoice_id . ' has no line items');
            $this->session->set_flashdata('alert_error', trans('billcom_no_items'));
            redirect('invoices/view/' . $invoice_id);
            return;
        }

        // Get invoice custom fields
        $custom_fields = $this->mdl_invoice_custom->by_id($invoice_id)->get()->result();

        log_message('info', 'Submitting invoice ' . $invoice_id . ' (IP#' . $invoice->invoice_number . ') to Bill.com for client ' . $client->client_name);

        // Send to Bill.com
        $result = $this->billcom->createInvoice($invoice, $client, $items, $custom_fields);

        if ($result['success']) {
            // Update invoice with Bill.com ID
            $this->mdl_invoices->mark_sent_to_billcom($invoice_id, $result['data']['id']);
            log_message('info', 'Successfully submitted invoice ' . $invoice_id . ' to Bill.com (ID: ' . $result['data']['id'] . ')');
            $this->session->set_flashdata('alert_success', trans('billcom_success') . ' (Bill.com ID: ' . $result['data']['id'] . ')');
        } else {
            log_message('error', 'Failed to submit invoice ' . $invoice_id . ' to Bill.com: ' . $result['error']);
            $this->session->set_flashdata('alert_error', trans('billcom_error') . ': ' . $result['error']);
        }

        redirect('invoices/view/' . $invoice_id);
    }

    /**
     * Send multiple invoices to Bill.com in batch
     */
    public function batch_send_to_billcom(): void
    {
        $this->load->library('billcom');

        // Check if Bill.com is enabled
        if (!$this->billcom->is_enabled()) {
            log_message('warning', 'Batch Bill.com submission failed: integration not enabled');
            $this->session->set_flashdata('alert_error', trans('billcom_not_enabled'));
            redirect('invoices/index');
            return;
        }

        // Get invoice IDs from POST
        $invoice_ids = $this->input->post('invoice_ids');
        if (empty($invoice_ids) || !is_array($invoice_ids)) {
            log_message('warning', 'Batch Bill.com submission failed: no invoices selected');
            $this->session->set_flashdata('alert_error', trans('billcom_no_invoices_selected'));
            redirect('invoices/index');
            return;
        }

        log_message('info', 'User initiated batch Bill.com submission for ' . count($invoice_ids) . ' invoice(s)');

        // Load required models
        $this->load->model([
            'invoices/mdl_items',
            'clients/mdl_clients',
            'custom_fields/mdl_invoice_custom'
        ]);

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($invoice_ids as $invoice_id) {
            // Get invoice data
            $invoice = $this->mdl_invoices->get_by_id($invoice_id);
            if (!$invoice) {
                $error_count++;
                $errors[] = "Invoice #$invoice_id not found";
                continue;
            }

            // Get client data
            $client = $this->mdl_clients->get_by_id($invoice->client_id);
            if (!$client) {
                $error_count++;
                $errors[] = "Client not found for invoice #" . ($invoice->invoice_number ?: $invoice_id);
                continue;
            }

            // Check if Bill.com is enabled for this client
            if ($client->client_billcom_enabled != 1) {
                $error_count++;
                $errors[] = "Invoice #" . ($invoice->invoice_number ?: $invoice_id) . ": " . trans('billcom_client_disabled');
                continue;
            }

            // Validate client has email or Bill.com customer ID
            if (empty($client->client_email) && empty($client->client_billcom_customer_id)) {
                $error_count++;
                $errors[] = "Invoice #" . ($invoice->invoice_number ?: $invoice_id) . ": " . trans('billcom_client_no_email_or_id');
                continue;
            }

            // Get invoice items
            $items = $this->mdl_items->where('invoice_id', $invoice_id)->get()->result();
            if (empty($items)) {
                $error_count++;
                $errors[] = "No items for invoice #" . ($invoice->invoice_number ?: $invoice_id);
                continue;
            }

            // Get invoice custom fields
            $custom_fields = $this->mdl_invoice_custom->by_id($invoice_id)->get()->result();

            // Send to Bill.com
            $result = $this->billcom->createInvoice($invoice, $client, $items, $custom_fields);

            if ($result['success']) {
                // Update invoice with Bill.com ID
                $this->mdl_invoices->mark_sent_to_billcom($invoice_id, $result['data']['id']);
                $success_count++;
            } else {
                $error_count++;
                $errors[] = "Invoice #" . ($invoice->invoice_number ?: $invoice_id) . ": " . $result['error'];
            }
        }

        // Log batch results
        log_message('info', 'Batch Bill.com submission complete: ' . $success_count . ' succeeded, ' . $error_count . ' failed');
        if ($error_count > 0) {
            log_message('error', 'Batch Bill.com errors: ' . implode('; ', $errors));
        }

        // Set flash messages with mark_as_flash to ensure they persist through redirect
        if ($success_count > 0) {
            $success_message = trans('billcom_batch_success') . ': ' . $success_count . ' ' . trans('invoices');
            $this->session->set_flashdata('alert_success', $success_message);
            log_message('debug', 'Setting success flash message: ' . $success_message);
        }

        if ($error_count > 0) {
            $error_message = trans('billcom_batch_errors') . ': ' . $error_count . '<br>' . implode('<br>', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $error_message .= '<br>... ' . trans('and') . ' ' . (count($errors) - 5) . ' ' . trans('more');
            }
            $this->session->set_flashdata('alert_error', $error_message);
            log_message('debug', 'Setting error flash message with ' . count($errors) . ' errors');
        }

        // Check for custom redirect URL (e.g., from client view)
        $redirect_url = $this->input->post('redirect_url');
        if (!empty($redirect_url)) {
            log_message('debug', 'Batch send redirecting to: ' . $redirect_url);
            redirect($redirect_url);
        } else {
            // Redirect to status/all to avoid double redirect (index redirects to status/all)
            log_message('debug', 'Batch send redirecting to: invoices/status/all');
            redirect('invoices/status/all');
        }
    }
}
