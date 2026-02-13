/**
 * DHL Deutschepost
 *
 * @author    silbersaiten <info@silbersaiten.de>
 * @copyright 2026 silbersaiten
 * @license   See joined file licence.txt
 * @category  Module
 * @support   silbersaiten <support@silbersaiten.de>
 * @version   3.2.0
 * @link      https://www.silbersaiten.de
 */

var dpAdminConfigure = {
    init: function () {
        var self = this;

        if ($('#dp_sender_person').is(':checked')) {
            self.showPerson();
        }
        if ($('#dp_sender_company').is(':checked')) {
            self.showCompany();
        }
        $('#dp_sender_person').click(function () {
            self.showPerson();
        });
        $('#dp_sender_company').click(function () {
            self.showCompany();
        });
        self.initSettingsTabs();
    },
    showCompany: function () {
        $('.dp_company').slideDown('slow');
    },
    showPerson: function () {
        $('.dp_company').hide();
    },
    initSettingsTabs: function () {
        var panels = [];

        $('button[name="submitSaveDPOptionsGlobal"], button[name="submitSaveDPAddressOptions"]').each(function () {
            var $panel = $(this).closest('.panel');
            if (!$panel.length) {
                return;
            }

            var isAdded = false;
            $.each(panels, function (index, panel) {
                if (panel[0] === $panel[0]) {
                    isAdded = true;
                    return false;
                }
            });

            if (!isAdded) {
                panels.push($panel);
            }
        });

        if (!panels.length) {
            return;
        }

        var $tabsContainer = $('<div id="dhldp-settings-tabs-dp" class="dhldp-settings-tabs"></div>');
        var $tabsNavigation = $('<ul></ul>');

        $.each(panels, function (index, $panel) {
            var panelId = 'dhldp-dp-settings-tab-' + index;
            var title = $.trim($panel.find('.panel-heading').first().text()) || ('Tab ' + (index + 1));

            $panel.attr('id', panelId).addClass('dhldp-settings-tab-panel');
            $tabsNavigation.append('<li><a href="#' + panelId + '">' + title + '</a></li>');
        });

        $tabsContainer.append($tabsNavigation);
        panels[0].before($tabsContainer);

        $.each(panels, function (index, $panel) {
            $tabsContainer.append($panel);
        });

        $tabsContainer.tabs({
            active: 0
        });
    }
}

$(function () {
    dpAdminConfigure.init();
});
