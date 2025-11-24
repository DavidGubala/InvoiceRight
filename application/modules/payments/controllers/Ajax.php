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

    public function add()
    {
        $this->load->model('payments/mdl_payments');

        if ($this->mdl_payments->run_validation()) {
            $payment_id = $this->mdl_payments->save();

            $response = [
                'success'    => 1,
                'payment_id' => $payment_id,
            ];
        } else {
            $this->load->helper('json_error');
            $response = [
                'success'           => 0,
                'validation_errors' => json_errors(),
            ];
        }

        echo json_encode($response);
    }

    public function modal_add_payment()
    {
        $this->load->module('layout');
        $this->load->model('payments/mdl_payments');
        $this->load->model('payment_methods/mdl_payment_methods');
        $this->load->model('custom_fields/mdl_payment_custom');

        $data = [
            'payment_methods'        => $this->mdl_payment_methods->get()->result(),
            'invoice_id'             => $this->security->xss_clean($this->input->post('invoice_id')),
            'invoice_balance'        => $this->input->post('invoice_balance'),
            'invoice_payment_method' => $this->input->post('invoice_payment_method'),
            'payment_cf_exist'       => $this->security->xss_clean($this->input->post('payment_cf_exist')),
        ];

        $this->layout->load_view('payments/modal_add_payment', $data);
    }

    public function modal_add_batch_payment()
    {
        $this->load->module('layout');
        $this->load->model('payments/mdl_payments');
        $this->load->model('invoices/mdl_invoices');
        $this->load->model('payment_methods/mdl_payment_methods');

        $invoice_ids = $this->input->post('invoice_ids');
        
        if (empty($invoice_ids) || !is_array($invoice_ids)) {
            echo json_encode(['error' => 'No invoices selected']);
            return;
        }

        // Get invoice details
        $invoices = [];
        $total_balance = 0;
        
        foreach ($invoice_ids as $invoice_id) {
            $invoice = $this->mdl_invoices
                ->where('ip_invoices.invoice_id', $invoice_id)
                ->get()
                ->row();
            
            if ($invoice && $invoice->invoice_balance > 0) {
                $invoices[] = $invoice;
                $total_balance += $invoice->invoice_balance;
            }
        }

        $data = [
            'payment_methods' => $this->mdl_payment_methods->get()->result(),
            'invoice_ids'     => array_column($invoices, 'invoice_id'),
            'invoices'        => $invoices,
            'total_balance'   => $total_balance,
            'csrf_token_name' => $this->security->get_csrf_token_name(),
            'csrf_hash'       => $this->security->get_csrf_hash(),
        ];

        $this->layout->load_view('payments/modal_add_batch_payment', $data);
    }

    public function add_batch()
    {
        $this->load->model('payments/mdl_payments');
        $this->load->model('invoices/mdl_invoices');

        $invoice_ids = $this->input->post('invoice_ids');
        $payment_method_id = $this->input->post('payment_method_id');
        $payment_date = $this->input->post('payment_date');
        $payment_note = $this->input->post('payment_note');

        if (empty($invoice_ids) || !is_array($invoice_ids)) {
            echo json_encode([
                'success' => 0,
                'error' => trans('no_invoices_selected')
            ]);
            return;
        }

        $success_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($invoice_ids as $invoice_id) {
            // Get current invoice balance
            $invoice = $this->mdl_invoices
                ->where('ip_invoices.invoice_id', $invoice_id)
                ->get()
                ->row();

            if (!$invoice) {
                $errors[] = "Invoice ID $invoice_id not found";
                $error_count++;
                continue;
            }

            // Skip if invoice has no balance
            if ($invoice->invoice_balance <= 0) {
                continue;
            }

            // Prepare payment data - use full invoice balance
            $payment_data = [
                'invoice_id'        => $invoice_id,
                'payment_amount'    => $invoice->invoice_balance,  // Pay full balance
                'payment_method_id' => $payment_method_id,
                'payment_date'      => $payment_date,
                'payment_note'      => $payment_note,
            ];

            // Set the form data for validation
            foreach ($payment_data as $key => $value) {
                $_POST[$key] = $value;
            }

            // Validate and save
            if ($this->mdl_payments->run_validation()) {
                $payment_id = $this->mdl_payments->save(null, $payment_data);
                if ($payment_id) {
                    $success_count++;
                } else {
                    $errors[] = "Failed to save payment for invoice " . $invoice->invoice_number;
                    $error_count++;
                }
            } else {
                $errors[] = "Validation failed for invoice " . $invoice->invoice_number;
                $error_count++;
            }
        }

        if ($success_count > 0) {
            echo json_encode([
                'success' => 1,
                'message' => "Successfully added payments to $success_count invoice(s)" . ($error_count > 0 ? ", $error_count failed" : ''),
                'success_count' => $success_count,
                'error_count' => $error_count,
                'errors' => $errors
            ]);
        } else {
            echo json_encode([
                'success' => 0,
                'error' => 'Failed to add payments. ' . implode(', ', $errors)
            ]);
        }
    }
}
