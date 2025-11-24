<div id="headerbar">
    <h1 class="headerbar-title"><?php _trans('payments'); ?></h1>

    <div class="headerbar-item pull-right">
        <a class="btn btn-sm btn-primary" href="<?php echo site_url('payments/form'); ?>">
            <i class="fa fa-plus"></i> <?php _trans('new'); ?>
        </a>
    </div>

    <div class="headerbar-item pull-right" style="display:none;">
        <?php echo pager(site_url('payments/index'), 'mdl_payments'); ?>
    </div>

</div>

<div id="content" class="table-content">

    <?php $this->layout->load_view('layout/alerts'); ?>

    <div id="filter_results">
        <?php $this->layout->load_view('payments/partial_payments_table'); ?>
    </div>
    
    <!-- Infinite Scroll Controls -->
    <div id="payment-load-more" class="text-center" style="padding: 20px;">
        <button type="button" id="btn-load-more-payments" class="btn btn-default">
            <i class="fa fa-arrow-down"></i> <?php _trans('load_more'); ?>
        </button>
    </div>
    
    <div id="payment-loading" class="text-center" style="padding: 20px; display: none;">
        <i class="fa fa-spinner fa-spin fa-2x"></i>
        <p><?php _trans('loading'); ?>...</p>
    </div>
    
    <div id="payment-end" class="text-center" style="padding: 20px; display: none; color: #999;">
        <i class="fa fa-check"></i> <?php _trans('no_more_payments'); ?>
    </div>

</div>

<script>
$(document).ready(function() {
    // Infinite scroll variables
    var currentOffset = <?php echo count($payments); ?>;
    var isLoading = false;
    var hasMorePayments = true;

    // Initialize infinite scroll
    initInfiniteScroll();

    function initInfiniteScroll() {
        // Auto-load on scroll
        $(window).on('scroll', function() {
            checkScrollPosition();
        });
        
        // Manual load button
        $('#btn-load-more-payments').on('click', function() {
            loadMorePayments();
        });
    }

    function checkScrollPosition() {
        if (isLoading || !hasMorePayments) return;
        
        var scrollTop = $(window).scrollTop();
        var windowHeight = $(window).height();
        var docHeight = $(document).height();
        
        // Trigger load when user is 200px from bottom
        if (scrollTop + windowHeight >= docHeight - 200) {
            loadMorePayments();
        }
    }

    function loadMorePayments() {
        if (isLoading || !hasMorePayments) return;
        
        isLoading = true;
        $('#payment-load-more').hide();
        $('#payment-loading').show();
        
        $.ajax({
            url: '<?php echo site_url('payments/load_more_payments'); ?>',
            method: 'POST',
            data: {
                offset: currentOffset,
                limit: 20,
                <?php echo $this->security->get_csrf_token_name(); ?>: '<?php echo $this->security->get_csrf_hash(); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.html && response.count > 0) {
                    // Append new payment rows
                    $('#filter_results table tbody').append(response.html);
                    currentOffset += response.count;
                    
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
                isLoading = false;
            },
            error: function() {
                alert('<?php _trans('error_loading_payments'); ?>');
                $('#payment-loading').hide();
                $('#payment-load-more').show();
                isLoading = false;
            }
        });
    }
});
</script>
