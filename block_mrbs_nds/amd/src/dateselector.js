/**
 * Date selector helper for block_mrbs_nds.
 *
 * Provides the ChangeOptionDays() function used by the date navigation forms.
 * In Phase 3 this will be extended to a full AMD module.
 */
define([], function() {
    'use strict';

    var weekDayNames = [];

    /**
     * Update the days dropdown to reflect the correct number of days
     * for the selected month/year.
     *
     * @param {HTMLFormElement} form    The form containing the dropdowns.
     * @param {string}          prefix  Field name prefix (e.g. '' or 'rep_end_').
     */
    function ChangeOptionDays(form, prefix) {
        var monthSel = form.elements[prefix + 'month'];
        var yearSel  = form.elements[prefix + 'year'];
        var daySel   = form.elements[prefix + 'day'];

        if (!monthSel || !yearSel || !daySel) {
            return;
        }

        var month    = parseInt(monthSel.value, 10);
        var year     = parseInt(yearSel.value, 10);
        var maxDays  = new Date(year, month, 0).getDate();
        var curDay   = parseInt(daySel.value, 10);

        while (daySel.length > maxDays) {
            daySel.remove(daySel.length - 1);
        }
        while (daySel.length < maxDays) {
            var opt = document.createElement('option');
            opt.value = daySel.length + 1;
            opt.text  = daySel.length + 1;
            daySel.add(opt);
        }

        if (curDay > maxDays) {
            daySel.value = maxDays;
        }
    }

    function SetWeekDayNames() {
        weekDayNames = Array.prototype.slice.call(arguments);
    }

    return {
        init: function(mon, tue, wed, thu, fri, sat, sun) {
            SetWeekDayNames(mon, tue, wed, thu, fri, sat, sun);
            // Expose globals needed by legacy inline scripts.
            window.ChangeOptionDays = ChangeOptionDays;
            window.SetWeekDayNames  = SetWeekDayNames;
        }
    };
});
