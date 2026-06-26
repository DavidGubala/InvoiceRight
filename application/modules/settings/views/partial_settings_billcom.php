<div class="row">
    <div class="col-xs-12 col-md-8 col-md-offset-2">

        <div class="panel panel-default">
            <div class="panel-heading">
                <?php _trans('billcom_settings'); ?>
            </div>
            <div class="panel-body">

                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i> 
                                    <?php echo trans('billcom_settings'); ?> - 
                                    Configure the email-based invoice submission to Bill.com. When enabled, the "Send to Bill.com" button will email the invoice PDF directly to the client's Bill.com submission address. 
                                    <strong>Requires SMTP email to be configured</strong> in the <a href="<?php echo site_url('settings'); ?>#settings-email">Email settings tab</a>.
                                </div>

                                <div class="form-group">
                                    <div class="checkbox">
                                        <label>
                                            <input type="hidden" name="settings[billcom_enabled]" value="0">
                                            <input type="checkbox" name="settings[billcom_enabled]" value="1"
                                                <?php check_select(get_setting('billcom_enabled'), 1, '==', true) ?>>
                                            <strong><?php _trans('billcom_enabled'); ?></strong>
                                        </label>
                                    </div>
                                </div>

                                <hr>

                                <div class="alert alert-info" style="font-size: 14px;">
                                    <i class="fa fa-lightbulb-o"></i> 
                                    <strong>How it works:</strong><br>
                                    1. Enable Bill.com integration above<br>
                                    2. For each client, check "Enable Bill.com Integration" and enter their Bill.com invoice submission email address<br>
                                    3. On the Invoices page, use "Send to Bill.com" to email the invoice PDF<br>
                                    4. Bill.com processes the attached PDF<br>
                                    5. The invoice is marked with a checkmark once sent<br><br>

                                    <i class="fa fa-info-circle"></i> 
                                    <strong>Note:</strong> Each client needs their own Bill.com submission email address configured in their client form. This is the email address their Bill.com account uses to receive invoices.
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fa fa-exclamation-triangle"></i> 
                                    <strong>Important:</strong> SMTP email configuration is required for this feature to work. Make sure email sending is configured and working in the Email settings tab before using Bill.com integration.
                                </div>

                                <hr>

                                <div class="form-group">
                                    <p class="help-block">
                                        <strong>Setup Steps:</strong><br>
                                        1. Configure SMTP email in Email Settings tab<br>
                                        2. Enable Bill.com integration above<br>
                                        3. Save settings<br>
                                        4. Enable Bill.com for specific clients and enter their Bill.com submission email<br>
                                        5. Go to Invoices and use "Send to Bill.com" option for individual invoices or select multiple invoices and use batch send
                                    </p>
                                </div>

            </div>
        </div>

    </div>
</div>

