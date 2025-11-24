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
class Ajax extends Admin_Controller
{
    public $ajax_controller = true;

    public function name_query()
    {
        // Load the model & helper
        $this->load->model('clients/mdl_clients');

        $response = [];

        // Get the post input
        $query                   = $this->input->get('query');
        $permissiveSearchClients = $this->input->get('permissive_search_clients');

        if (empty($query)) {
            echo json_encode($response);
            exit;
        }

        // Search for chars "in the middle" of clients names
        $moreClientsQuery = $permissiveSearchClients ? '%' : '';

        // Search for clients
        $escapedQuery = $this->db->escape_str($query);
        $escapedQuery = str_replace('%', '', $escapedQuery);

        $clients = $this->mdl_clients
            ->where('client_active', 1)
            ->having("client_name LIKE '" . $moreClientsQuery . $escapedQuery . "%'")
            ->or_having("client_surname LIKE '" . $moreClientsQuery . $escapedQuery . "%'")
            ->or_having("client_fullname LIKE '" . $moreClientsQuery . $escapedQuery . "%'")
            ->order_by('client_name')
            ->get()
            ->result();

        foreach ($clients as $client) {
            $response[] = [
                'id'   => $client->client_id,
                'text' => htmlsc(format_client($client, false)),
            ];
        }

        // Return the results
        echo json_encode($response);
    }

    /**
     * Get the latest clients.
     */
    public function get_latest()
    {
        // Load the model & helper
        $this->load->model('clients/mdl_clients');

        $response = [];

        $clients = $this->mdl_clients
            ->where('client_active', 1)
            ->limit(5)
            ->order_by('client_date_created')
            ->get()
            ->result();

        foreach ($clients as $client) {
            $response[] = [
                'id'   => $client->client_id,
                'text' => htmlsc(format_client($client, false)),
            ];
        }

        // Return the results
        echo json_encode($response);
    }

    public function save_preference_permissive_search_clients()
    {
        $this->load->model('mdl_settings');
        $permissiveSearchClients = $this->input->get('permissive_search_clients');

        if ( ! preg_match('!^[0-1]{1}$!', $permissiveSearchClients)) {
            exit;
        }

        $this->mdl_settings->save('enable_permissive_search_clients', $permissiveSearchClients);
    }

    /**
     * Delete client note id.
     */
    public function delete_client_note()
    {
        $success        = 0;
        $client_note_id = $this->input->post('client_note_id');
        $this->load->model('mdl_client_notes');

        // Only continue if the note exists or no item id was provided
        if ($this->mdl_client_notes->get_by_id($client_note_id) || empty($client_note_id)) {
            // Delete invoice item
            $this->load->model('mdl_client_notes');
            $item = $this->mdl_client_notes->delete($client_note_id);

            // Check if deletion was successful
            if ($item) {
                $success = 1;
            }
        }

        // Return the response
        echo json_encode([
            'success' => $success,
        ]);
    }

    public function save_client_note()
    {
        $this->load->model('clients/mdl_client_notes');

        if ($this->mdl_client_notes->run_validation()) {
            $this->mdl_client_notes->save();

            $response = [
                'success'   => 1,
                'new_token' => $this->security->get_csrf_hash(),
            ];
        } else {
            $this->load->helper('json_error');
            $response = [
                'success'           => 0,
                'new_token'         => $this->security->get_csrf_hash(),
                'validation_errors' => json_errors(),
            ];
        }

        echo json_encode($response);
    }

    public function load_client_notes()
    {
        $this->load->model('clients/mdl_client_notes');
        $data = [
            'client_notes' => $this->mdl_client_notes->where(
                'client_id',
                $this->input->post('client_id')
            )->get()->result(),
        ];

        $this->layout->load_view('clients/partial_notes', $data);
    }

    /**
     * Load more invoices for infinite scroll in client view
     */
    public function load_more_invoices()
    {
        $client_id = $this->input->post('client_id');
        $offset = (int)$this->input->post('offset');
        $limit = (int)$this->input->post('limit') ?: 20;
        $status = $this->input->post('status') ?: 'all';
        $search = $this->input->post('search');

        if (empty($client_id)) {
            echo json_encode(['success' => false, 'error' => 'Client ID required']);
            return;
        }

        $this->load->model('invoices/mdl_invoices');
        $this->load->model('clients/mdl_clients');
        $this->load->helper('date');
        
        // Get client for Bill.com status
        $client = $this->mdl_clients->get_by_id($client_id);
        
        // Apply status filter
        $this->mdl_invoices->by_client($client_id);
        
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
            // 'all' - no additional filter
        }
        
        // Apply search filter if provided
        if (!empty($search)) {
            $search = trim($search);
            // Join with invoice items table to search item names and descriptions
            $this->mdl_invoices->db->join('ip_invoice_items', 'ip_invoice_items.invoice_id = ip_invoices.invoice_id', 'left');
            // Join with custom fields to search trailer numbers and other custom fields
            $this->mdl_invoices->db->join('ip_invoice_custom', 'ip_invoice_custom.invoice_id = ip_invoices.invoice_id', 'left');
            $this->mdl_invoices->db->group_start();
            $this->mdl_invoices->db->like('ip_invoice_items.item_name', $search);
            $this->mdl_invoices->db->or_like('ip_invoice_items.item_description', $search);
            $this->mdl_invoices->db->or_like('ip_invoice_custom.invoice_custom_fieldvalue', $search);
            $this->mdl_invoices->db->group_end();
            // Group by to avoid duplicate invoices (an invoice can have multiple matching items/fields)
            $this->mdl_invoices->db->group_by('ip_invoices.invoice_id');
        }
        
        // Get invoices with offset and limit
        $invoices = $this->mdl_invoices
            ->limit($limit, $offset)
            ->get()
            ->result();

        // Get invoice statuses for rendering
        $invoice_statuses = $this->mdl_invoices->statuses();
        
        // Render the invoice rows HTML
        $html = '';
        $invoice_count = count($invoices);
        
        // Bill.com settings
        $billcom_enabled = get_setting('billcom_enabled') == '1';
        $client_billcom_enabled = $client && isset($client->client_billcom_enabled) && $client->client_billcom_enabled == 1;
        $show_billcom = $billcom_enabled && $client_billcom_enabled;
        
        foreach ($invoices as $idx => $invoice) {
            // Disable read-only if not applicable
            if ($this->config->item('disable_read_only') == true) {
                $invoice->is_read_only = 0;
            }
            
            // Use dropup for last few items (after midpoint of loaded batch)
            $dropup = $idx > ($invoice_count / 2);
            
            $status_label = $invoice_statuses[$invoice->invoice_status_id]['label'];
            $status_class = $invoice_statuses[$invoice->invoice_status_id]['class'];
            
            $html .= '<tr>';
            
            // Checkbox column (always visible for batch operations)
            $html .= '<td class="text-center">';
            $html .= '<input type="checkbox" class="invoice-select" value="' . $invoice->invoice_id . '">';
            $html .= '</td>';
            
            // Status column
            $html .= '<td>';
            $html .= '<span class="label ' . $status_class . '">';
            $html .= $status_label;
            if ($invoice->invoice_sign == '-1') {
                $html .= '&nbsp;<i class="fa fa-credit-invoice" title="' . trans('credit_invoice') . '"></i>';
            }
            if ($invoice->is_read_only) {
                $html .= '&nbsp;<i class="fa fa-read-only" title="' . trans('read_only') . '"></i>';
            }
            if ($invoice->invoice_is_recurring) {
                $html .= '&nbsp;<i class="fa fa-refresh" title="' . trans('recurring') . '"></i>';
            }
            $html .= '</span></td>';
            
            // Invoice number
            $html .= '<td>';
            $html .= '<a href="' . site_url('invoices/view/' . $invoice->invoice_id) . '" title="' . trans('edit') . '">';
            $html .= htmlspecialchars($invoice->invoice_number ? $invoice->invoice_number : $invoice->invoice_id);
            $html .= '</a></td>';
            
            // Date created
            $html .= '<td>' . date_from_mysql($invoice->invoice_date_created, true) . '</td>';
            
            // Due date
            $html .= '<td>';
            $html .= '<span class="' . ($invoice->is_overdue ? 'font-overdue' : '') . '">';
            $html .= date_from_mysql($invoice->invoice_date_due, true);
            $html .= '</span></td>';
            
            // Client name
            $html .= '<td>';
            $html .= '<a href="' . site_url('clients/view/' . $invoice->client_id) . '" title="' . trans('view_client') . '">';
            $html .= htmlspecialchars(format_client($invoice));
            $html .= '</a></td>';
            
            // Amount
            $html .= '<td class="amount ' . ($invoice->invoice_sign == '-1' ? 'text-danger' : '') . '">';
            $html .= format_currency($invoice->invoice_total);
            $html .= '</td>';
            
            // Balance
            $html .= '<td class="amount last">';
            $html .= format_currency($invoice->invoice_balance);
            $html .= '</td>';
            
            // Options column - full dropdown menu
            $html .= '<td>';
            $html .= '<div class="options btn-group' . ($dropup ? ' dropup' : '') . '">';
            $html .= '<a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">';
            $html .= '<i class="fa fa-cog"></i> ' . trans('options');
            $html .= '</a>';
            $html .= '<ul class="dropdown-menu">';
            
            // Edit option (only if not read-only)
            if ($invoice->is_read_only != 1) {
                $html .= '<li>';
                $html .= '<a href="' . site_url('invoices/view/' . $invoice->invoice_id) . '">';
                $html .= '<i class="fa fa-edit fa-margin"></i> ' . trans('edit');
                $html .= '</a>';
                $html .= '</li>';
            }
            
            // Download PDF
            $html .= '<li>';
            $html .= '<a href="' . site_url('invoices/generate_pdf/' . $invoice->invoice_id) . '" target="_blank">';
            $html .= '<i class="fa fa-print fa-margin"></i> ' . trans('download_pdf');
            $html .= '</a>';
            $html .= '</li>';
            
            // Email invoice
            $html .= '<li>';
            $html .= '<a href="' . site_url('mailer/invoice/' . $invoice->invoice_id) . '">';
            $html .= '<i class="fa fa-send fa-margin"></i> ' . trans('send_email');
            $html .= '</a>';
            $html .= '</li>';
            
            // Copy invoice (only if status is 2, 3, or 4)
            if (in_array($invoice->invoice_status_id, [2, 3, 4])) {
                $html .= '<li>';
                $html .= '<a href="' . site_url('invoices/create/' . $invoice->invoice_id) . '">';
                $html .= '<i class="fa fa-copy fa-margin"></i> ' . trans('copy_invoice');
                $html .= '</a>';
                $html .= '</li>';
            }
            
            // Send to Bill.com (if enabled and invoice has Bill.com ID or can be sent)
            if ($show_billcom) {
                $html .= '<li>';
                $html .= '<a href="' . site_url('invoices/send_to_billcom/' . $invoice->invoice_id) . '">';
                $html .= '<i class="fa fa-cloud-upload fa-margin"></i> ' . trans('billcom_send_invoice');
                if (!empty($invoice->invoice_billcom_id)) {
                    $html .= ' <i class="fa fa-check text-success"></i>';
                }
                $html .= '</a>';
                $html .= '</li>';
            }
            
            // Delete (only if draft or deletion is enabled and not read-only)
            if ($invoice->invoice_status_id == 1 || 
                ($this->config->item('enable_invoice_deletion') === true && $invoice->is_read_only != 1)) {
                $html .= '<li>';
                $html .= '<form action="' . site_url('invoices/delete/' . $invoice->invoice_id) . '" method="POST">';
                $html .= '<input type="hidden" name="' . $this->security->get_csrf_token_name() . '" value="' . $this->security->get_csrf_hash() . '">';
                $html .= '<button type="submit" class="dropdown-button" onclick="return confirm(\'' . trans('delete_invoice_warning') . '\');">';
                $html .= '<i class="fa fa-trash-o fa-margin"></i> ' . trans('delete');
                $html .= '</button>';
                $html .= '</form>';
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</div>';
            $html .= '</td>';
            
            $html .= '</tr>';
        }

        echo json_encode([
            'success' => true,
            'html' => $html,
            'count' => $invoice_count,
            'has_more' => $invoice_count === $limit
        ]);
    }

    /**
     * AJAX endpoint for loading more payments (infinite scroll)
     */
    public function load_more_payments()
    {
        $this->load->model('payments/mdl_payments');
        $this->load->helper('date');

        $client_id = $this->input->post('client_id');
        $offset = $this->input->post('offset') ?: 0;
        $limit = $this->input->post('limit') ?: 20;

        if (!$client_id) {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(['html' => '', 'count' => 0, 'has_more' => false]));
            return;
        }

        // Get payments for this client
        $payments = $this->mdl_payments
            ->by_client($client_id)
            ->limit($limit, $offset)
            ->get()
            ->result();

        // Render payment rows as HTML
        $html = '';
        foreach ($payments as $payment) {
            $html .= '<tr>';
            
            // Payment date
            $html .= '<td>' . date_from_mysql($payment->payment_date, true) . '</td>';
            
            // Invoice date
            $html .= '<td>' . date_from_mysql($payment->invoice_date_created, true) . '</td>';
            
            // Invoice number
            $html .= '<td>';
            $html .= '<a href="' . site_url('invoices/view/' . $payment->invoice_id) . '">';
            $html .= htmlsc($payment->invoice_number);
            $html .= '</a>';
            $html .= '</td>';
            
            // Client name
            $html .= '<td>';
            $html .= '<a href="' . site_url('clients/view/' . $payment->client_id) . '" title="' . trans('view_client') . '">';
            $html .= htmlsc(format_client($payment));
            $html .= '</a>';
            $html .= '</td>';
            
            // Amount
            $html .= '<td class="amount last">' . format_currency($payment->payment_amount) . '</td>';
            
            // Payment method
            $html .= '<td>' . htmlsc($payment->payment_method_name) . '</td>';
            
            // Note
            $html .= '<td>' . htmlsc($payment->payment_note) . '</td>';
            
            // Options dropdown
            $html .= '<td>';
            $html .= '<div class="options btn-group">';
            $html .= '<a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">';
            $html .= '<i class="fa fa-cog"></i> ' . trans('options');
            $html .= '</a>';
            $html .= '<ul class="dropdown-menu">';
            
            // Edit
            $html .= '<li>';
            $html .= '<a href="' . site_url('payments/form/' . $payment->payment_id) . '">';
            $html .= '<i class="fa fa-edit fa-margin"></i> ' . trans('edit');
            $html .= '</a>';
            $html .= '</li>';
            
            // Delete
            $html .= '<li>';
            $html .= '<form action="' . site_url('payments/delete/' . $payment->payment_id) . '" method="POST">';
            $html .= '<input type="hidden" name="' . $this->security->get_csrf_token_name() . '" value="' . $this->security->get_csrf_hash() . '">';
            $html .= '<button type="submit" class="dropdown-button" onclick="return confirm(\'' . trans('delete_record_warning') . '\');">';
            $html .= '<i class="fa fa-trash-o fa-margin"></i> ' . trans('delete');
            $html .= '</button>';
            $html .= '</form>';
            $html .= '</li>';
            
            $html .= '</ul>';
            $html .= '</div>';
            $html .= '</td>';
            
            $html .= '</tr>';
        }

        // Check if there are more payments to load
        $has_more = count($payments) >= $limit;

        // Return JSON response
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'html' => $html,
                'count' => count($payments),
                'has_more' => $has_more
            ]));
    }
}
