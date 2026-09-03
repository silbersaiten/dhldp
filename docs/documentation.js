(function () {
    'use strict';

    var printButton = document.querySelector('[data-print-document]');
    if (printButton) {
        printButton.addEventListener('click', function () {
            window.print();
        });
    }
}());
