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
                    Configure your Bill.com account credentials to enable invoice synchronization. 
                    You'll need your Bill.com login credentials and a developer key from the <a href="https://developer.bill.com/" target="_blank">Bill.com Developer Portal</a>.
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

                <div class="form-group">
                    <label for="billcom_username">
                        <?php _trans('billcom_username'); ?>
                    </label>
                    <input type="email" class="form-control" 
                        name="settings[billcom_username]" 
                        id="billcom_username"
                        value="<?php echo htmlspecialchars(get_setting('billcom_username')); ?>"
                        placeholder="your-email@example.com">
                    <small class="text-muted">Your Bill.com account email address</small>
                </div>

                <div class="form-group">
                    <label for="billcom_password">
                        <?php _trans('billcom_password'); ?>
                    </label>
                    <input type="password" class="form-control" 
                        name="settings[billcom_password]" 
                        id="billcom_password"
                        value="<?php echo $this->crypt->decode(get_setting('billcom_password')); ?>"
                        placeholder="Enter your Bill.com password">
                    <input type="hidden" value="1" name="settings[billcom_password_field_is_password]">
                    <small class="text-muted">Your Bill.com account password (will be encrypted)</small>
                </div>

                <div class="form-group">
                    <label for="billcom_dev_key">
                        <?php _trans('billcom_dev_key'); ?>
                    </label>
                    <input type="password" class="form-control" 
                        name="settings[billcom_dev_key]" 
                        id="billcom_dev_key"
                        value="<?php echo $this->crypt->decode(get_setting('billcom_dev_key')); ?>"
                        placeholder="Enter your Bill.com Developer Key">
                    <input type="hidden" value="1" name="settings[billcom_dev_key_field_is_password]">
                    <small class="text-muted">Your Bill.com API developer key from the developer portal (will be encrypted)</small>
                </div>

                <div class="form-group">
                    <label for="billcom_org_id">
                        <?php _trans('billcom_org_id'); ?>
                    </label>
                    <input type="text" class="form-control" 
                        name="settings[billcom_org_id]" 
                        id="billcom_org_id"
                        value="<?php echo htmlspecialchars(get_setting('billcom_org_id')); ?>"
                        placeholder="Optional - Leave blank for default organization">
                    <small class="text-muted">Optional: Specific Bill.com organization ID (leave blank to use your default organization)</small>
                </div>

                <div class="form-group">
                    <label for="billcom_api_url">
                        <?php _trans('billcom_api_url'); ?>
                    </label>
                    <select name="settings[billcom_api_url]" id="billcom_api_url" class="form-control">
                        <option value="https://gateway.stage.bill.com/connect" 
                            <?php check_select(get_setting('billcom_api_url', 'https://gateway.stage.bill.com/connect'), 'https://gateway.stage.bill.com/connect'); ?>>
                            Sandbox (https://gateway.stage.bill.com/connect)
                        </option>
                        <option value="https://gateway.prod.bill.com/connect" 
                            <?php check_select(get_setting('billcom_api_url'), 'https://gateway.prod.bill.com/connect'); ?>>
                            Production (https://gateway.prod.bill.com/connect)
                        </option>
                    </select>
                    <small class="text-muted">Select Sandbox for testing, Production for live invoices</small>
                </div>

                <hr>

                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i> 
                    <strong>Important:</strong> Make sure to test your connection using the sandbox environment before switching to production.
                </div>

                <div class="form-group">
                    <p class="help-block">
                        <strong>How to use:</strong><br>
                        1. Enable Bill.com integration above<br>
                        2. Enter your Bill.com login credentials (username/email and password)<br>
                        3. Enter your Developer Key from the Bill.com Developer Portal<br>
                        4. Select sandbox or production environment<br>
                        5. Save settings<br>
                        6. Enable Bill.com for specific clients<br>
                        7. Go to Invoices and use "Send to Bill.com" option for individual invoices or select multiple invoices and use batch send
                    </p>
                    <p class="help-block">
                        <strong>Note:</strong> Your credentials are securely encrypted and stored. The system will automatically log in to Bill.com when needed.
                    </p>
                </div>

                <hr>

                <div class="form-group">
                    <button type="button" class="btn btn-info" id="btn-test-billcom-connection">
                        <i class="fa fa-plug"></i> <?php _trans('billcom_test_connection'); ?>
                    </button>
                    <span id="billcom-test-result" style="margin-left: 15px;"></span>
                </div>

<script>
$(document).ready(function() {
    $('#btn-test-billcom-connection').on('click', function() {
        var btn = $(this);
        var result = $('#billcom-test-result');
        
        // Get current form values
        var username = $('#billcom_username').val();
        var password = $('#billcom_password').val();
        var dev_key = $('#billcom_dev_key').val();
        var org_id = $('#billcom_org_id').val();
        var api_url = $('#billcom_api_url').val();
        
        // Debug: log values
        console.log('Username:', username);
        console.log('Password:', password ? '***' : '(empty)');
        console.log('Dev Key:', dev_key ? '***' : '(empty)');
        console.log('Org ID:', org_id);
        console.log('API URL:', api_url);
        
        if (!username || !password || !dev_key) {
            result.html('<span class="text-danger"><i class="fa fa-times"></i> Please fill in username, password, and developer key first.</span>');
            return;
        }
        
        var data = {
            billcom_username: username,
            billcom_password: password,
            billcom_dev_key: dev_key,
            billcom_org_id: org_id || '',
            billcom_api_url: api_url,
            '<?php echo $this->security->get_csrf_token_name(); ?>': '<?php echo $this->security->get_csrf_hash(); ?>'
        };
        
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
        result.html('');
        
        $.ajax({
            url: '<?php echo site_url('settings/test_billcom_connection'); ?>',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    result.html('<span class="text-success"><i class="fa fa-check"></i> ' + response.message + '</span>');
                } else {
                    result.html('<span class="text-danger"><i class="fa fa-times"></i> ' + response.message + '</span>');
                }
            },
            error: function() {
                result.html('<span class="text-danger"><i class="fa fa-times"></i> Test failed. Please try again.</span>');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fa fa-plug"></i> <?php _trans('billcom_test_connection'); ?>');
            }
        });
    });
});
</script>

            </div>
        </div>

    </div>
</div>

