jQuery(document).ready(function () {

    var jq = jQuery;
    jq("#bpcp_change").on('click', '#bpcp-del-image', function () {
        var $this = jq(this);

        jq.post(ajaxurl, {
                action: 'bpcp_delete_cover',
                cookie: encodeURIComponent(document.cookie),
                buid: $this.data('buid'),
                _wpnonce: jq($this.parents('form').get(0)).find('#_wpnonce').val()
            },
            function (response) {
                //remove the current image
                jq("div#message").remove();
                $this.parent().before(jq("<div id='message' class='update'>" + response + "</div>"));
                $this.prev('.current-cover').fadeOut(100);//hide current image
                $this.parent().remove();//remove from dom the delete link
                //give feedback
                //remove the body class
                jq('body').removeClass('is-user-profile');
            }
        );
        return false;

    })

});