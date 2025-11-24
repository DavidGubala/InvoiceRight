<script>
    // e-invoice user switch anima
    const switch_fa_toggle = function (id){
        const f = $('#'+id);
        f.toggleClass('fa-toggle-on').toggleClass('fa-toggle-off');
    }

    $(function () {
        const client_id = <?php echo $client->client_id; ?>;
        function add_delete_client_notes_click_event(){
            $('.delete_client_note').click(delete_client_note);
        }
        function reload_client_notes(data){
            var response = json_parse(data, <?php echo (int) IP_DEBUG; ?>);
            if (response.success === 1) {
                // The validation was successful
                $('.has-error').removeClass('has-error');
                $('#client_note').val('');

                // Reload all notes
                $('#notes_list').load("<?php echo site_url('clients/ajax/load_client_notes'); ?>",
                    {
                        client_id: client_id
                    }, function (response) {
                        <?php echo IP_DEBUG ? 'console.log(response);' : ''; ?>

                        setTimeout(add_delete_client_notes_click_event, 161);
                    });
            } else {
                // The validation was not successful
                $('.has-error').removeClass('has-error');
                for (var key in response.validation_errors) {
                    $('#' + key).parent().addClass('has-error');
                }
            }
            close_loader();
        }
        function delete_client_note(event) {
            show_loader();
            $.post('<?php echo site_url('clients/ajax/delete_client_note'); ?>',
                {
                    client_note_id: $(this).attr('data-id')
                }, function (data) {
                    reload_client_notes(data);
                }
            );
        }
        $('#save_client_note').click(function () {
            show_loader();
            $.post('<?php echo site_url('clients/ajax/save_client_note'); ?>',
                {
                    client_id: client_id,
                    client_note: $('#client_note').val()
                }, function (data) {
                    reload_client_notes(data);
                }
            );
        });
        add_delete_client_notes_click_event();
    });
</script>

<?php
$locations = [];
foreach ($custom_fields as $custom_field) {
    if (array_key_exists($custom_field->custom_field_location, $locations)) {
        $locations[$custom_field->custom_field_location] += 1;
    } else {
        $locations[$custom_field->custom_field_location] = 1;
    }
}
?>

<div id="headerbar">
    <h1 class="headerbar-title"><?php _htmlsc(format_client($client)); ?></h1>

    <div class="headerbar-item pull-right">
        <div class="btn-group btn-group-sm">
            <a href="#" class="btn btn-default client-create-quote"
               data-client-id="<?php echo $client->client_id; ?>">
                <i class="fa fa-file"></i> <?php _trans('create_quote'); ?>
            </a>
            <a href="#" class="btn btn-default client-create-invoice"
               data-client-id="<?php echo $client->client_id; ?>">
                <i class="fa fa-file-text"></i> <?php _trans('create_invoice'); ?></a>
            <a href="<?php echo site_url('clients/form/' . $client->client_id); ?>"
               class="btn btn-default">
                <i class="fa fa-edit"></i> <?php _trans('edit'); ?>
            </a>
            <form action="<?php echo site_url('clients/delete/' . $client->client_id); ?>"
                  method="POST" class="btn-group btn-group-sm">
                <?php _csrf_field(); ?>
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('<?php _trans('delete_client_warning'); ?>');">
                    <i class="fa fa-trash-o"></i> <?php _trans('delete'); ?>
                </button>
            </form>
        </div>
    </div>

</div>

<ul id="submenu" class="nav nav-tabs nav-tabs-noborder">
    <li<?php echo $activeTab == 'detail' ? ' class="active"' : ''; ?>><a data-toggle="tab" href="#client-details"><?php _trans('details'); ?></a></li>
    <li<?php echo $activeTab == 'quotes' ? ' class="active"' : ''; ?>><a data-toggle="tab" href="#client-quotes"><?php _trans('quotes'); ?></a></li>
    <li<?php echo $activeTab == 'invoices' ? ' class="active"' : ''; ?>><a data-toggle="tab" href="#client-invoices"><?php _trans('invoices'); ?></a></li>
    <li<?php echo $activeTab == 'payments' ? ' class="active"' : ''; ?>><a data-toggle="tab" href="#client-payments"><?php _trans('payments'); ?></a></li>
</ul>

<div id="content" class="tabbable tabs-below no-padding">
    <div class="tab-content no-padding">

        <div id="client-details" class="tab-pane tab-rich-content<?php echo $activeTab == 'detail' ? ' active' : ''; ?>">

            <?php $this->layout->load_view('layout/alerts'); ?>

            <div class="row">
                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-8">

                    <h3><?php _htmlsc(format_client($client)); ?></h3>
                    <p><?php $this->layout->load_view('clients/partial_client_address'); ?></p>

                </div>
                <div class="col-xs-12 col-sm-6 col-md-6 col-lg-4">

                    <table class="table table-bordered no-margin">
                        <tr>
                            <th><?php _trans('language'); ?></th>
                            <td class="td-amount"><?php echo ucfirst($client->client_language); ?></td>
                        </tr>
                        <tr>
                            <th><?php _trans('total_billed'); ?></th>
                            <td class="td-amount"><?php echo format_currency($client->client_invoice_total); ?></td>
                        </tr>
                        <tr>
                            <th><?php _trans('total_paid'); ?></th>
                            <th class="td-amount"><?php echo format_currency($client->client_invoice_paid); ?></th>
                        </tr>
                        <tr>
                            <th><?php _trans('total_balance'); ?></th>
                            <td class="td-amount"><?php echo format_currency($client->client_invoice_balance); ?></td>
                        </tr>
                    </table>

                </div>
            </div>

            <hr>
<?php
$colClass = 'col-xs-12 col-sm-6' . ($req_einvoicing ? ' col-lg-4' : '');
?>
            <div class="row">
                <div class="<?php echo $colClass; ?>">

                    <div class="panel panel-default no-margin">
                        <div class="panel-heading"><?php _trans('contact_information'); ?></div>
                        <div class="panel-body table-content">
                            <table class="table no-margin">
<?php if ($client->client_invoicing_contact) { ?>
                                <tr>
                                    <th><?php _trans('contact'); ?> (<?php _trans('invoicing'); ?>)</th>
                                    <td><?php _htmlsc($client->client_invoicing_contact); ?></td>
                                </tr>
<?php } ?>
<?php if ($client->client_email) { ?>
                                <tr>
                                    <th><?php _trans('email'); ?></th>
                                    <td><?php _auto_link($client->client_email, 'email'); ?></td>
                                </tr>
<?php } ?>
<?php if (get_setting('billcom_enabled') == '1') { ?>
                                <tr>
                                    <th><?php _trans('billcom_status'); ?></th>
                                    <td>
<?php if (isset($client->client_billcom_enabled) && $client->client_billcom_enabled == 1) { ?>
                                        <span class="label label-success"><i class="fa fa-check"></i> <?php _trans('enabled'); ?></span>
<?php } else { ?>
                                        <span class="label label-default"><?php _trans('disabled'); ?></span>
<?php } ?>
                                    </td>
                                </tr>
<?php if (!empty($client->client_billcom_customer_id)) { ?>
                                <tr>
                                    <th><?php _trans('client_billcom_customer_id'); ?></th>
                                    <td><?php echo htmlspecialchars($client->client_billcom_customer_id); ?></td>
                                </tr>
<?php } ?>
<?php } ?>
<?php if ($client->client_phone) { ?>
                                <tr>
                                    <th><?php _trans('phone'); ?></th>
                                    <td><?php _htmlsc($client->client_phone); ?></td>
                                </tr>
<?php } ?>
<?php if ($client->client_mobile) { ?>
                                <tr>
                                    <th><?php _trans('mobile'); ?></th>
                                    <td><?php _htmlsc($client->client_mobile); ?></td>
                                </tr>
<?php } ?>
<?php if ($client->client_fax) { ?>
                                <tr>
                                    <th><?php _trans('fax'); ?></th>
                                    <td><?php _htmlsc($client->client_fax); ?></td>
                                </tr>
<?php } ?>
<?php if ($client->client_web) { ?>
                                <tr>
                                    <th><?php _trans('web'); ?></th>
                                    <td><?php _auto_link($client->client_web, 'url', true); ?></td>
                                </tr>
<?php } ?>

<?php
foreach ($custom_fields as $custom_field) {
    if ($custom_field->custom_field_location == 2) {
        $column = $custom_field->custom_field_label;
        $value  = $this->mdl_client_custom->form_value('cf_' . $custom_field->custom_field_id);
?>
                                <tr>
                                    <th><?php _htmlsc($column); ?></th>
                                    <td><?php _htmlsc($value); ?></td>
                                </tr>
<?php
    }
}
?>
                            </table>
                        </div>
                    </div>

                </div>
                <div class="<?php echo $colClass; ?>">
                    <div class="panel panel-default no-margin">

                        <div class="panel-heading"><?php _trans('tax_information'); ?></div>
                        <div class="panel-body table-content">
                            <table class="table no-margin">
                                <tr>
                                    <th><?php _trans('company'); ?></th>
                                    <td><?php _htmlsc($client->client_company ? $client->client_company : ''); ?></td>
                                </tr>
<?php if ($client->client_vat_id) { ?>
                                <tr>
                                    <th><?php _trans('vat_id'); ?></th>
                                    <td><?php _htmlsc($client->client_vat_id); ?></td>
                                </tr>
<?php } ?>
<?php if ($client->client_tax_code) { ?>
                                <tr>
                                    <th><?php _trans('tax_code'); ?></th>
                                    <td><?php _htmlsc($client->client_tax_code); ?></td>
                                </tr>
<?php } ?>

<?php

$default_custom = false;
foreach ($custom_fields as $custom_field) {
    if ( ! $default_custom && ! $custom_field->custom_field_location) {
        $default_custom = true;
    }

    if ($custom_field->custom_field_location == 4) {
        $column = $custom_field->custom_field_label;
        $value  = $this->mdl_client_custom->form_value('cf_' . $custom_field->custom_field_id);
?>
                                <tr>
                                    <th><?php _htmlsc($column); ?></th>
                                    <td><?php _htmlsc($value); ?></td>
                                </tr>
<?php
    }
}
?>
                            </table>
                        </div>

                    </div>
                </div>
<?php
if ($req_einvoicing) {
?>
                <!-- eInvoicing panel -->
                <div class="<?php echo $colClass; ?>">
                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">
                            e-<?php _trans('invoicing'); ?>
<?php
// Panel eInvoicing checks
$title_tip = ' data-toggle="tooltip" data-placement="bottom" title="' . trans('edit'); // Tooltip helper ! Need add: . '"'

// For eInvoicing panel client (users)
$nb_users    = count($req_einvoicing->users);
$me          = $req_einvoicing->users[$_SESSION['user_id']]->show_table;
$nb          = $req_einvoicing->show_table;
$ln          = 'user' . (($nb ?: $nb_users) > 1 ? 's' : ''); // tweak 1 on more nb_users no ok
$user_toggle = ($req_einvoicing->show_table ? ($me ? 'danger' : 'warning') : 'default') . ' ' . ($me ? '" aria-expanded="true' : '" collapsed" aria-expanded="false');
// For eInvoicing panel User(s) table
$class_checks     = ['fa fa-lg fa-check-square-o text-success', 'fa fa-lg fa-edit text-warning', 'fa fa-lg fa-square-o text-danger']; // Checkboxe icons
$base             = 'address_1 zip city country company tax_code vat_id'; // Field names
$keys             = explode(' ', $base); // to array
$lang             = explode(' ', strtr($base, ['_1' => '']));
$user_fields_nook = ($req_einvoicing->clients[$client->client_id]->einvoicing_empty_fields > 0 && $client->client_einvoicing_version != '');
// eInvoicing button toggle users table checking
if ($client->client_einvoicing_active && ! $user_fields_nook) {
?>
                            <span class="pull-right cursor-pointer btn btn-xs btn-default alert-<?php echo $user_toggle; ?>"
                                  data-toggle="collapse" data-target=".einvoice-users-check"
                                  onclick="switch_fa_toggle('einvoice_users_check_fa_toggle')"
                            >
                                <i class="fa fa-<?php echo $nb ? ($me ? 'ban' : 'warning') : 'check-square-o text-success'; ?>"></i>
                                <span data-toggle="tooltip" data-placement="bottom" title="<?php echo '🗸 ' . ($nb_users - $nb) . '/' . $nb_users . ' ' . trans('user' . ($nb_users > 1 ? 's' : '')); ?>">
                                    <?php echo ($nb ?: $nb_users) . ' ' . trans($ln); ?>
                                </span>
                                <i id="einvoice_users_check_fa_toggle" class="fa fa-toggle-<?php echo $me ? 'on' : 'off'; ?> fa-margin"></i>
                            </span>
<?php
} // End if eInvoicing button toggle users table checking
?>
                        </div>
                        <div class="panel-body table-content">

                            <table class="table no-margin">
                                <tr>
                                    <th>e-<?php _htmlsc(trans('invoice') . ' ' . trans('version') . ' (' . trans('send')); ?>)</th>
                                    <td><?php echo ($client->client_einvoicing_active && $client->client_einvoicing_version) ? get_xml_full_name($client->client_einvoicing_version) : trans('none'); ?></td>
                                </tr>
                            </table>

<?php
// eInvoicing panel Client checks table
if ($client->client_einvoicing_active && $user_fields_nook) {
?>
                            <div class="alert alert-warning small" style="margin: 0px 10px 10px;">
                                <table>
                                    <tr>
                                        <td><i class="fa fa-exclamation-triangle fa-2x"></i>&emsp;</td>
                                        <td><?php _trans('einvoicing_no_creation_hint'); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <table class="table no-margin" id="client_einvoice_checks">
                                <thead class="einvoice-client-checks-lists">
                                    <tr><th><?php _trans('required_fields'); ?> (<?php _trans('client'); ?>)</th></tr>
                                </thead>
                                <tbody class="einvoice-client-checks-lists">
                                    <tr><td>
<?php
    $reqs = []; // init ! important
    if ($req_einvoicing->clients[$client->client_id]->einvoicing_empty_fields) {
        foreach ($keys as $l => $key) {
            if ($req_einvoicing->clients[$client->client_id]->{$key}) {
                $reqs[] = '<i class="' . $class_checks[$req_einvoicing->clients[$client->client_id]->{$key}] . '"></i>'
                        . anchor(
                            '/clients/form/' . $client->client_id . '#client_' . $key,
                            trans($lang[$l]),
                            $title_tip . ' #' . trans($lang[$l]) . ' (' . mb_trim(trans('field')) . ')"'
                        ); // ! Need add: "
            }
        }
    }
    // Show fields in Errors
?>
                                        <span><?php echo implode(', ', $reqs); ?></span>

                                    </td></tr>
                                </tbody>
                            </table>
<?php
} else {
    // Client ok! Show check fields user(s)
?>
                            <table class="einvoice-users-check table no-margin collapse<?php
                                   echo $req_einvoicing->users[$_SESSION['user_id']]->einvoicing_empty_fields ? ' in" aria-expanded="true' : '" aria-expanded="false'; ?>"
                            >
                                <thead class="einvoice-users-check-lists">
                                    <tr><th colspan="3"><?php _trans('required_fields'); ?> (<?php _trans('user' . ($nb_users > 1 ? 's' : '')); ?>)</th></tr>
                                    <tr><th><?php _trans('user'); ?></th><th class="text-nowrap">e-<?php _trans('invoice'); ?></th><th><?php _trans('errors'); ?></th></tr>
                                </thead>
<?php
    // eInvoicing panel User(s) checks table
    foreach ($req_einvoicing->users as $uid => $user) {
        $ok = ! $user->einvoicing_empty_fields; // or ->show_table (inverse)
        $tx = $ok ? 'success' : ($_SESSION['user_id'] == $uid ? 'danger' : 'warning');
?>
                                <tbody class="einvoice-user-check-lists">
                                    <tr class="text-<?php echo $tx; ?>">
                                        <td class="te te-1">
                                            <i class="fa fa-fw fa-user"></i>
                                            <span><?php echo anchor('/users/form/' . $uid, $user->user_name); ?></span>
                                        </td>
                                        <td><i class="<?php echo $class_checks[$ok ? 0 : 2]; ?>"></i><?php _trans($ok ? 'yes' : 'no'); ?></td>
                                        <td>
<?php
        $reqs = []; // Re init ! important
        if ($user->einvoicing_empty_fields) {
            $reqs = []; // reuse
            foreach ($keys as $l => $key) {
                if ($user->{$key}) {
                    $reqs[] = '<span class="text-nowrap"><i class="' . $class_checks[$user->{$key}] . '"></i>'
                            . anchor(
                                '/users/form/' . $uid . '#user_' . $key,
                                trans($lang[$l]),
                                // ! Need add: "
                                $title_tip . ' #' . trans($lang[$l]) . ' (' . mb_trim(trans('field')) . ' ' . htmlsc($user->user_name) . ')"'
                            )
                            . '</span>';
                }
            }
        }
        // Show Ok or Errors
        $reqs = $reqs === [] ? trans('no') : implode(', ', $reqs);
?>
                                            <span><?php echo $reqs; ?></span>
                                        </td>
                                    </tr>
                                </tbody>
<?php
    } // End foreach users
?>
                            </table>
<?php
} // End if client ok
?>

                        </div>
                    </div>
                </div>
                <!-- /eInvoicing panel -->
<?php
}
?>
            </div>

<?php
if ($client->client_surname != '') { // Client is not a company
?>
            <hr>

            <div class="row">
                <div class="col-xs-12 col-md-6">

                    <div class="panel panel-default">
                        <div class="panel-heading"><?php _trans('personal_information'); ?></div>

                        <div class="panel-body table-content">
                            <table class="table no-margin">
                                <tr>
                                    <th><?php _trans('birthdate'); ?></th>
                                    <td><?php echo format_date($client->client_birthdate); ?></td>
                                </tr>
                                <tr>
                                    <th><?php _trans('gender'); ?></th>
                                    <td><?php echo format_gender($client->client_gender) ?></td>
                                </tr>
<?php
    if ($this->mdl_settings->setting('sumex') == '1') {
?>
                                <tr>
                                    <th><?php _trans('sumex_ssn'); ?></th>
                                    <td><?php echo format_avs($client->client_avs) ?></td>
                                </tr>

                                <tr>
                                    <th><?php _trans('sumex_insurednumber'); ?></th>
                                    <td><?php _htmlsc($client->client_insurednumber) ?></td>
                                </tr>

                                <tr>
                                    <th><?php _trans('sumex_veka'); ?></th>
                                    <td><?php _htmlsc($client->client_veka) ?></td>
                                </tr>
<?php
    } // fi sumex

    foreach ($custom_fields as $custom_field) {
        if ($custom_field->custom_field_location == 3) {
            $column = $custom_field->custom_field_label;
            $value  = $this->mdl_client_custom->form_value('cf_' . $custom_field->custom_field_id);
?>
                                <tr>
                                    <th><?php _htmlsc($column); ?></th>
                                    <td><?php _htmlsc($value); ?></td>
                                </tr>
<?php
        }
    }
?>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
<?php
} // fi client->client_surname

if ($default_custom) {
?>
            <hr>

            <div class="row">
                <div class="col-xs-12 col-md-6">
                    <div class="panel panel-default no-margin">

                        <div class="panel-heading"><?php _trans('custom_fields'); ?></div>
                        <div class="panel-body table-content">
                            <table class="table no-margin">
<?php
    foreach ($custom_fields as $custom_field) {
        if ( ! $custom_field->custom_field_location) { // == 0
            $column = $custom_field->custom_field_label;
            $value  = $this->mdl_client_custom->form_value('cf_' . $custom_field->custom_field_id);
?>
                                <tr>
                                    <th><?php _htmlsc($column); ?></th>
                                    <td><?php _htmlsc($value); ?></td>
                                </tr>
<?php
        }
    }
?>
                            </table>
                        </div>

                    </div>
                </div>
            </div>
<?php
} // fi custom_fields
?>

            <hr>

            <div class="row">
                <div class="col-xs-12 col-md-6">

                    <div class="panel panel-default no-margin">
                        <div class="panel-heading">
                            <?php _trans('notes'); ?>
                        </div>
                        <div class="panel-body">
                            <div id="notes_list">
                                <?php echo $partial_notes; ?>
                            </div>
                            <div class="input-group">
                                <textarea id="client_note" class="form-control" rows="2" style="resize:none"></textarea>
                                <span id="save_client_note" class="input-group-addon btn btn-default">
                                    <?php _trans('add_note'); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
<?php
foreach (explode(' ', 'quote invoice payment') as $what) {
    $table = $what . '_table'; // dynamic var name
?>
        <div id="client-<?php echo $what; ?>s" class="tab-pane table-content<?php echo $activeTab == $what . 's' ? ' active' : ''; ?>">
<?php if ($what == 'invoice') { ?>
            <?php $this->layout->load_view('layout/alerts'); ?>
            
            <div class="container-fluid" style="margin-bottom: 15px;">
                <div class="row">
                    <div class="col-xs-12 col-md-6">
                        <div class="input-group">
                            <input type="text" id="invoice-search-input" class="form-control" placeholder="<?php _trans('search_invoice_items'); ?>" style="height: 34px;">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="invoice-search-clear" style="height: 34px;">
                                    <i class="fa fa-times"></i>
                                </button>
                            </span>
                        </div>
                    </div>
                    <div class="col-xs-12 col-md-6">
                        <div class="pull-right">
                            <div class="btn-group btn-group-sm index-options" style="margin-right: 10px;">
                                <button type="button" class="btn btn-primary invoice-status-filter" data-status="all">
                                    <?php _trans('all'); ?>
                                </button>
                                <button type="button" class="btn btn-default invoice-status-filter" data-status="draft">
                                    <?php _trans('draft'); ?>
                                </button>
                                <button type="button" class="btn btn-default invoice-status-filter" data-status="sent">
                                    <?php _trans('sent'); ?>
                                </button>
                                <button type="button" class="btn btn-default invoice-status-filter" data-status="viewed">
                                    <?php _trans('viewed'); ?>
                                </button>
                                <button type="button" class="btn btn-default invoice-status-filter" data-status="paid">
                                    <?php _trans('paid'); ?>
                                </button>
                                <button type="button" class="btn btn-default invoice-status-filter" data-status="overdue">
                                    <?php _trans('overdue'); ?>
                                </button>
                            </div>
                            <button type="button" class="btn btn-sm btn-warning" id="btn-batch-payment-client" style="display:none; margin-right: 10px;">
                                <i class="fa fa-money"></i> <?php _trans('enter_payment'); ?>
                            </button>
                            <button type="button" class="btn btn-sm btn-info" id="btn-batch-download-pdf-client" style="display:none; margin-right: 10px;">
                                <i class="fa fa-file-pdf-o"></i> <?php _trans('download_selected_pdfs'); ?>
                            </button>
<?php if (get_setting('billcom_enabled') == '1' && isset($client->client_billcom_enabled) && $client->client_billcom_enabled == 1) { ?>
                            <button type="button" class="btn btn-sm btn-success" id="btn-batch-send-billcom-client" style="display:none; margin-right: 10px;">
                                <i class="fa fa-cloud-upload"></i> <?php _trans('billcom_send_batch'); ?>
                            </button>
<?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div style="clear: both;"></div>
<?php } else { ?>
            <div class="container-fluid">
                <div class="pull-right" style="margin:.5rem 0 -1.5rem 0; <?php echo ($what == 'payment') ? 'display:none;' : ''; ?>">
                    <?php echo pager(site_url('clients/view/' . $client->client_id . '/' . $what . 's'), 'mdl_' . $what . 's'); ?>
                </div>
            </div>
<?php } ?>
            <?php echo ${$table}; ?>
<?php if ($what == 'invoice') { ?>
            <div id="invoice-load-more" class="text-center" style="padding: 20px;">
                <button type="button" class="btn btn-default" id="btn-load-more-invoices">
                    <i class="fa fa-chevron-down"></i> <?php _trans('load_more'); ?>
                </button>
            </div>
            <div id="invoice-loading" class="text-center" style="padding: 20px; display: none;">
                <i class="fa fa-spinner fa-spin fa-2x"></i>
                <p><?php _trans('loading'); ?>...</p>
            </div>
            <div id="invoice-end" class="text-center" style="padding: 20px; display: none; color: #999;">
                <i class="fa fa-check"></i>
                <p><?php _trans('no_more_invoices'); ?></p>
            </div>
<?php } elseif ($what == 'payment') { ?>
            <div id="payment-load-more" class="text-center" style="padding: 20px;">
                <button type="button" class="btn btn-default" id="btn-load-more-payments">
                    <i class="fa fa-chevron-down"></i> <?php _trans('load_more'); ?>
                </button>
            </div>
            <div id="payment-loading" class="text-center" style="padding: 20px; display: none;">
                <i class="fa fa-spinner fa-spin fa-2x"></i>
                <p><?php _trans('loading'); ?>...</p>
            </div>
            <div id="payment-end" class="text-center" style="padding: 20px; display: none; color: #999;">
                <i class="fa fa-check"></i>
                <p><?php _trans('no_more_payments'); ?></p>
            </div>
<?php } ?>
        </div>
<?php
}
?>
    </div>
</div>

<script>
// Function to update select all checkbox state (global scope for reuse)
function updateSelectAllState() {
    var total = $('.invoice-select').length;
    var checked = $('.invoice-select:checked').length;
    var $selectAll = $('#select-all-invoices');
    
    if (checked === 0) {
        // None selected
        $selectAll.prop('checked', false);
        $selectAll.prop('indeterminate', false);
    } else if (checked === total) {
        // All selected
        $selectAll.prop('checked', true);
        $selectAll.prop('indeterminate', false);
    } else {
        // Some selected (partial)
        $selectAll.prop('checked', false);
        $selectAll.prop('indeterminate', true);
    }
}

$(document).ready(function() {
    // Handle select all checkbox for client invoice tab (works regardless of Bill.com status)
    $('#select-all-invoices').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('.invoice-select').prop('checked', isChecked);
        $(this).prop('indeterminate', false);
        toggleClientBatchButton();
    });

    // Handle individual checkbox changes for client invoice tab (works regardless of Bill.com status)
    $(document).on('change', '.invoice-select', function() {
        updateSelectAllState();
        toggleClientBatchButton();
    });

    // Show/hide batch buttons based on selection (works regardless of Bill.com status)
    function toggleClientBatchButton() {
        var checked = $('.invoice-select:checked').length;
        if (checked > 0) {
            $('#btn-batch-payment-client').show();
            $('#btn-batch-payment-client').html('<i class="fa fa-money"></i> <?php _trans('enter_payment'); ?> (' + checked + ')');
<?php if (get_setting('billcom_enabled') == '1' && isset($client->client_billcom_enabled) && $client->client_billcom_enabled == 1) { ?>
            $('#btn-batch-send-billcom-client').show();
            $('#btn-batch-send-billcom-client').html('<i class="fa fa-cloud-upload"></i> <?php _trans('billcom_send_batch'); ?> (' + checked + ')');
<?php } ?>
            $('#btn-batch-download-pdf-client').show();
            $('#btn-batch-download-pdf-client').html('<i class="fa fa-file-pdf-o"></i> <?php _trans('download_selected_pdfs'); ?> (' + checked + ')');
        } else {
            $('#btn-batch-payment-client').hide();
<?php if (get_setting('billcom_enabled') == '1' && isset($client->client_billcom_enabled) && $client->client_billcom_enabled == 1) { ?>
            $('#btn-batch-send-billcom-client').hide();
<?php } ?>
            $('#btn-batch-download-pdf-client').hide();
        }
    }

<?php if (get_setting('billcom_enabled') == '1' && isset($client->client_billcom_enabled) && $client->client_billcom_enabled == 1) { ?>
    // Handle batch send button click for client tab (Bill.com only)
    $('#btn-batch-send-billcom-client').on('click', function() {
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

        // Add CSRF token (get fresh token from cookie to handle regenerated tokens)
        form.append($('<input>', {
            'type': 'hidden',
            'name': csrf_token_name,
            'value': Cookies.get(csrf_cookie_name)
        }));

        // Add redirect URL to return to client view
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'redirect_url',
            'value': '<?php echo site_url('clients/view/' . $client->client_id . '/invoices'); ?>'
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
<?php } ?>

    // Handle batch payment button click for client tab
    $('#btn-batch-payment-client').on('click', function() {
        var selectedIds = [];
        $('.invoice-select:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('<?php _trans('no_invoices_selected'); ?>');
            return;
        }

        // Load the batch payment modal
        $('#modal-placeholder').load("<?php echo site_url('payments/ajax/modal_add_batch_payment'); ?>", {
            invoice_ids: selectedIds
        });
    });

    // Handle batch PDF download button click for client tab (works regardless of Bill.com status)
    $('#btn-batch-download-pdf-client').on('click', function() {
        var selectedIds = [];
        $('.invoice-select:checked').each(function() {
            selectedIds.push($(this).val());
        });

        if (selectedIds.length === 0) {
            alert('<?php _trans('no_invoices_selected'); ?>');
            return;
        }

        // Show loading indicator
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php _trans('generating_pdfs'); ?>...');
        
        var $button = $(this);

        // Create a form and submit
        var form = $('<form>', {
            'method': 'POST',
            'action': '<?php echo site_url('invoices/batch_download_pdf'); ?>'
        });

        // Add CSRF token (get fresh token from cookie to handle regenerated tokens)
        form.append($('<input>', {
            'type': 'hidden',
            'name': csrf_token_name,
            'value': Cookies.get(csrf_cookie_name)
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
        
        // Re-enable button after a delay (file download doesn't reload page)
        setTimeout(function() {
            $button.prop('disabled', false);
            toggleClientBatchButton();
        }, 2000);
    });
});
</script>

<script>
// Infinite scroll for client invoices
$(document).ready(function() {
    var clientId = <?php echo $client->client_id; ?>;
    var invoiceOffset = <?php echo count($invoices); ?>; // Initial load count
    var invoiceLimit = 20;
    var isLoading = false;
    var hasMore = true;
    var currentStatus = 'all';
    var searchTerm = '';
    var searchTimeout = null;
    
    // Only enable infinite scroll on the invoices tab
    function initInfiniteScroll() {
        if (!$('#client-invoices').hasClass('active')) {
            return;
        }
        
        // Detect scroll on both window and the tab container
        $(window).on('scroll.invoiceScroll', checkScrollPosition);
        $('#client-invoices').on('scroll.invoiceScroll', checkScrollPosition);
        
        // Also check on window resize (in case content changes)
        $(window).on('resize.invoiceScroll', checkScrollPosition);
    }
    
    function checkScrollPosition() {
        // Check if we're in the invoices tab
        if (!$('#client-invoices').hasClass('active') || !hasMore || isLoading) {
            return;
        }
        
        // Get the invoice table
        var $invoiceTable = $('#client-invoices table');
        if ($invoiceTable.length === 0) {
            return;
        }
        
        // Calculate if we're near the bottom
        var tableBottom = $invoiceTable.offset().top + $invoiceTable.height();
        var viewportBottom = $(window).scrollTop() + $(window).height();
        
        // Trigger when table bottom is within 500px of viewport bottom
        if (viewportBottom >= tableBottom - 500) {
            loadMoreInvoices();
        }
    }
    
    function loadMoreInvoices() {
        if (isLoading || !hasMore) {
            return;
        }
        
        isLoading = true;
        $('#invoice-loading').show();
        $('#invoice-load-more').hide();
        $('#invoice-end').hide();
        
        $.ajax({
            url: '<?php echo site_url('clients/ajax/load_more_invoices'); ?>',
            type: 'POST',
            data: {
                client_id: clientId,
                offset: invoiceOffset,
                limit: invoiceLimit,
                status: currentStatus,
                search: searchTerm,
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.html) {
                    // Append new rows to the invoice table
                    $('#client-invoices table tbody').append(response.html);
                    
                    // Update offset for next load
                    invoiceOffset += response.count;
                    
                    // Update select all state since total count changed
                    updateSelectAllState();
                    
                    // Check if there are more records
                    hasMore = response.has_more;
                    
                    if (!hasMore) {
                        $('#invoice-end').show();
                        $('#invoice-load-more').hide();
                    } else {
                        $('#invoice-load-more').show();
                    }
                } else {
                    hasMore = false;
                    $('#invoice-end').show();
                    $('#invoice-load-more').hide();
                }
            },
            error: function() {
                console.error('Failed to load more invoices');
                hasMore = false;
                $('#invoice-load-more').hide();
            },
            complete: function() {
                isLoading = false;
                $('#invoice-loading').hide();
            }
        });
    }
    
    // Handle status filter button clicks
    $('.invoice-status-filter').on('click', function() {
        var newStatus = $(this).data('status');
        
        if (newStatus === currentStatus) {
            return; // Already on this filter
        }
        
        // Update button states (match main invoice list styling)
        $('.invoice-status-filter').removeClass('btn-primary').addClass('btn-default');
        $(this).removeClass('btn-default').addClass('btn-primary');
        
        // Update current status
        currentStatus = newStatus;
        
        // Reset pagination - start fresh
        invoiceOffset = 0;
        hasMore = true;
        isLoading = false;
        
        // Clear current invoices
        $('#client-invoices table tbody').empty();
        
        // Update select all state since table is now empty
        updateSelectAllState();
        
        // Hide end message and load more button
        $('#invoice-load-more').hide();
        $('#invoice-end').hide();
        
        // Load filtered invoices
        loadMoreInvoices();
    });
    
    // Handle "Load More" button click
    $('#btn-load-more-invoices').on('click', function() {
        loadMoreInvoices();
    });
    
    // Handle search input with debounce
    $('#invoice-search-input').on('keyup', function() {
        clearTimeout(searchTimeout);
        var inputValue = $(this).val().trim();
        
        searchTimeout = setTimeout(function() {
            if (searchTerm !== inputValue) {
                searchTerm = inputValue;
                
                // Reset pagination - start fresh
                invoiceOffset = 0;
                hasMore = true;
                isLoading = false;
                
                // Clear current invoices
                $('#client-invoices table tbody').empty();
                
                // Update select all state since table is now empty
                updateSelectAllState();
                
                // Hide end message and load more button
                $('#invoice-load-more').hide();
                $('#invoice-end').hide();
                
                // Load filtered invoices
                loadMoreInvoices();
            }
        }, 500); // 500ms debounce
    });
    
    // Handle search clear button
    $('#invoice-search-clear').on('click', function() {
        $('#invoice-search-input').val('');
        
        if (searchTerm !== '') {
            searchTerm = '';
            
            // Reset pagination - start fresh
            invoiceOffset = 0;
            hasMore = true;
            isLoading = false;
            
            // Clear current invoices
            $('#client-invoices table tbody').empty();
            
            // Update select all state since table is now empty
            updateSelectAllState();
            
            // Hide end message and load more button
            $('#invoice-load-more').hide();
            $('#invoice-end').hide();
            
            // Load filtered invoices
            loadMoreInvoices();
        }
    });
    
    // Initialize when the invoices tab is clicked
    $('a[href="#client-invoices"]').on('shown.bs.tab', function() {
        initInfiniteScroll();
    });
    
    // Initialize immediately if on invoices tab
    if ($('#client-invoices').hasClass('active')) {
        initInfiniteScroll();
    }
});

// Infinite scroll for client payments
$(document).ready(function() {
    var clientId = <?php echo $client->client_id; ?>;
    var paymentOffset = <?php echo count($payments); ?>; // Initial load count
    var paymentLimit = 20;
    var isLoadingPayments = false;
    var hasMorePayments = true;
    
    // Only enable infinite scroll on the payments tab
    function initPaymentInfiniteScroll() {
        if (!$('#client-payments').hasClass('active')) {
            return;
        }
        
        // Detect scroll on both window and the tab container
        $(window).on('scroll.paymentScroll', checkPaymentScrollPosition);
        $('#client-payments').on('scroll.paymentScroll', checkPaymentScrollPosition);
        
        // Also check on window resize (in case content changes)
        $(window).on('resize.paymentScroll', checkPaymentScrollPosition);
    }
    
    function checkPaymentScrollPosition() {
        // Check if we're in the payments tab
        if (!$('#client-payments').hasClass('active') || !hasMorePayments || isLoadingPayments) {
            return;
        }
        
        // Get the payment table
        var $paymentTable = $('#client-payments table');
        if ($paymentTable.length === 0) {
            return;
        }
        
        // Calculate if we're near the bottom
        var tableBottom = $paymentTable.offset().top + $paymentTable.height();
        var viewportBottom = $(window).scrollTop() + $(window).height();
        
        // Trigger when table bottom is within 500px of viewport bottom
        if (viewportBottom >= tableBottom - 500) {
            loadMorePayments();
        }
    }
    
    function loadMorePayments() {
        if (isLoadingPayments || !hasMorePayments) {
            return;
        }
        
        isLoadingPayments = true;
        $('#payment-load-more').hide();
        $('#payment-loading').show();
        
        $.ajax({
            url: '<?php echo site_url('clients/ajax/load_more_payments'); ?>',
            type: 'POST',
            data: {
                client_id: clientId,
                offset: paymentOffset,
                limit: paymentLimit,
                <?php echo $this->security->get_csrf_token_name(); ?>: Cookies.get(csrf_cookie_name)
            },
            dataType: 'json',
            success: function(response) {
                if (response.html && response.count > 0) {
                    // Append new payment rows
                    $('#client-payments table tbody').append(response.html);
                    paymentOffset += response.count;
                    
                    if (!response.has_more) {
                        hasMorePayments = false;
                        $('#payment-load-more').hide();
                        $('#payment-end').show();
                    } else {
                        $('#payment-load-more').show();
                    }
                } else {
                    hasMorePayments = false;
                    $('#payment-load-more').hide();
                    $('#payment-end').show();
                }
                
                $('#payment-loading').hide();
                isLoadingPayments = false;
            },
            error: function() {
                alert('<?php _trans('error_loading_payments'); ?>');
                $('#payment-loading').hide();
                $('#payment-load-more').show();
                isLoadingPayments = false;
            }
        });
    }
    
    // Manual load more button
    $('#btn-load-more-payments').on('click', function() {
        loadMorePayments();
    });
    
    // Initialize when the payments tab is clicked
    $('a[href="#client-payments"]').on('shown.bs.tab', function() {
        initPaymentInfiniteScroll();
    });
    
    // Initialize immediately if on payments tab
    if ($('#client-payments').hasClass('active')) {
        initPaymentInfiniteScroll();
    }
});
</script>
