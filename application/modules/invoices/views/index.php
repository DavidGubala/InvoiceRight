<div id="headerbar">

    <h1 class="headerbar-title"><?php _trans('invoices'); ?></h1>

    <div class="headerbar-item pull-right">
        <button type="button" class="btn btn-default btn-sm submenu-toggle hidden-lg"
                data-toggle="collapse" data-target="#ip-submenu-collapse">
            <i class="fa fa-bars"></i> <?php _trans('submenu'); ?>
        </button>
<?php if (get_setting('billcom_enabled') == '1') { ?>
        <button type="button" class="btn btn-sm btn-success" id="btn-batch-send-billcom" style="display:none;">
            <i class="fa fa-cloud-upload"></i> <?php _trans('billcom_send_batch'); ?>
        </button>
<?php } ?>
        <a class="create-invoice btn btn-sm btn-primary" href="#">
            <i class="fa fa-plus"></i> <?php _trans('new'); ?>
        </a>
    </div>

    <div class="headerbar-item pull-right visible-lg">
        <?php echo pager(site_url('invoices/status/' . $this->uri->segment(3)), 'mdl_invoices'); ?>
    </div>

    <div class="headerbar-item pull-right visible-lg">
        <div class="btn-group btn-group-sm index-options">
            <a href="<?php echo site_url('invoices/status/all'); ?>"
               class="btn <?php echo $status == 'all' ? 'btn-primary' : 'btn-default' ?>">
                <?php _trans('all'); ?>
            </a>
            <a href="<?php echo site_url('invoices/status/draft'); ?>"
               class="btn <?php echo $status == 'draft' ? 'btn-primary' : 'btn-default' ?>">
                <?php _trans('draft'); ?>
            </a>
            <a href="<?php echo site_url('invoices/status/sent'); ?>"
               class="btn <?php echo $status == 'sent' ? 'btn-primary' : 'btn-default' ?>">
                <?php _trans('sent'); ?>
            </a>
            <a href="<?php echo site_url('invoices/status/viewed'); ?>"
               class="btn <?php echo $status == 'viewed' ? 'btn-primary' : 'btn-default' ?>">
                <?php _trans('viewed'); ?>
            </a>
            <a href="<?php echo site_url('invoices/status/paid'); ?>"
               class="btn <?php echo $status == 'paid' ? 'btn-primary' : 'btn-default' ?>">
                <?php _trans('paid'); ?>
            </a>
            <a href="<?php echo site_url('invoices/status/overdue'); ?>"
               class="btn <?php echo $status == 'overdue' ? 'btn-primary' : 'btn-default' ?>">
                <?php _trans('overdue'); ?>
            </a>
        </div>
    </div>

</div>

<div id="submenu">
    <div class="collapse clearfix" id="ip-submenu-collapse">

        <div class="submenu-row">
            <?php echo pager(site_url('invoices/status/' . $this->uri->segment(3)), 'mdl_invoices'); ?>
        </div>

        <div class="submenu-row">
            <div class="btn-group btn-group-sm index-options">
                <a href="<?php echo site_url('invoices/status/all'); ?>"
                   class="btn <?php echo $status == 'all' ? 'btn-primary' : 'btn-default' ?>">
                    <?php _trans('all'); ?>
                </a>
                <a href="<?php echo site_url('invoices/status/draft'); ?>"
                   class="btn  <?php echo $status == 'draft' ? 'btn-primary' : 'btn-default' ?>">
                    <?php _trans('draft'); ?>
                </a>
                <a href="<?php echo site_url('invoices/status/sent'); ?>"
                   class="btn  <?php echo $status == 'sent' ? 'btn-primary' : 'btn-default' ?>">
                    <?php _trans('sent'); ?>
                </a>
                <a href="<?php echo site_url('invoices/status/viewed'); ?>"
                   class="btn  <?php echo $status == 'viewed' ? 'btn-primary' : 'btn-default' ?>">
                    <?php _trans('viewed'); ?>
                </a>
                <a href="<?php echo site_url('invoices/status/paid'); ?>"
                   class="btn  <?php echo $status == 'paid' ? 'btn-primary' : 'btn-default' ?>">
                    <?php _trans('paid'); ?>
                </a>
                <a href="<?php echo site_url('invoices/status/overdue'); ?>"
                   class="btn  <?php echo $status == 'overdue' ? 'btn-primary' : 'btn-default' ?>">
                    <?php _trans('overdue'); ?>
                </a>
            </div>
        </div>

    </div>
</div>

<div id="content" class="table-content">
    
    <?php 
    // Debug: Check if flash data exists (TEMPORARY - remove after testing)
    if (IP_DEBUG) {
        $session_data = $this->session->all_userdata();
        echo '<!-- Flash Debug: ';
        echo 'Session keys: ' . implode(', ', array_keys($session_data));
        echo ' -->';
    }
    
    $this->layout->load_view('layout/alerts'); 
    ?>
    
    <div id="filter_results">
        <?php $this->layout->load_view('invoices/partial_invoice_table'); ?>
    </div>
</div>

<?php if (get_setting('billcom_enabled') == '1') { ?>
<script>
$(document).ready(function() {
    // Handle select all checkbox
    $('#select-all-invoices').on('change', function() {
        $('.invoice-select').prop('checked', $(this).prop('checked'));
        toggleBatchButton();
    });

    // Handle individual checkbox changes
    $(document).on('change', '.invoice-select', function() {
        // Update select all checkbox if all items are selected
        var total = $('.invoice-select').length;
        var checked = $('.invoice-select:checked').length;
        $('#select-all-invoices').prop('checked', total === checked);
        toggleBatchButton();
    });

    // Show/hide batch send button based on selection
    function toggleBatchButton() {
        var checked = $('.invoice-select:checked').length;
        if (checked > 0) {
            $('#btn-batch-send-billcom').show();
            $('#btn-batch-send-billcom').text('<?php _trans('billcom_send_batch'); ?> (' + checked + ')');
        } else {
            $('#btn-batch-send-billcom').hide();
        }
    }

    // Handle batch send button click
    $('#btn-batch-send-billcom').on('click', function() {
        var selectedIds = [];
        $('.invoice-select:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('<?php _trans('billcom_no_invoices_selected'); ?>');
            return;
        }

        if (!confirm('<?php _trans('billcom_send_confirm'); ?> ' + selectedIds.length + ' <?php _trans('invoice'); ?>' + (selectedIds.length > 1 ? 's' : '') + ' <?php _trans('to'); ?> Bill.com?')) {
            return;
        }

        // Show loading indicator
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php _trans('sending'); ?>...');

        // Create a form and submit
        var form = $('<form>', {
            'method': 'POST',
            'action': '<?php echo site_url('invoices/batch_send_to_billcom'); ?>'
        });

        // Add CSRF token
        form.append($('<input>', {
            'type': 'hidden',
            'name': '<?php echo $this->security->get_csrf_token_name(); ?>',
            'value': '<?php echo $this->security->get_csrf_hash(); ?>'
        }));

        // Add invoice IDs
        $.each(selectedIds, function(index, value) {
            form.append($('<input>', {
                'type': 'hidden',
                'name': 'invoice_ids[]',
                'value': value
            }));
        });

        $('body').append(form);
        form.submit();
    });
});
</script>
<?php } ?>
