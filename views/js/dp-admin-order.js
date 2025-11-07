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

$(function(){
    $('a.requestDeutschepostLabelData').click(function(evt){
        evt.preventDefault();

        var link = $(this);

        $.fancybox.open({
            href: link.attr('href'),
            type: 'iframe'
        });

        return false;
    });

    $(document).on('click', '#showAllDPLabels', function(){
        $('#allDPLabels').toggle();
    });
});