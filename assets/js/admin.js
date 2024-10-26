jQuery(document).ready(function($) {
    // Tab Management
    $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
        e.preventDefault();
        var target = $(this).data('tab');
        
        $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.isi-settings-section').hide();
        $('#' + target).show();
    });

    // Initialize first tab
    $('.nav-tab-wrapper .nav-tab:first').click();
});
