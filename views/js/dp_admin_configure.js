/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2020 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   1.0.0
 * @link      http://www.silbersaiten.de
 */

var dp_admin_configure = {
    init: function() {
        var self = this;

        if ($('#dp_sender_person').is(':checked'))
            self.showPerson();

        if ($('#dp_sender_company').is(':checked'))
            self.showCompany();

        $('#dp_sender_person').click(function(){
            self.showPerson();
        });
        $('#dp_sender_company').click(function(){
            self.showCompany();
        });

    },
    showCompany: function() {
        $('.dp_company').slideDown('slow');
    },
    showPerson: function() {
        $('.dp_company').hide();
    }
}

$(function(){
    dp_admin_configure.init();
})