// script.js
(function($) {
    $(document).ready(function() {
        function toggleAllCheckboxes() {
            var checkboxes = document.querySelectorAll('.unused-media-item input[type="checkbox"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = document.getElementById('select-all-checkbox').checked;
            });
        }

        $('#select-all-checkbox').on('click', function() {
            toggleAllCheckboxes();
        });
    });
})(jQuery);
