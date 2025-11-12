<div class="table-responsive">
    <table class="table table-hover table-striped">

        <thead>
        <tr>
            <th class="text-center" style="width: 30px;">
                <input type="checkbox" id="select-all-invoices">
            </th>
            <th><?php _trans('status'); ?></th>
            <th><?php _trans('invoice'); ?></th>
            <th><?php _trans('created'); ?></th>
            <th><?php _trans('due_date'); ?></th>
            <th><?php _trans('client_name'); ?></th>
            <th class="amount"><?php _trans('amount'); ?></th>
            <th class="amount last"><?php _trans('balance'); ?></th>
            <th><?php _trans('options'); ?></th>
        </tr>
        </thead>

        <tbody>
<?php
$invoice_idx        = 1;
$invoice_count      = count($invoices);
$invoice_list_split = $invoice_count > 3 ? $invoice_count / 2 : 9999;
foreach ($invoices as $invoice) {
    // Disable read-only if not applicable
    if ($this->config->item('disable_read_only') == true) {
        $invoice->is_read_only = 0;
    }
    // Convert the dropdown menu to a dropup if invoice is after the invoice split
    $dropup = $invoice_idx > $invoice_list_split;
?>
            <tr>
                <td class="text-center">
                    <input type="checkbox" class="invoice-select" value="<?php echo $invoice->invoice_id; ?>">
                </td>
                <td>
                    <span class="label <?php echo $invoice_statuses[$invoice->invoice_status_id]['class']; ?>">
                        <?php echo $invoice_statuses[$invoice->invoice_status_id]['label'];
                            if ($invoice->invoice_sign == '-1') {?>&nbsp;<i class="fa fa-credit-invoice" title="<?php _trans('credit_invoice'); ?>"></i><?php }
                            if ($invoice->is_read_only) {?>&nbsp;<i class="fa fa-read-only" title="<?php _trans('read_only'); ?>"></i><?php }
                            if ($invoice->invoice_is_recurring) {?>&nbsp;<i class="fa fa-refresh" title="<?php _trans('recurring'); ?>"></i><?php }
                        ?>
                    </span>
                </td>

                <td>
                    <a href="<?php echo site_url('invoices/view/' . $invoice->invoice_id); ?>"
                       title="<?php _trans('edit'); ?>">
                        <?php echo $invoice->invoice_number ? $invoice->invoice_number : $invoice->invoice_id; ?>
                    </a>
                </td>

                <td>
                    <?php echo date_from_mysql($invoice->invoice_date_created); ?>
                </td>

                <td>
                    <span class="<?php echo $invoice->is_overdue ? 'font-overdue' : ''; ?>">
                        <?php echo date_from_mysql($invoice->invoice_date_due); ?>
                    </span>
                </td>

                <td>
                    <a href="<?php echo site_url('clients/view/' . $invoice->client_id); ?>"
                       title="<?php _trans('view_client'); ?>">
                        <?php _htmlsc(format_client($invoice)); ?>
                    </a>
                </td>

                <td class="amount <?php echo ($invoice->invoice_sign == '-1') ? 'text-danger' : ''; ?>">
                    <?php echo format_currency($invoice->invoice_total); ?>
                </td>

                <td class="amount last">
                    <?php echo format_currency($invoice->invoice_balance); ?>
                </td>

                <td>
                    <div class="options btn-group<?php echo $dropup ? ' dropup' : ''; ?>">
                        <a class="btn btn-default btn-sm dropdown-toggle" data-toggle="dropdown" href="#">
                            <i class="fa fa-cog"></i> <?php _trans('options'); ?>
                        </a>
                        <ul class="dropdown-menu">
<?php
    if ($invoice->is_read_only != 1) {
?>
                            <li>
                                <a href="<?php echo site_url('invoices/view/' . $invoice->invoice_id); ?>">
                                    <i class="fa fa-edit fa-margin"></i> <?php _trans('edit'); ?>
                                </a>
                            </li>
<?php
    }
?>
                            <li>
                                <a href="<?php echo site_url('invoices/generate_pdf/' . $invoice->invoice_id); ?>"
                                   target="_blank">
                                    <i class="fa fa-print fa-margin"></i> <?php _trans('download_pdf'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo site_url('mailer/invoice/' . $invoice->invoice_id); ?>">
                                    <i class="fa fa-send fa-margin"></i> <?php _trans('send_email'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="#" class="invoice-add-payment"
                                   data-invoice-id="<?php echo $invoice->invoice_id; ?>"
                                   data-invoice-balance="<?php echo $invoice->invoice_balance; ?>"
                                   data-invoice-payment-method="<?php echo $invoice->payment_method; ?>">
                                    <i class="fa fa-money fa-margin"></i>
                                    <?php _trans('enter_payment'); ?>
                                </a>
                            </li>
<?php
    // Bill.com integration option
    if (get_setting('billcom_enabled') == '1') {
        // Access Bill.com columns directly (already in query, no extra DB call)
        $has_billcom_id = !empty($invoice->invoice_billcom_id);
        // Check if client has Bill.com enabled (default to 0 if column doesn't exist yet)
        $client_billcom_enabled = (isset($invoice->client_billcom_enabled) && $invoice->client_billcom_enabled) ? 1 : 0;
?>
                            <li>
                                <a href="<?php echo site_url('invoices/send_to_billcom/' . $invoice->invoice_id); ?>"
                                   <?php if (!$client_billcom_enabled) { ?>title="<?php _trans('billcom_client_disabled'); ?>" style="color: #999;"<?php } ?>>
                                    <i class="fa fa-cloud-upload fa-margin"></i>
                                    <?php _trans('billcom_send_invoice'); ?>
<?php
        if ($has_billcom_id) {
            echo ' <span class="label label-success" style="font-size:9px;"><i class="fa fa-check"></i></span>';
        } elseif (!$client_billcom_enabled) {
            echo ' <span class="label label-default" style="font-size:9px;"><i class="fa fa-ban"></i></span>';
        }
?>
                                </a>
                            </li>
<?php
    }
?>
<?php
    if (
        $invoice->invoice_status_id == 1 ||
        ($this->config->item('enable_invoice_deletion') === true && $invoice->is_read_only != 1)
    ) {
?>
                            <li>
                                <form action="<?php echo site_url('invoices/delete/' . $invoice->invoice_id); ?>"
                                      method="POST">
                                    <?php _csrf_field(); ?>
                                    <button type="submit" class="dropdown-button"
                                            onclick="return confirm('<?php _trans('delete_invoice_warning'); ?>');">
                                        <i class="fa fa-trash-o fa-margin"></i> <?php _trans('delete'); ?>
                                    </button>
                                </form>
                            </li>
<?php
    }
?>
                        </ul>
                    </div>
                </td>
            </tr>
<?php
    $invoice_idx++;
} // End foreach invoices
?>
        </tbody>

    </table>
</div>
