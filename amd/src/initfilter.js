define(['jquery'], function($) {
    return {
        init: function() {

            const params = new URLSearchParams(window.location.search);
            if (!params.has('local_mycoursesfilter')) {
                return;
            }

            const query = params.get('query');
            if (!query) {
                return;
            }

            // Warten, bis die Course-Overview geladen ist
            const interval = setInterval(() => {
                const searchInput = document.querySelector(
                    'input[data-region="course-search"]'
                );

                if (searchInput) {
                    clearInterval(interval);

                    searchInput.value = query;
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }, 300);
        }
    };
});
