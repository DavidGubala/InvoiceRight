<script>
    $(function () {
        $('#enter-batch-payment').modal('show');

        $('#enter-batch-payment').on('shown', function () {
            $('#payment_date').focus();
        });

        // Select2 for all select inputs
        $(".simple-select").select2();

        $('#btn_modal_batch_payment_submit').click(function () {
            var invoice_ids = <?php echo json_encode($invoice_ids); ?>;
            
            $.post("<?php echo site_url('payments/ajax/add_batch'); ?>", {
                    invoice_ids: invoice_ids,
                    payment_method_id: $('#payment_method_id').val(),
                    payment_date: $('#payment_date').val(),
                    payment_note: $('#payment_note').val(),
                    <?php echo $csrf_token_name; ?>: '<?php echo $csrf_hash; ?>'
                },
                function (data) {
                    var response = json_parse(data, <?php echo (int) IP_DEBUG; ?>);
                    if (response.success === 1) {
                        // The validation was successful and payments were added
                        window.location.reload();
                    }
                    else {
                        // The validation was not successful
                        $('.control-group').removeClass('has-error');
                        if (response.validation_errors) {
                            for (var key in response.validation_errors) {
                                if(response.validation_errors.hasOwnProperty(key)) {
                                    $('#' + key).parent().parent().addClass('has-error');
                                }
                            }
                        }
                        if (response.error) {
                            alert(response.error);
                        }
                    }
                });
        });
    });
</script>

<div id="enter-batch-payment" class="modal col-xs-12 col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2"
     role="dialog" aria-labelledby="modal_enter_batch_payment" aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <a data-dismiss="modal" class="close"><i class="fa fa-close"></i></a>

            <h3><?php _trans('enter_batch_payment'); ?></h3>
        </div>

        <div class="modal-body">
            <form>

                <div class="alert alert-info">
                    <strong><?php _trans('batch_payment_info'); ?></strong><br>
                    <?php _trans('paying_full_amount_for'); ?> <strong><?php echo count($invoices); ?></strong> <?php _trans('invoices'); ?>:
                    <ul style="margin-top: 10px;">
<?php foreach ($invoices as $invoice): ?>
                        <li>
                            <strong><?php echo $invoice->invoice_number; ?></strong> - 
                            <?php echo format_currency($invoice->invoice_balance); ?>
                        </li>
<?php endforeach; ?>
                    </ul>
                    <hr>
                    <strong><?php _trans('total_payment_amount'); ?>: <?php echo format_currency($total_balance); ?></strong>
                </div>

                <div class="form-group has-feedback">

                    <label class="payment_date"><?php _trans('payment_date'); ?></label>

                    <div class="input-group">
                        <input name="payment_date" id="payment_date"
                               class="form-control datepicker"
                               value="<?php echo date(date_format_setting()); ?>">
                        <span class="input-group-addon">
                            <i class="fa fa-calendar fa-fw"></i>
                        </span>
                    </div>

                </div>

                <div class="form-group">
                    <label for="payment_method_id"><?php _trans('payment_method'); ?></label>

                    <div class="controls">
                        <select name="payment_method_id" id="payment_method_id" class="form-control simple-select">
                            <option value=""><?php _trans('none'); ?></option>
<?php foreach ($payment_methods as $payment_method): ?>
                            <option value="<?php echo $payment_method->payment_method_id; ?>">
                                <?php _htmlsc($payment_method->payment_method_name); ?>
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="payment_note"><?php _trans('note'); ?></label>

                    <div class="controls">
                        <textarea name="payment_note" id="payment_note" class="form-control" 
                                  placeholder="<?php _trans('note_will_be_added_to_all'); ?>"></textarea>
                    </div>
                </div>

            </form>
        </div>

        <div class="modal-footer">
            <div class="btn-group">
                <button class="btn btn-success" id="btn_modal_batch_payment_submit" type="button">
                    <i class="fa fa-check"></i>
                    <?php _trans('submit'); ?>
                </button>
                <button class="btn btn-danger" type="button" data-dismiss="modal">
                    <i class="fa fa-times"></i>
                    <?php _trans('cancel'); ?>
                </button>
            </div>
        </div>
    </div>

</div>

