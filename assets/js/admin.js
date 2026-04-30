/* CHM Appointments — Admin JS */
(function($){
    // Status update
    $(document).on('change', '.chm-status-select', function(){
        var $sel = $(this),
            id   = $sel.data('id'),
            val  = $sel.val();
        if(!val) return;

        $.post(chmAdmin.ajax, {
            action: 'chm_appt_update_status',
            nonce:  chmAdmin.nonce,
            id:     id,
            status: val
        }, function(res){
            if(res.success){
                var $row = $sel.closest('tr');
                $row.find('.chm-badge')
                    .attr('class','chm-badge chm-badge--'+val)
                    .text(val.charAt(0).toUpperCase()+val.slice(1));
                $sel.val('');
            } else {
                alert('Error: '+(res.data||'Unknown'));
            }
        });
    });

    // Delete
    $(document).on('click', '.chm-delete-btn', function(){
        if(!confirm('Delete this appointment permanently?')) return;
        var $btn = $(this), id = $btn.data('id');

        $.post(chmAdmin.ajax, {
            action: 'chm_appt_delete',
            nonce:  chmAdmin.nonce,
            id:     id
        }, function(res){
            if(res.success){
                $btn.closest('tr').fadeOut(300, function(){ $(this).remove(); });
            } else {
                alert('Error: '+(res.data||'Unknown'));
            }
        });
    });
})(jQuery);
